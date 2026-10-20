<?php

declare(strict_types=1);

namespace IRevolveTests\Support;

use Bga\Games\IRevolveThereforeIAm\Material\ActionContext;
use Bga\Games\IRevolveThereforeIAm\Material\Condition;
use Bga\Games\IRevolveThereforeIAm\Material\ConditionRef;
use Bga\Games\IRevolveThereforeIAm\Material\EffectKind;
use Bga\Games\IRevolveThereforeIAm\Material\EffectLimit;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\Modifier;
use Bga\Games\IRevolveThereforeIAm\Material\OwnerScope;

/**
 * 修飾（`MODIFIER`）の問い合わせの検査に使う、カード効果の定義（008 §7.2・§7.6 の表の写し）。
 * 想い出カード 42・自我カード 13 の本番の定義は後続のタスクで `EffectCatalog` に入る。そのときこの写しは本番の定義に置き換える。
 */
final class TestSpecs
{
    /**
     * @param array<string, mixed> $params
     * @param list<ConditionRef> $conditions
     */
    private static function modifier(
        string $source,
        EffectKind $kind,
        Modifier $modifier,
        array $params = [],
        array $conditions = [],
        EffectLimit $limit = EffectLimit::PER_EVENT,
    ): EffectSpec {
        return new EffectSpec(
            id: $source . '.1',
            source: $source,
            mechanism: Mechanism::MODIFIER,
            kind: $kind,
            ownerScope: OwnerScope::OWNER,
            modifier: $modifier,
            conditions: $conditions,
            limit: $limit,
            params: $params,
        );
    }

    /**
     * @return list<EffectSpec>
     */
    public static function all(): array
    {
        $table = [1, 1, 2, 2, 3];
        $granted = ['key' => KeyType::WILD, 'src' => 'EFFECT'];
        $firstLv2 = [ConditionRef::of(Condition::FIRST_LV2_ENTRY_THIS_TURN)];

        return [
            // MD-01 COMPOSE_ENERGY_DELTA
            self::modifier('M060', EffectKind::CONSTANT, Modifier::COMPOSE_ENERGY_DELTA, ['delta' => -1], [
                ConditionRef::of(Condition::COMPOSED_CARD_LEVEL, ['level' => 2]),
            ]),
            self::modifier('M077', EffectKind::CONSTANT, Modifier::COMPOSE_ENERGY_DELTA, ['delta' => -1], [
                ConditionRef::of(Condition::COMPOSED_CARD_HAS_KEY, ['type' => KeyType::EMOTION]),
            ]),
            self::modifier('M068', EffectKind::ACTIVATED, Modifier::COMPOSE_ENERGY_DELTA, ['delta' => -1], [
                ConditionRef::of(Condition::ROOMS_ENTERED_GE, ['n' => 4]),
                ConditionRef::of(Condition::FIRST_COMPOSE_ACTION_THIS_TURN),
            ]),
            self::modifier('E01', EffectKind::CONSTANT, Modifier::COMPOSE_ENERGY_DELTA, ['delta' => -1]),
            // MD-02 HELD_ENERGY_DELTA
            self::modifier('M098', EffectKind::HELD, Modifier::HELD_ENERGY_DELTA, ['key' => KeyType::EMOTION, 'per' => 1]),
            // MD-03 ANALYSIS_COST_DELTA
            self::modifier('E02', EffectKind::CONSTANT, Modifier::ANALYSIS_COST_DELTA, ['delta' => -1]),
            // MD-04 LIGHT_SCHEDULE
            self::modifier('M059', EffectKind::CONSTANT, Modifier::LIGHT_SCHEDULE, [
                'context' => ActionContext::ANALYSIS,
                'table' => $table,
            ]),
            self::modifier('M063', EffectKind::CONSTANT, Modifier::LIGHT_SCHEDULE, [
                'context' => ActionContext::MOVE,
                'table' => $table,
            ]),
            self::modifier('M070', EffectKind::CONSTANT, Modifier::LIGHT_SCHEDULE, [
                'context' => ActionContext::COMPOSE,
                'table' => $table,
            ]),
            // MD-05 KEY_VIEW
            self::modifier('M094', EffectKind::CONSTANT, Modifier::KEY_VIEW, [
                'from' => KeyType::WISDOM,
                'to' => KeyType::WILD,
                'context' => ActionContext::ANALYSIS,
            ]),
            self::modifier('M096', EffectKind::CONSTANT, Modifier::KEY_VIEW, [
                'from' => KeyType::WILL,
                'to' => KeyType::WILD,
                'context' => ActionContext::MOVE,
            ]),
            // MD-06 MOVE_PAYMENT_GRANT
            self::modifier('M064', EffectKind::ACTIVATED, Modifier::MOVE_PAYMENT_GRANT, $granted, $firstLv2, EffectLimit::ONCE_PER_TURN),
            self::modifier('M065', EffectKind::ACTIVATED, Modifier::MOVE_PAYMENT_GRANT, $granted, $firstLv2, EffectLimit::ONCE_PER_TURN),
            self::modifier('M066', EffectKind::ACTIVATED, Modifier::MOVE_PAYMENT_GRANT, $granted, $firstLv2, EffectLimit::ONCE_PER_TURN),
            // MD-07 TAP_FOR_ENERGY
            self::modifier('M095', EffectKind::CONSTANT, Modifier::TAP_FOR_ENERGY, ['key' => KeyType::EMOTION]),
            // MD-09 ARCHITECT_SUBSTITUTE
            self::modifier('I03', EffectKind::ACTIVATED, Modifier::ARCHITECT_SUBSTITUTE, ['plus' => 2]),
            // MD-11 RECALL_TIMES
            self::modifier('M062', EffectKind::ACTIVATED, Modifier::RECALL_TIMES, ['times' => 2], [
                ConditionRef::of(Condition::OTHER_PERSONA_IN_ROOM),
            ]),
        ];
    }
}
