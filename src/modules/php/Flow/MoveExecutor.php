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
use Bga\Games\IRevolveThereforeIAm\Data\TurnContext;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;

/**
 * 検証済みの移動（`MoveOutcome`）を DB に適用する（仕様書 006 §5.1 の 4、008 §6.2 の 5）。
 * 部屋を 1 つずつ順に、スキップした部屋の周遊ボーナス → 支払い → 移動と入室の記録 → 入室した部屋の周遊ボーナス → 勝利判定。
 * 12 部屋目に入室した時点で刹那の追憶が成立し、残りの経路は適用しない（Q-09）。
 */
final class MoveExecutor
{
    public function __construct(
        private readonly MemoryCardRepository $memoryCards,
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
    ) {
    }

    public function apply(MoveBoard $board, MoveOutcome $outcome, string $playerName): MoveResult
    {
        $player = $board->player;
        $ctx = $this->globals->turnContext();
        $notices = [];

        if (!$outcome->moved()) {
            $room = $board->room($board->position);
            $notices[] = Notification::moveBlocked($player, $playerName, $room->pos, $room->name);
            $this->globals->setTurnContext($ctx);

            return new MoveResult($notices, false, $this->endEvents($board, $outcome));
        }

        $ended = false;
        foreach ($outcome->steps as $index => $step) {
            if ($step->skipped !== null) {
                $skipped = $board->room($step->skipped);
                $ctx->skippedRooms = array_values(array_unique([...$ctx->skippedRooms, $step->skipped]));
                $ctx->moveSteps++;
                $notices[] = Notification::roomSkipped($player, $playerName, $skipped->pos, $skipped->name);
                array_push($notices, ...$this->grantBonus($player, $playerName, $step->skipBonus, $ctx));
            }

            $paymentInfo = $this->pay($player, $playerName, $step, $ctx, $notices);

            $this->players->setPersona($player, $step->pos);
            $room = $board->room($step->pos);
            $ctx->recordEntry($step->pos, $step->level === 2);
            $ctx->moveSteps++;
            $notices[] = Notification::roomEntered(
                $player,
                $playerName,
                $step->from,
                $step->pos,
                $room->name,
                $index + 1,
                $paymentInfo,
                $ctx->roomsEnteredCount,
            );
            array_push($notices, ...$this->grantBonus($player, $playerName, $step->entryBonus, $ctx));

            if (count($ctx->enteredRooms) >= RoomRepository::ROOM_COUNT) {
                $this->globals->setEndReason('INSTANT_MEMORY');
                $this->globals->setWinnerId($player);
                $ended = true;
                break;
            }
        }

        $ctx->moveSummary = [
            'key_kinds_paid' => array_values(array_unique([
                ...$ctx->moveSummary['key_kinds_paid'],
                ...array_map(static fn(KeyType $k): string => $k->value, $outcome->keyKinds),
            ])),
            'wild_tokens_used' => $ctx->moveSummary['wild_tokens_used'] + $outcome->wildTokensUsed,
            'light_spent' => $ctx->moveSummary['light_spent'] + $outcome->lightUses,
        ];
        $this->globals->setTurnContext($ctx);

        return new MoveResult($notices, $ended, $ended ? [] : $this->endEvents($board, $outcome));
    }

    /**
     * 支払いを確定する（横向き・捨札・心光・万能移動鍵トークン）。
     *
     * @param list<Notice> $notices
     * @return array{tapped: list<int>, discarded: list<array{id: int, type: int}>, light: int, wild_tokens: int, lock_reduction: int}
     */
    private function pay(int $player, string $playerName, MoveStep $step, TurnContext $ctx, array &$notices): array
    {
        if ($step->tapped !== []) {
            $this->memoryCards->setTapped($step->tapped, true);
            $notices[] = Notification::cardsTapped($player, $playerName, $step->tapped, true);
        }

        $discarded = [];
        foreach ($step->discarded as $cardId) {
            $card = $this->memoryCards->find($cardId) ?? throw new \LogicException("unknown card $cardId");
            $this->memoryCards->move($cardId, MemoryLocation::discard(PublicView::definition($card->type)->level));
            $discarded[] = ['id' => $cardId, 'type' => $card->type];
        }
        if ($discarded !== []) {
            $notices[] = Notification::cardsDiscarded(
                $player,
                $playerName,
                $discarded,
                'COST',
                [
                    1 => $this->memoryCards->count(MemoryLocation::Discard1),
                    2 => $this->memoryCards->count(MemoryLocation::Discard2),
                ],
                $this->memoryCards->count(MemoryLocation::Hand, $player),
            );
        }

        $delta = [];
        if ($step->lightsPaid > 0) {
            $this->players->addToken($player, PlayerValues::COLUMN_LIGHT, -$step->lightsPaid);
            $delta['light'] = -$step->lightsPaid;
        }
        if ($step->tokens > 0) {
            $this->players->useWild($player, $step->tokens);
            $delta['wild_up'] = -$step->tokens;
            $delta['wild_used'] = $step->tokens;
        }
        if ($delta !== []) {
            $notices[] = Notification::tokensChanged($player, $playerName, $delta, $this->totals($player, array_keys($delta)), 'PAYMENT');
        }

        return [
            'tapped' => $step->tapped,
            'discarded' => $discarded,
            'light' => $step->lightsPaid,
            'wild_tokens' => $step->tokens,
            'lock_reduction' => $step->lockReduction,
        ];
    }

    /**
     * 周遊ボーナス（002 §7、008 §7.4。`origin = TOUR`）を与える。
     *
     * @return list<Notice>
     */
    private function grantBonus(int $player, string $playerName, FloorBonus $bonus, TurnContext $ctx): array
    {
        [$key, $column] = match ($bonus) {
            FloorBonus::NONE => [null, null],
            FloorBonus::LIGHT => ['light', PlayerValues::COLUMN_LIGHT],
            FloorBonus::WILD_MOVE_KEY => ['wild_up', PlayerValues::COLUMN_WILD_UP],
            FloorBonus::COMPOSE_TOKEN => ['compose', PlayerValues::COLUMN_COMPOSE],
            FloorBonus::ANALYSIS_TOKEN => ['analysis', PlayerValues::COLUMN_ANALYSIS],
        };
        if ($key === null || $column === null) {
            return [];
        }
        $this->players->addToken($player, $column, 1);
        if ($bonus === FloorBonus::LIGHT) {
            $ctx->lightsGained++;
        }

        return [Notification::tokensChanged(
            $player,
            $playerName,
            [$key => 1],
            $this->totals($player, [$key]),
            'TOUR_BONUS',
            $bonus === FloorBonus::LIGHT ? 'TOUR' : null,
            Notification::tokenName($key),
            1,
        )];
    }

    /**
     * @param list<string> $keys
     * @return array<string, int>
     */
    private function totals(int $player, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = match ($key) {
                'light' => $this->players->light($player),
                'wild_up' => $this->players->wildUp($player),
                'wild_used' => $this->players->wildUsed($player),
                'compose' => $this->players->composeTokens($player),
                'analysis' => $this->players->analysisTokens($player),
                default => throw new \InvalidArgumentException("unknown token key: $key"),
            };
        }

        return $out;
    }

    /**
     * 移動で起きたイベント（008 §4.1。誘発の収集は I-09）。EV-04 は経路の途中の入室のうち他の人格がいる部屋と、
     * スキップした四隅（レベル 2）の部屋のうち他の人格がいる部屋（Q-71。スキップしても「通過」）、
     * EV-02 は移動の終了（移動しなかったときも）、EV-03 は最後に止まった部屋。
     *
     * @return list<GameEvent>
     */
    private function endEvents(MoveBoard $board, MoveOutcome $outcome): array
    {
        $events = [];
        $last = count($outcome->steps) - 1;
        foreach ($outcome->steps as $index => $step) {
            if ($step->skipped !== null && $board->room($step->skipped)->othersPresent) {
                $events[] = new GameEvent(Event::PERSONA_PASSED_OTHER, $board->player, ['room' => $step->skipped]);
            }
            if ($index !== $last && $board->room($step->pos)->othersPresent) {
                $events[] = new GameEvent(Event::PERSONA_PASSED_OTHER, $board->player, ['room' => $step->pos]);
            }
        }
        $stopPos = $last >= 0 ? $outcome->steps[$last]->pos : $board->position;
        $others = $board->room($stopPos)->othersPresent;
        $events[] = new GameEvent(Event::MOVE_ENDED, $board->player, [
            'rooms_entered' => count($outcome->steps),
            'wild_tokens_used' => $outcome->wildTokensUsed,
            'key_kinds' => implode(',', array_map(static fn(KeyType $k): string => $k->value, $outcome->keyKinds)),
            'room' => $stopPos,
            'other_persona' => $others,
        ]);
        if ($last >= 0) {
            $events[] = new GameEvent(Event::PERSONA_STOPPED, $board->player, [
                'room' => $stopPos,
                'via' => 'MOVE',
                'other_persona' => $others,
            ]);
        }

        return $events;
    }
}
