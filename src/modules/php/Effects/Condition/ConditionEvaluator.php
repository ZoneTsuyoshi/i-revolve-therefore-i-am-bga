<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Condition;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Material\Condition;
use Bga\Games\IRevolveThereforeIAm\Material\ConditionRef;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\Origin;

/**
 * 条件 CD-01〜CD-13 の評価（008 §5.2）。状態を変えない。
 */
final class ConditionEvaluator
{
    public function __construct(private readonly GameView $view)
    {
    }

    /**
     * 条件の AND。空なら真。
     *
     * @param list<ConditionRef> $conditions
     */
    public function all(array $conditions, ConditionContext $context): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->evaluate($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    public function evaluate(ConditionRef $ref, ConditionContext $context): bool
    {
        $params = $ref->params;
        $payload = $context->payload;

        return match ($ref->condition) {
            Condition::ROOMS_ENTERED_GE => $this->view->roomsEnteredCount() >= $this->intParam($params, 'n'),
            Condition::ALL_KEY_KINDS_PAID => $this->allKeyKindsPaid(),
            Condition::NO_WILD_TOKEN_USED => $this->view->moveWildTokensUsed() === 0,
            Condition::OTHER_PERSONA_IN_ROOM => $this->otherPersonaInRoom($payload),
            Condition::FIRST_LV2_ENTRY_THIS_TURN => ($payload['entering_level'] ?? null) === 2
                && $this->view->lv2EntriesCount() === 0,
            Condition::COMPOSED_CARD_HAS_KEY => $this->composedCardHasKey($context, $params),
            Condition::COMPOSED_COUNT_IN => in_array($payload['composed_count'] ?? null, $params['set'] ?? [], true),
            Condition::TIER_UP_VIA => ($payload['via'] ?? null) === ($params['via'] ?? null),
            Condition::DEST_HAD_NO_LIGHT => ($payload['dest_had_light'] ?? null) === false,
            Condition::LIGHT_ORIGIN => $this->lightOrigin($payload, $params),
            Condition::FLOOR_ELEMENT => ($payload['element'] ?? null) === ($params['type'] ?? null),
            Condition::FIRST_COMPOSE_ACTION_THIS_TURN => $this->view->composeActionsDone() === 0,
            Condition::COMPOSED_CARD_LEVEL => $context->card !== null
                && $context->card->level === $this->intParam($params, 'level'),
        };
    }

    /** CD-02: 知恵・意志・感情の 3 種類全てを使った（印刷された鍵の種類。万能は数えない） */
    private function allKeyKindsPaid(): bool
    {
        $kinds = $this->view->moveKeyKindsPaid();

        return in_array(KeyType::WISDOM, $kinds, true)
            && in_array(KeyType::WILL, $kinds, true)
            && in_array(KeyType::EMOTION, $kinds, true);
    }

    /**
     * CD-04: 判定する部屋に、ターンプレイヤー以外の人格がいる。
     *
     * @param array<string, mixed> $payload
     */
    private function otherPersonaInRoom(array $payload): bool
    {
        $room = $payload['room'] ?? $this->view->recallRoomPosition();
        if (!is_int($room)) {
            return false;
        }
        foreach ($this->view->playersAt($room) as $playerId) {
            if ($playerId !== $this->view->turnPlayer()) {
                return true;
            }
        }

        return false;
    }

    /**
     * CD-06: 自己構成するカードが `type` の鍵を 1 つ以上持つ（2 本あっても 1 回と数える）。
     *
     * @param array<string, mixed> $params
     */
    private function composedCardHasKey(ConditionContext $context, array $params): bool
    {
        $type = $params['type'] ?? null;

        return $context->card !== null && $type instanceof KeyType && in_array($type, $context->card->keys, true);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $params
     */
    private function lightOrigin(array $payload, array $params): bool
    {
        $expected = $params['origin'] ?? null;
        $actual = $payload['origin'] ?? null;
        if ($actual instanceof Origin) {
            $actual = $actual->value;
        }

        return $expected instanceof Origin && $actual === $expected->value;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function intParam(array $params, string $key): int
    {
        $value = $params[$key] ?? 0;

        return is_int($value) ? $value : 0;
    }
}
