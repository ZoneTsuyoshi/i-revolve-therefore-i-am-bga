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
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;

/**
 * フリーアクション（001 §11-3、006 §6）のうち、想い出カードを捨てて心光を得る処理。
 *
 * 想い出カード 2 枚分につき心光 1 つ（レベル 1 は 1 枚分、レベル 2 は 1 枚で 2 枚分。Q-03、FAQ 行 40）。端数は失われる（Q-22 に準ずる）。
 * 回数は無制限で、ターンプレイヤーだけが使う（呼べる状態の制限は状態クラス側。006 §6.2）。目標カードの獲得は {@see ObjectiveService}。
 */
final class FreeActionService
{
    public function __construct(
        private readonly MemoryCardRepository $memory,
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
        private readonly \Closure $nameOf,
        private readonly \Closure $victoryCheck,
    ) {
    }

    /**
     * 捨てるカードが何枚分か（レベル 1 は 1 枚分、レベル 2 は 2 枚分）。
     *
     * @param list<int> $cardIds
     */
    public function weight(array $cardIds): int
    {
        $weight = 0;
        foreach ($cardIds as $id) {
            $card = $this->memory->find($id) ?? throw new EffectViolation(clienttranslate('Unknown card'));
            $weight += PublicView::definition($card->type)->level;
        }

        return $weight;
    }

    /**
     * 手札のカードを捨てて、2 枚分につき心光 1 つを得る。心光が 1 つも得られない捨て方（1 枚分だけ）は拒否する。
     *
     * @param list<int> $cardIds
     * @return list<Notice>
     * @throws EffectViolation
     */
    public function discardForLight(int $player, array $cardIds): array
    {
        if ($cardIds === [] || count(array_unique($cardIds)) !== count($cardIds)) {
            throw new EffectViolation(clienttranslate('Choose cards to discard'));
        }
        $refs = [];
        foreach ($cardIds as $id) {
            $card = $this->memory->find($id);
            if ($card === null || $card->location !== MemoryLocation::Hand || $card->locationArg !== $player) {
                throw new EffectViolation(clienttranslate('A selected card is not in your hand'));
            }
            $refs[] = Notification::cardRef($id, $card->type);
        }
        $light = intdiv($this->weight($cardIds), 2);
        if ($light < 1) {
            throw new EffectViolation(clienttranslate('You need to discard at least two cards worth to gain inner light'));
        }
        $name = ($this->nameOf)($player);
        foreach ($cardIds as $id) {
            $card = $this->memory->find($id) ?? throw new \LogicException("unknown card $id");
            $this->memory->move($id, MemoryLocation::discard(PublicView::definition($card->type)->level));
        }
        $this->players->addToken($player, PlayerValues::COLUMN_LIGHT, $light);
        $turn = $this->globals->turnContext();
        $turn->lightsGained += $light;
        $this->globals->setTurnContext($turn);
        $notices = [
            Notification::cardsDiscarded(
                $player,
                $name,
                $refs,
                'LIGHT',
                [1 => $this->memory->count(MemoryLocation::Discard1), 2 => $this->memory->count(MemoryLocation::Discard2)],
                $this->memory->count(MemoryLocation::Hand, $player),
            ),
            Notification::tokensChanged(
                $player,
                $name,
                ['light' => $light],
                ['light' => $this->players->light($player)],
                'DISCARD_LIGHT',
                null,
                Notification::tokenName('light'),
                $light,
            ),
        ];
        ($this->victoryCheck)();

        return $notices;
    }
}
