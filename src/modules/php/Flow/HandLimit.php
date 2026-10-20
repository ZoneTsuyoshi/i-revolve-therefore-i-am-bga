<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Data\EffectStack;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCard;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;

/**
 * 手札上限（8 枚。001 §12、006 §8）。unit（移動フェイズ全体など）の終わりに `HAND_LIMIT_CHECK` を積み、
 * 70 が超過したプレイヤーを 72 へ送る。超過分を捨てる処理もここ。
 */
final class HandLimit
{
    public const LIMIT = 8;

    public function __construct(
        private readonly MemoryCardRepository $memoryCards,
        private readonly PlayerValues $players,
        private readonly EffectStack $stack,
    ) {
    }

    public function excess(int $player): int
    {
        return max(0, $this->memoryCards->count(MemoryLocation::Hand, $player) - self::LIMIT);
    }

    /**
     * 超過しているプレイヤーが 1 人でもいれば `HAND_LIMIT_CHECK` を積む（unit の終わり）。
     */
    public function pushCheckIfNeeded(int $turnPlayer): bool
    {
        if ($this->firstOver($turnPlayer) === null) {
            return false;
        }
        $this->stack->pushFront([new EffectFrame(EffectFrame::KIND_HAND_LIMIT_CHECK, $turnPlayer, unit: true)]);

        return true;
    }

    /**
     * 手札が上限を超えている最初のプレイヤー（ターンプレイヤーを先に、続いて席順。006 §8）。
     */
    public function firstOver(int $turnPlayer): ?int
    {
        $order = $this->players->playerIds();
        $start = array_search($turnPlayer, $order, true);
        $start = $start === false ? 0 : $start;
        $count = count($order);
        for ($i = 0; $i < $count; $i++) {
            $id = $order[($start + $i) % $count];
            if ($this->excess($id) > 0) {
                return $id;
            }
        }

        return null;
    }

    /**
     * 超過分ちょうどを選んで捨てる。捨札は捨てたカードのレベルの捨札（Q-23）。
     *
     * @param list<int> $cardIds
     * @throws EffectViolation 枚数が超過分と一致しない、または手札にない
     */
    public function discardExcess(int $player, string $playerName, array $cardIds): Notice
    {
        if (count($cardIds) !== $this->excess($player) || count(array_unique($cardIds)) !== count($cardIds)) {
            throw new EffectViolation('Discard exactly the excess cards');
        }
        $cards = [];
        foreach ($cardIds as $cardId) {
            $card = $this->memoryCards->find($cardId);
            if ($card === null || $card->location !== MemoryLocation::Hand || $card->locationArg !== $player) {
                throw new EffectViolation("Card $cardId is not in your hand");
            }
            $cards[] = $card;
        }
        $refs = [];
        foreach ($cards as $card) {
            $this->memoryCards->move($card->id, MemoryLocation::discard(PublicView::definition($card->type)->level));
            $refs[] = ['id' => $card->id, 'type' => $card->type];
        }

        return Notification::cardsDiscarded(
            $player,
            $playerName,
            $refs,
            'HAND_LIMIT',
            [1 => $this->memoryCards->count(MemoryLocation::Discard1), 2 => $this->memoryCards->count(MemoryLocation::Discard2)],
            $this->memoryCards->count(MemoryLocation::Hand, $player),
        );
    }

    /**
     * zombie の捨て方（006 §10）: 超過枚数分、レベル 1 を優先して手札の後ろから。
     *
     * @return list<int>
     */
    public function zombieChoice(int $player): array
    {
        $hand = $this->memoryCards->inLoc(MemoryLocation::Hand, $player);
        usort($hand, static function (MemoryCard $a, MemoryCard $b): int {
            $la = PublicView::definition($a->type)->level;
            $lb = PublicView::definition($b->type)->level;

            return $la <=> $lb ?: $b->id <=> $a->id;
        });

        return array_map(static fn(MemoryCard $c): int => $c->id, array_slice($hand, 0, $this->excess($player)));
    }
}
