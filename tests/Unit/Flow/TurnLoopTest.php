<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\TurnContext;
use Bga\Games\IRevolveThereforeIAm\Flow\HandLimit;
use Bga\Games\IRevolveThereforeIAm\Flow\SeatOrder;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Flow\TurnKit;
use IRevolveTests\Support\EndgameRig;
use IRevolveTests\Support\MoveFixture;
use PHPUnit\Framework\TestCase;

/**
 * 想起 → 床アクション → 自己構成フェイズの窓 → クリーンアップの入口までを、zombie と同じ既定の操作（仮のボタンと同じ）で通す。
 * Express Start で手番が回り続けること（006 §10）の確認。全ての部屋・想起効果を通る。
 */
final class TurnLoopTest extends TestCase
{
    /**
     * 自動で最後まで進める（選択は既定の答え、行動は取り消し、手札上限は zombie の捨て方）。
     */
    private function drive(MoveFixture $fx, ?TurnKit $kit = null): string
    {
        $kit ??= $fx->kit;
        for ($i = 0; $i < 60; $i++) {
            $result = $kit->resolver->resolve();
            $player = $result->activePlayer;
            switch ($result->transition) {
                case Transition::CHOICE:
                    $kit->choices->auto($player);
                    break;
                case Transition::COMPOSE:
                    $kit->compose->cancel($player);
                    break;
                case Transition::ANALYSIS:
                    $kit->analysis->cancel($player);
                    break;
                case Transition::HAND_LIMIT:
                    $fx->handLimit->discardExcess($player, "P{$player}", $fx->handLimit->zombieChoice($player));
                    break;
                default:
                    return $result->transition;
            }
        }
        self::fail('the turn did not finish');
    }

    /**
     * 12 の部屋 × 13 種の想起効果（部屋ごとに効果をずらして 2 周。全ての部屋と全ての効果を通る）で、
     * 手番が詰まらずに 60 → クリーンアップの入口へ進む。
     */
    public function testEveryRoomAndRecallEffectFlowsToCleanup(): void
    {
        $recallCards = [1, 15, 13, 14, 22, 24, 23, 31, 32, 94, 95, 33, 96];
        foreach (range(1, 12) as $pos) {
            foreach ([$recallCards[($pos - 1) % 13], $recallCards[($pos + 5) % 13]] as $type) {
                $fx = new MoveFixture();
                $fx->place(11, $pos);
                $fx->place(22, $pos);
                $fx->place(33, $pos % 12 + 1);
                $fx->giveTableau(11, [4]);
                $fx->giveHandAny(11, 6);
                $fx->giveHandAny(22, 7);
                $fx->tokens(11, light: 2);
                $fx->roomCard($pos, $type);

                $fx->kit->recall->start();
                self::assertSame(Transition::RESUME_FLOOR, $this->drive($fx), "room $pos / M$type recall");
                $floor = $fx->kit->floor->start();
                if ($floor['choose']) {
                    $fx->kit->floor->choose('');
                }
                self::assertSame(Transition::RESUME_CONSTRUCT, $this->drive($fx), "room $pos / M$type floor");
                $fx->kit->construct->end(11);
                self::assertSame(Transition::RESUME_CLEANUP, $this->drive($fx), "room $pos / M$type construct");

                self::assertSame(0, $fx->stack->count());
                foreach (MoveFixture::SEATS as $id) {
                    self::assertLessThanOrEqual(HandLimit::LIMIT, $fx->memory->count(MemoryLocation::Hand, $id), "player $id hand");
                    foreach ([PlayerValues::COLUMN_LIGHT, PlayerValues::COLUMN_COMPOSE, PlayerValues::COLUMN_ANALYSIS] as $column) {
                        self::assertGreaterThanOrEqual(0, (int) $fx->db->value("SELECT `$column` FROM `player` WHERE `player_id` = $id"));
                    }
                }
                self::assertSame(StateId::CLEANUP, $fx->globals->resume());
            }
        }
    }

    /**
     * 1 ターン（移動 → 想起 → 床アクション → 自己構成 → クリーンアップ）を zombie と同じ既定の操作で進める。
     *
     * @return string 終局なら `Transition::GAME_END`、そうでなければ `Transition::SKIP`（次のターンへ）
     */
    private function playTurn(EndgameRig $rig, int $player): string
    {
        $fx = $rig->fx;
        $kit = $rig->kit;
        $context = new TurnContext();
        $context->startPos = $fx->players->persona($player);
        $fx->globals->setTurnContext($context);

        $plan = $fx->phase->zombiePlan($player);
        $moved = $fx->phase->submit($player, "P{$player}", $plan, true);
        if ($moved->ended) {
            return Transition::GAME_END;
        }
        $kit->triggers->emit($moved->events);
        $fx->globals->setResume(StateId::RECALL_START);
        if (($t = $this->drive($fx, $kit)) !== Transition::RESUME_RECALL) {
            return $this->ended($t);
        }

        $kit->recall->start();
        if (($t = $this->drive($fx, $kit)) !== Transition::RESUME_FLOOR) {
            return $this->ended($t);
        }
        $floor = $kit->floor->start();
        if ($floor['choose']) {
            $kit->floor->choose('');
        }
        if (($t = $this->drive($fx, $kit)) !== Transition::RESUME_CONSTRUCT) {
            return $this->ended($t);
        }
        $kit->construct->end($player);
        if (($t = $this->drive($fx, $kit)) !== Transition::RESUME_CLEANUP) {
            return $this->ended($t);
        }

        return $rig->cleanup->run();
    }

    private function ended(string $transition): string
    {
        self::assertSame(Transition::GAME_END, $transition);

        return $transition;
    }

    /**
     * zombie と同じ操作だけでゲームが終局まで到達する（山札切れか勝利）。終局の理由と勝者の書き込み、
     * 進行度の入力になる山札の消費、全員敗北の場合の勝者なしを確かめる。
     */
    public function testAGameOfZombiesReachesTheEnd(): void
    {
        $fx = new MoveFixture();
        $rig = new EndgameRig($fx);
        foreach ([11 => 1, 22 => 5, 33 => 9] as $player => $pos) {
            $fx->place($player, $pos);
        }
        $deckBefore = $fx->memory->count(MemoryLocation::Deck1);
        $fx->globals->setTurnPlayer(11);

        $turns = 0;
        $transition = Transition::SKIP;
        while ($transition !== Transition::GAME_END && $turns < 1500) {
            $turns++;
            $player = $fx->globals->turnPlayer();
            $transition = $this->playTurn($rig, $player);
            $fx->globals->setTurnPlayer(SeatOrder::next(MoveFixture::SEATS, $player));
        }

        self::assertSame(Transition::GAME_END, $transition, "the game did not end in $turns turns");
        self::assertContains($fx->globals->endReason(), ['DECK_OUT', 'FRAGMENTS', 'INSTANT_MEMORY', 'APEX_OF_PSYCHE']);
        if ($fx->globals->endReason() === 'DECK_OUT') {
            self::assertNull($fx->globals->winnerId(), '全員敗北');
            self::assertNotSame([], $fx->globals->deckOut());
        } else {
            self::assertContains($fx->globals->winnerId(), MoveFixture::SEATS);
        }
        self::assertLessThan($deckBefore, $fx->memory->count(MemoryLocation::Deck1), '山札が消費されている');
        // 場札と部屋は、終局の時点でも整合している（補充の途中で終わっても重複しない）
        self::assertSame(144, $fx->memory->countAll());
    }
}
