<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Flow\HandLimit;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
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
    private function drive(MoveFixture $fx): string
    {
        for ($i = 0; $i < 60; $i++) {
            $result = $fx->kit->resolver->resolve();
            $player = $result->activePlayer;
            switch ($result->transition) {
                case Transition::CHOICE:
                    $fx->kit->choices->auto($player);
                    break;
                case Transition::COMPOSE:
                    $fx->kit->compose->cancel($player);
                    break;
                case Transition::ANALYSIS:
                    $fx->kit->analysis->cancel($player);
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
}
