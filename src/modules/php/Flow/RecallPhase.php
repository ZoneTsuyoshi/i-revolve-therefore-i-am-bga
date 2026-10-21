<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Data\EffectStack;
use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\SpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\RecallTimesQuery;
use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\FloorCards;
use Bga\Games\IRevolveThereforeIAm\Material\OwnerScope;

/**
 * 40 `RecallStart`: 想起フェイズの開始（001 §7、006 §5.2）。
 *
 * ターンプレイヤーのいる部屋の想い出カードを表向きにし（N09）、その部屋にいる全員を、ターンプレイヤーから時計回りの席順に
 * 並べる。発動者ごとに `RECALL_ACTIVATION`（M062 のときターンプレイヤーは 2 回）と手札上限の確認（発動者ごとが 1 つの単位。
 * 008 §6.9）、最後に `ACQUIRE_ROOM_CARD`（ターンプレイヤーが開始時の部屋のカードを獲得。Q-04）と手札上限の確認を積む。
 * ターンプレイヤーだけが使える想起効果（床アクションの代替・移動。Q-39）は、他の発動者の frame を積まない。
 * 戻り先は 50（`resume`）。発動者の列は、想起効果で移動した後の位置とは関係しない。
 */
final class RecallPhase
{
    public function __construct(
        private readonly MemoryCardRepository $memory,
        private readonly PlayerValues $players,
        private readonly RoomRepository $rooms,
        private readonly GlobalValues $globals,
        private readonly EffectStack $stack,
        private readonly RecallTimesQuery $times,
        private readonly SpecSource $specs,
        private readonly \Closure $nameOf,
    ) {
    }

    /**
     * @return list<Notice>
     */
    public function start(): array
    {
        $turn = $this->globals->turnPlayer();
        $pos = $this->players->persona($turn);
        $this->globals->setResume(StateId::FLOOR_ACTION_START);
        $context = $this->globals->turnContext();
        $cards = $this->memory->inLoc(MemoryLocation::Room, $pos);
        if ($cards === []) {
            // 部屋に想い出カードが無い状況は起きない（001 §7）。起きても想起は何もせず進める
            $context->recall = ['room_pos' => $pos];
            $this->globals->setTurnContext($context);

            return [];
        }
        $card = $cards[0];
        $this->memory->setFaceUp($card->id, true);
        $definition = PublicView::definition($card->type);

        $activators = [];
        $counts = [];
        foreach (SeatOrder::startingFrom($this->players->playerIds(), $turn) as $playerId) {
            if ($this->players->persona($playerId) === $pos) {
                $activators[] = $playerId;
                $counts[$playerId] = $this->times->times($playerId, $pos);
            }
        }
        $context->recall = [
            'room_pos' => $pos,
            'card_id' => $card->id,
            'card_type' => $definition->id,
            'activators' => $activators,
            'counts' => $counts,
        ];
        $this->globals->setTurnContext($context);

        $spec = $this->specs->find(EffectCatalog::recallId($definition->recall));
        $frames = [];
        foreach ($activators as $playerId) {
            if ($spec !== null && $spec->ownerScope === OwnerScope::TURN_PLAYER && $playerId !== $turn) {
                continue;
            }
            $frames[] = new EffectFrame(
                EffectFrame::KIND_RECALL_ACTIVATION,
                $playerId,
                source: $definition->id,
                params: ['times' => $counts[$playerId], 'nth' => 1, 'groups' => []],
            );
            $frames[] = EffectFrame::handLimitCheck($turn);
        }
        $frames[] = new EffectFrame(EffectFrame::KIND_ACQUIRE_ROOM_CARD, $turn);
        $frames[] = EffectFrame::handLimitCheck($turn);
        $this->stack->pushBack($frames);

        $floor = FloorCards::get(sprintf('F%02d', $this->rooms->floorAt($pos)));

        return [Notification::recallStarted(
            $turn,
            ($this->nameOf)($turn),
            $pos,
            $floor->roomName,
            Notification::cardRef($card->id, $card->type),
            $definition->nameEn,
            $activators,
            $counts,
        )];
    }
}
