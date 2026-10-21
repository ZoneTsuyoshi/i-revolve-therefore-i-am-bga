<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Data\StoredGameMutator;
use Bga\Games\IRevolveThereforeIAm\Setup\SetupPlanner;

/**
 * 80 クリーンアップ（仕様書 001 §13、006 §5.6）。ターンプレイヤーについて a〜d を順に行い、N29 `cleanup` にまとめる。
 *
 * - a 自己構成済みカードを縦に戻す。
 * - b 使用済み（裏向き）の万能移動鍵を表に戻す。
 * - c 想い出カードのない部屋に、その部屋のレベルの山札から 1 枚を裏向きで補充する。
 * - d 場札が 6 枚未満なら、6 枚になるまでレベル 1 の山札から表向きで補充する。
 * - 山札が足りなければ補充できるだけ補充して山札切れのフラグを立て、最後に判定する（Q-05、Q-24）。
 *   勝利が成立していれば勝利を優先し、そうでなければ全員敗北（`DECK_OUT`）。このとき 81 には入らない（Q-42）。
 */
final class CleanupPhase
{
    public function __construct(
        private readonly MemoryCardRepository $memory,
        private readonly RoomRepository $rooms,
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
        private readonly StoredGameMutator $mutator,
        private readonly \Closure $nameOf,
    ) {
    }

    /**
     * @return string 遷移名（`SKIP` = 82 へ、`GAME_END` = 95 へ）
     */
    public function run(): string
    {
        $player = $this->globals->turnPlayer();

        $untapped = [];
        foreach ($this->memory->inLoc(MemoryLocation::Tableau, $player) as $card) {
            if ($card->tapped) {
                $untapped[] = $card->id;
            }
        }
        $this->memory->setTapped($untapped, false);

        $wildUsed = $this->players->wildUsed($player);
        if ($wildUsed > 0) {
            $this->players->addToken($player, PlayerValues::COLUMN_WILD_UP, $wildUsed);
            $this->players->addToken($player, PlayerValues::COLUMN_WILD_USED, -$wildUsed);
        }

        $this->mutator->add([Notification::cleanup(
            $player,
            ($this->nameOf)($player),
            $untapped,
            $wildUsed,
            $this->refillRooms(),
            $this->refillOpen(),
            $this->mutator->decks(),
        )]);
        // TODO(I-10): ターン終了時にだけ条件を満たせる目標 O004 を獲得できる状況なら ASK（81 TurnEnd。006 §6.3）。
        //  山札切れが成立したときは O004 より先に全員敗北（Q-42）。

        $this->mutator->settleDeckOut();

        // 勝利が成立していた場合も終局（勝利を優先して判定済み）
        return $this->globals->endReason() !== null ? Transition::GAME_END : Transition::SKIP;
    }

    /**
     * @return list<array{pos: int, card_id: int, back: string}>
     */
    private function refillRooms(): array
    {
        $out = [];
        foreach (array_keys($this->rooms->all()) as $pos) {
            if ($this->memory->count(MemoryLocation::Room, $pos) > 0) {
                continue;
            }
            $level = RoomRepository::levelOfPosition($pos);
            $ids = $this->memory->peekDeck($level, 1);
            if ($ids === []) {
                $this->mutator->flagDeckExhausted($level);
                continue;
            }
            $card = $this->memory->find($ids[0]) ?? throw new \LogicException('unknown card');
            $this->memory->move($card->id, MemoryLocation::Room, $pos);
            $out[] = ['pos' => $pos, 'card_id' => $card->id, 'back' => PublicView::backKey(PublicView::definition($card->type))];
        }

        return $out;
    }

    /**
     * @return list<array{slot: int, id: int, type: int}>
     */
    private function refillOpen(): array
    {
        $taken = array_map(static fn ($c): int => $c->locationArg, $this->memory->inLocAnyArg(MemoryLocation::Open));
        $out = [];
        for ($slot = 0; $slot < SetupPlanner::OPEN_SIZE; $slot++) {
            if (in_array($slot, $taken, true)) {
                continue;
            }
            $ids = $this->memory->peekDeck(1, 1);
            if ($ids === []) {
                $this->mutator->flagDeckExhausted(1);
                break;
            }
            $card = $this->memory->find($ids[0]) ?? throw new \LogicException('unknown card');
            $this->memory->move($card->id, MemoryLocation::Open, $slot);
            $out[] = ['slot' => $slot, ...Notification::cardRef($card->id, $card->type)];
        }

        return $out;
    }
}
