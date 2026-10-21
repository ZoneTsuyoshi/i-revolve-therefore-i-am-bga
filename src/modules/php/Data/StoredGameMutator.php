<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameMutator;
use Bga\Games\IRevolveThereforeIAm\Flow\Notice;
use Bga\Games\IRevolveThereforeIAm\Flow\Notification;
use Bga\Games\IRevolveThereforeIAm\Flow\SpiritArrival;
use Bga\Games\IRevolveThereforeIAm\Material\ActionKind;
use Bga\Games\IRevolveThereforeIAm\Material\Corridor;
use Bga\Games\IRevolveThereforeIAm\Material\EgoCards;
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;
use Bga\Games\IRevolveThereforeIAm\Material\FloorCards;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;
use Bga\Games\IRevolveThereforeIAm\Material\RecallGroup;
use Bga\Games\IRevolveThereforeIAm\Material\TokenKind;

/**
 * `GameMutator`（効果エンジンがゲーム状態を書く口。008 §11.1）の本番の実装。DB に書き、送る通知を溜める（{@see self::drain()}）。
 * 規則の判断は `Effects/` が行い、ここは決まった結果を反映するだけ。勝利判定（断片の結集）は `$victoryCheck` に委ねる（I-08・I-10）。
 */
final class StoredGameMutator implements GameMutator
{
    /** @var list<Notice> */
    private array $notices = [];

    /**
     * @param \Closure(int): string $nameOf プレイヤー ID → プレイヤー名
     * @param \Closure(): void $victoryCheck 断片の結集（自己構成スコア 20 以上）などの勝利判定。成立したら end_reason を書く
     */
    public function __construct(
        private readonly MemoryCardRepository $memory,
        private readonly RoomRepository $rooms,
        private readonly SpiritRepository $spirits,
        private readonly EgoRepository $egos,
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
        private readonly EffectStack $stack,
        private readonly SpiritArrival $arrival,
        private readonly \Closure $nameOf,
        private readonly \Closure $victoryCheck,
    ) {
    }

    /**
     * 溜まった通知を取り出す（取り出した分は消える）。
     *
     * @return list<Notice>
     */
    public function drain(): array
    {
        $notices = $this->notices;
        $this->notices = [];

        return $notices;
    }

    /**
     * 他のサービスが作った通知を、送る順に加える。
     *
     * @param list<Notice> $notices
     */
    public function add(array $notices): void
    {
        array_push($this->notices, ...$notices);
    }

    public function drawFromDeck(int $player, int $count, ?Origin $origin): int
    {
        $ids = $this->memory->drawFromDeck(1, $count, MemoryLocation::Hand, $player);
        if (count($ids) < $count) {
            // 山札から引く処理の共通の口: 足りなかった瞬間に山札切れのフラグを立てる（Q-05。判定は frame の終了時）
            $this->flagDeckExhausted();
        }
        if ($ids !== []) {
            $cards = array_map(fn (int $id): array => $this->ref($id), $ids);
            $reason = match ($origin) {
                Origin::FLOOR => 'FLOOR',
                Origin::RECALL => 'RECALL',
                Origin::BONUS => 'ANALYSIS_BONUS',
                default => 'EFFECT',
            };
            $this->notices[] = Notification::cardsDrawn(
                $player,
                ($this->nameOf)($player),
                $cards,
                $reason,
                $this->decks(),
                $this->memory->count(MemoryLocation::Hand, $player),
            );
        }

        return count($ids);
    }

    public function flagDeckExhausted(int $level = 1): void
    {
        $levels = $this->globals->deckOut();
        if (!in_array($level, $levels, true)) {
            $levels[] = $level;
            $this->globals->setDeckOut($levels);
        }
    }

    /**
     * 立っている山札切れのフラグを判定する（frame・アクション・クリーンアップの終了時。006 §9）。
     * 先に勝利判定を行い、勝利が成立していれば勝利を優先する（Q-05）。そうでなければ全員敗北（`DECK_OUT`）にして N30 を溜める。
     *
     * @return bool 山札切れで全員敗北が確定した
     */
    public function settleDeckOut(): bool
    {
        $levels = $this->globals->deckOut();
        if ($this->globals->endReason() !== null || $levels === []) {
            return false;
        }
        ($this->victoryCheck)();
        if ($this->globals->endReason() !== null) {
            return false;
        }
        sort($levels);
        foreach ($levels as $level) {
            $this->notices[] = Notification::deckOut($level);
        }
        $this->globals->setEndReason('DECK_OUT');
        $this->globals->setWinnerId(null);

        return true;
    }

    public function takeFromField(int $player, int $cardId): void
    {
        $card = $this->memory->find($cardId) ?? throw new \LogicException("unknown card $cardId");
        $slot = $card->locationArg;
        $this->memory->move($cardId, MemoryLocation::Hand, $player);
        $this->notices[] = Notification::cardsGained(
            $player,
            ($this->nameOf)($player),
            [$this->ref($cardId)],
            [$slot],
            $this->memory->count(MemoryLocation::Hand, $player),
            'EFFECT',
        );
    }

    public function gainLight(int $player, int $count, ?Origin $origin): void
    {
        $this->players->addToken($player, PlayerValues::COLUMN_LIGHT, $count);
        $context = $this->globals->turnContext();
        $context->lightsGained += $count;
        $this->globals->setTurnContext($context);
        $reason = match ($origin) {
            Origin::FLOOR => 'FLOOR',
            Origin::RECALL => 'RECALL',
            Origin::TOUR => 'TOUR_BONUS',
            Origin::SPIRIT_CARD, Origin::BONUS => 'ANALYSIS',
            default => 'EFFECT',
        };
        $this->notices[] = Notification::tokensChanged(
            $player,
            ($this->nameOf)($player),
            ['light' => $count],
            ['light' => $this->players->light($player)],
            $reason,
            $origin?->value,
            Notification::tokenName('light'),
            $count,
        );
    }

    public function gainToken(int $player, TokenKind $token, int $count): void
    {
        [$key, $column] = match ($token) {
            TokenKind::COMPOSE => ['compose', PlayerValues::COLUMN_COMPOSE],
            TokenKind::ANALYSIS => ['analysis', PlayerValues::COLUMN_ANALYSIS],
            TokenKind::WILD_MOVE_KEY => ['wild_up', PlayerValues::COLUMN_WILD_UP],
        };
        $this->players->addToken($player, $column, $count);
        $this->notices[] = Notification::tokensChanged(
            $player,
            ($this->nameOf)($player),
            [$key => $count],
            [$key => $this->tokenValue($player, $column)],
            'EFFECT',
            null,
            Notification::tokenName($key),
            $count,
        );
    }

    public function takeSpiritLight(int $player): bool
    {
        $spirit = $this->players->spirit($player);

        return $spirit !== null && $this->spirits->takeLight($spirit);
    }

    public function discardFromHand(int $player, array $cardIds): void
    {
        if ($cardIds === []) {
            return;
        }
        $refs = [];
        foreach ($cardIds as $cardId) {
            $refs[] = $this->ref($cardId);
            $this->memory->move($cardId, MemoryLocation::discard($this->levelOfCard($cardId)));
        }
        $this->notices[] = Notification::cardsDiscarded(
            $player,
            ($this->nameOf)($player),
            $refs,
            'EFFECT',
            $this->pileCounts(),
            $this->memory->count(MemoryLocation::Hand, $player),
        );
    }

    public function swapHandAndField(int $player, array $handIds, array $fieldIds): void
    {
        $slots = [];
        foreach ($fieldIds as $fieldId) {
            $card = $this->memory->find($fieldId) ?? throw new \LogicException("unknown card $fieldId");
            $slots[] = $card->locationArg;
            $this->memory->move($fieldId, MemoryLocation::Hand, $player);
        }
        $pairs = [];
        foreach ($handIds as $i => $handId) {
            $this->memory->move($handId, MemoryLocation::Open, $slots[$i]);
            $pairs[] = ['out' => $this->ref($handId), 'in' => $this->ref($fieldIds[$i]), 'slot' => $slots[$i]];
        }
        $this->notices[] = Notification::cardsExchanged(
            $player,
            ($this->nameOf)($player),
            'HAND_OPEN',
            $pairs,
            $this->memory->count(MemoryLocation::Hand, $player),
        );
    }

    public function swapComposedAndHand(int $player, int $tableauCardId, int $handCardId): void
    {
        $under = array_map(fn (MemoryCard $c): array => $this->ref($c->id), $this->memory->inLoc(MemoryLocation::UnderCard, $tableauCardId));
        $this->memory->swapTableauHand($tableauCardId, $handCardId, fn (int $type): int => PublicView::definition($type)->level);
        $this->notices[] = Notification::cardsExchanged(
            $player,
            ($this->nameOf)($player),
            'TABLEAU_HAND',
            [['out' => $this->ref($tableauCardId), 'in' => $this->ref($handCardId)]],
            $this->memory->count(MemoryLocation::Hand, $player),
        );
        if ($under !== []) {
            // 親が場を離れたので、I08 の下のカードは各レベルの捨札へ（全員に公開。007 N15 `UNDER_RELEASED`）
            $this->notices[] = Notification::cardsDiscarded(
                $player,
                ($this->nameOf)($player),
                $under,
                'UNDER_RELEASED',
                $this->pileCounts(),
                $this->memory->count(MemoryLocation::Hand, $player),
            );
        }
        ($this->victoryCheck)();
    }

    public function setTurnComposeDiscount(int $count): void
    {
        $context = $this->globals->turnContext();
        $context->turnComposeDiscount = $count;
        $this->globals->setTurnContext($context);
        $this->notifyDiscount($context);
    }

    public function addNextComposeDiscount(int $count): void
    {
        $context = $this->globals->turnContext();
        $context->nextComposeDiscount += $count;
        $this->globals->setTurnContext($context);
        $this->notifyDiscount($context);
    }

    public function setFloorAlternatives(array $alternatives): void
    {
        $context = $this->globals->turnContext();
        $context->floorAlternatives = array_map(
            static fn (array $alt): array => ['kind' => $alt['kind']->value, 'discount' => $alt['discount']],
            $alternatives,
        );
        $this->globals->setTurnContext($context);
        $turn = $this->globals->turnPlayer();
        $this->notices[] = Notification::floorAlternativeRecorded(
            $turn,
            ($this->nameOf)($turn),
            array_map(static fn (array $alt): array => ['kind' => $alt['kind'], 'discount' => (int) $alt['discount']], $context->floorAlternatives),
        );
    }

    public function takeRoomCard(int $player, int $position): ?int
    {
        $cards = $this->memory->inLoc(MemoryLocation::Room, $position);
        if ($cards === []) {
            return null;
        }
        $card = $cards[0];
        $this->memory->move($card->id, MemoryLocation::Hand, $player);
        $definition = PublicView::definition($card->type);
        $this->notices[] = Notification::roomCardAcquired(
            $player,
            ($this->nameOf)($player),
            $position,
            Notification::cardRef($card->id, $card->type),
            $definition->nameEn,
            $this->memory->count(MemoryLocation::Hand, $player),
        );

        return $card->id;
    }

    public function markOnceUsed(int $player, string $specId): void
    {
        $context = $this->globals->turnContext();
        $context->markOnceUsed($player, $specId);
        $this->globals->setTurnContext($context);
    }

    public function checkVictory(): void
    {
        ($this->victoryCheck)();
    }

    public function movePersona(int $player, int $to, bool $entry): void
    {
        $from = $this->players->persona($player);
        $this->players->setPersona($player, $to);
        $context = $this->globals->turnContext();
        if ($entry) {
            $context->recordEntry($to, RoomRepository::levelOfPosition($to) === 2);
            $this->globals->setTurnContext($context);
        }
        $this->notices[] = Notification::personaMoved(
            $player,
            ($this->nameOf)($player),
            $from,
            $to,
            FloorCards::get(sprintf('F%02d', $this->rooms->floorAt($to)))->roomName,
            'RECALL',
            $entry,
        );
        if ($entry && count($context->enteredRooms) >= RoomRepository::ROOM_COUNT) {
            $this->win($player, 'INSTANT_MEMORY');
        }
    }

    public function grantFloorBonus(int $player, FloorBonus $bonus): void
    {
        [$key, $column] = match ($bonus) {
            FloorBonus::NONE => [null, null],
            FloorBonus::LIGHT => ['light', PlayerValues::COLUMN_LIGHT],
            FloorBonus::WILD_MOVE_KEY => ['wild_up', PlayerValues::COLUMN_WILD_UP],
            FloorBonus::COMPOSE_TOKEN => ['compose', PlayerValues::COLUMN_COMPOSE],
            FloorBonus::ANALYSIS_TOKEN => ['analysis', PlayerValues::COLUMN_ANALYSIS],
        };
        if ($key === null || $column === null) {
            return;
        }
        $this->players->addToken($player, $column, 1);
        if ($bonus === FloorBonus::LIGHT) {
            $context = $this->globals->turnContext();
            $context->lightsGained++;
            $this->globals->setTurnContext($context);
        }
        $this->notices[] = Notification::tokensChanged(
            $player,
            ($this->nameOf)($player),
            [$key => 1],
            [$key => $this->tokenValue($player, $column)],
            'TOUR_BONUS',
            $bonus === FloorBonus::LIGHT ? 'TOUR' : null,
            Notification::tokenName($key),
            1,
        );
    }

    public function advanceSpirit(int $player, string $spiritId, string $via): bool
    {
        $result = $this->arrival->arrive($player, $spiritId, $via);
        array_push($this->notices, ...$result->notices);

        return $result->hadLight;
    }

    public function obtainEnhancedEgo(int $player, string $egoId): void
    {
        $this->egos->obtainEnhancement($egoId, $player);
        $this->notices[] = Notification::enhancedEgoObtained(
            $player,
            ($this->nameOf)($player),
            $egoId,
            EgoCards::get($egoId)->nameEn,
            $this->egos->count(EgoRepository::ENH_DECK),
        );
    }

    public function win(int $player, string $reason): void
    {
        if ($this->globals->endReason() !== null) {
            return;
        }
        $this->globals->setEndReason($reason);
        $this->globals->setWinnerId($player);
    }

    public function pushRecallActivation(int $player, string $cardId, int $times, array $groups): void
    {
        $this->stack->pushFront([new EffectFrame(
            EffectFrame::KIND_RECALL_ACTIVATION,
            $player,
            source: $cardId,
            params: [
                'times' => $times,
                'nth' => 1,
                'groups' => array_map(static fn (RecallGroup $g): string => $g->value, $groups),
            ],
        )]);
    }

    public function warpPersona(int $player, int $to): void
    {
        $from = $this->players->persona($player);
        $this->players->setPersona($player, $to);
        $this->notices[] = Notification::personaMoved(
            $player,
            ($this->nameOf)($player),
            $from,
            $to,
            $this->roomName($to),
            'WARP',
            false,
        );
    }

    public function swapRoomCards(int $player, int $posA, int $posB): void
    {
        $this->memory->swapRoomCards($posA, $posB);
        $after = [];
        foreach ([$posA, $posB] as $pos) {
            $card = $this->memory->inLoc(MemoryLocation::Room, $pos)[0] ?? throw new \LogicException("room $pos has no card");
            $after[] = ['pos' => $pos, 'card_id' => $card->id, 'back' => PublicView::backKey(PublicView::definition($card->type))];
        }
        $this->notices[] = Notification::roomsSwapped(
            $player,
            ($this->nameOf)($player),
            'CARD',
            $posA,
            $posB,
            $this->roomName($posA),
            $this->roomName($posB),
            $after,
        );
    }

    public function swapRoomFloors(int $player, int $posA, int $posB): void
    {
        $this->rooms->swapFloors($posA, $posB);
        $this->notices[] = Notification::roomsSwapped(
            $player,
            ($this->nameOf)($player),
            'FLOOR',
            $posA,
            $posB,
            $this->roomName($posA),
            $this->roomName($posB),
            [['pos' => $posA, 'floor_id' => $this->rooms->floorAt($posA)], ['pos' => $posB, 'floor_id' => $this->rooms->floorAt($posB)]],
        );
    }

    public function swapHandAndRoom(int $player, int $handCardId, int $position): void
    {
        $room = $this->memory->inLoc(MemoryLocation::Room, $position)[0] ?? throw new \LogicException("room $position has no card");
        $roomRef = $this->ref($room->id);
        $handRef = $this->ref($handCardId);
        $this->memory->move($room->id, MemoryLocation::Hand, $player);
        $this->memory->move($handCardId, MemoryLocation::Room, $position);
        $this->notices[] = Notification::roomCardSwappedWithHand(
            $player,
            ($this->nameOf)($player),
            $position,
            $this->roomName($position),
            $roomRef,
            $handRef,
            PublicView::backKey(PublicView::definition($handRef['type'])),
        );
    }

    public function placeUnderFloor(int $player, array $cardIds): void
    {
        $pos = $this->players->persona($player);
        foreach ($cardIds as $cardId) {
            $ref = $this->ref($cardId);
            $this->memory->move($cardId, MemoryLocation::UnderFloor, $pos);
            $this->notices[] = Notification::cardsPlacedUnderFloor(
                $player,
                ($this->nameOf)($player),
                $pos,
                $this->roomName($pos),
                [$ref],
                $this->memory->count(MemoryLocation::Hand, $player),
            );
            $pos = Corridor::previous($pos);
        }
    }

    public function collectUnderFloor(int $player, int $position): void
    {
        $refs = [];
        foreach ($this->memory->inLoc(MemoryLocation::UnderFloor, $position) as $card) {
            $refs[] = $this->ref($card->id);
            $this->memory->move($card->id, MemoryLocation::Hand, $player);
        }
        if ($refs !== []) {
            $this->notices[] = Notification::cardsGainedFromFloor(
                $player,
                ($this->nameOf)($player),
                $position,
                $this->roomName($position),
                $refs,
                $this->memory->count(MemoryLocation::Hand, $player),
            );
        }
    }

    public function isGameOver(): bool
    {
        return $this->globals->endReason() !== null;
    }

    /**
     * @return array{id: int, type: int}
     */
    private function ref(int $cardId): array
    {
        $card = $this->memory->find($cardId) ?? throw new \LogicException("unknown card $cardId");

        return Notification::cardRef($card->id, $card->type);
    }

    private function roomName(int $pos): string
    {
        return FloorCards::get(sprintf('F%02d', $this->rooms->floorAt($pos)))->roomName;
    }

    private function levelOfCard(int $cardId): int
    {
        $card = $this->memory->find($cardId) ?? throw new \LogicException("unknown card $cardId");

        return PublicView::definition($card->type)->level;
    }

    /**
     * @return array{1: int, 2: int}
     */
    private function pileCounts(): array
    {
        return [1 => $this->memory->count(MemoryLocation::Discard1), 2 => $this->memory->count(MemoryLocation::Discard2)];
    }

    /**
     * 山札の同期（007 §10.1。枚数と一番上の裏面）。
     *
     * @return array<int, array{count: int, top: ?string}>
     */
    public function decks(): array
    {
        $out = [];
        foreach ([1, 2] as $level) {
            $type = $this->memory->topType($level);
            $out[$level] = [
                'count' => $this->memory->count(MemoryLocation::deck($level)),
                'top' => $type === null ? null : PublicView::backKey(PublicView::definition($type)),
            ];
        }

        return $out;
    }

    private function tokenValue(int $player, string $column): int
    {
        return match ($column) {
            PlayerValues::COLUMN_LIGHT => $this->players->light($player),
            PlayerValues::COLUMN_WILD_UP => $this->players->wildUp($player),
            PlayerValues::COLUMN_COMPOSE => $this->players->composeTokens($player),
            PlayerValues::COLUMN_ANALYSIS => $this->players->analysisTokens($player),
            default => throw new \InvalidArgumentException("unknown token column: $column"),
        };
    }

    private function notifyDiscount(TurnContext $context): void
    {
        $turn = $this->globals->turnPlayer();
        $this->notices[] = Notification::composeDiscountChanged(
            $turn,
            ($this->nameOf)($turn),
            $context->turnComposeDiscount,
            $context->nextComposeDiscount,
        );
    }
}
