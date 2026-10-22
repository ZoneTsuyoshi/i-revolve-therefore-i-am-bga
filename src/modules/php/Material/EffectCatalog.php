<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 効果 ID → `EffectSpec` の対応表（仕様書 008 §2.1、§11.1）。手書きの値オブジェクトで、外部の JSON は持たない。
 * 002 の生成物（`MemoryCards` など）とは別ファイル。
 *
 * 現在の収録: 想起効果 13（RC-01〜RC-13。§7.3）、床アクション 12（F01〜F12。§7.4）、
 * 周遊ボーナス 4（F01.TOUR など。§7.4）、精神カードのボーナス 9（R1A〜R5。§7.5）、
 * 想い出カードの効果 42（M052.1 など。§7.2）、初期自我 8・強化自我 5（I01.1・I04.1・I04.2・E01.1 など。§7.6）。
 * 目標カード 7（O001〜O007。`OBJECTIVE`。§7.7）。
 */
final class EffectCatalog
{
    /** 周遊ボーナスの効果 ID の接尾辞（`F01.TOUR`）。 */
    public const TOUR_SUFFIX = '.TOUR';

    /** @var array<string, EffectSpec>|null */
    private static ?array $all = null;

    /** @var array<string, list<EffectSpec>>|null 出典のカード ID → 効果（定義順） */
    private static ?array $bySource = null;

    /**
     * @return array<string, EffectSpec> 効果 ID をキーとする定義（定義順）
     */
    public static function all(): array
    {
        if (self::$all === null) {
            $all = [];
            foreach (
                [
                ...self::recallSpecs(),
                ...self::floorSpecs(),
                ...self::tourSpecs(),
                ...self::spiritSpecs(),
                ...self::memorySpecs(),
                ...self::egoSpecs(),
                ...self::objectiveSpecs(),
                ] as $spec
            ) {
                if (isset($all[$spec->id])) {
                    throw new \LogicException("Duplicate effect id: {$spec->id}");
                }
                $all[$spec->id] = $spec;
            }
            self::$all = $all;
        }

        return self::$all;
    }

    /**
     * @throws \OutOfBoundsException 未知の効果 ID
     */
    public static function get(string $effectId): EffectSpec
    {
        return self::all()[$effectId] ?? throw new \OutOfBoundsException("Unknown effect id: {$effectId}");
    }

    public static function find(string $effectId): ?EffectSpec
    {
        return self::all()[$effectId] ?? null;
    }

    /**
     * 002 のカード ID（想い出 `M058`、床 `F01`、精神 `R1A`、自我 `I01`、目標 `O001`）から効果を引く。効果が無ければ空。
     * 床 ID には床アクションと周遊ボーナスの両方が返る。想起効果は表向きのカードの ID を持たないので、`recall()` で引く。
     *
     * @return list<EffectSpec>
     */
    public static function forCard(string $cardId): array
    {
        if (self::$bySource === null) {
            $bySource = [];
            foreach (self::all() as $spec) {
                if ($spec->source !== '') {
                    $bySource[$spec->source][] = $spec;
                }
            }
            self::$bySource = $bySource;
        }

        return self::$bySource[$cardId] ?? [];
    }

    /** 想起効果の識別子（002 §3.4）に対応する RC-NN の効果 ID。 */
    public static function recallId(RecallEffect $recall): string
    {
        return match ($recall) {
            RecallEffect::DRAW_1 => 'RC-01',
            RecallEffect::OPEN_DRAW_1 => 'RC-02',
            RecallEffect::LIGHT_1 => 'RC-03',
            RecallEffect::NEXT_COMPOSE_DISCOUNT_1 => 'RC-04',
            RecallEffect::DISCARD_FOR_LIGHT => 'RC-05',
            RecallEffect::SWAP_HAND_FIELD => 'RC-06',
            RecallEffect::SWAP_SAME_COST => 'RC-07',
            RecallEffect::ALT_ANALYSIS => 'RC-08',
            RecallEffect::ALT_COMPOSE => 'RC-09',
            RecallEffect::ALT_ANALYSIS_DISCOUNT_1 => 'RC-10',
            RecallEffect::ALT_COMPOSE_DISCOUNT_1 => 'RC-11',
            RecallEffect::MOVE_NEXT_LV1 => 'RC-12',
            RecallEffect::MOVE_NEXT_LV2 => 'RC-13',
        };
    }

    public static function recall(RecallEffect $recall): EffectSpec
    {
        return self::get(self::recallId($recall));
    }

    /** 床アクション（`F01`〜`F12`）。 */
    public static function floorAction(string $floorId): EffectSpec
    {
        return self::get($floorId);
    }

    /** 周遊ボーナス。レベル 1 の床では null。 */
    public static function tourBonus(string $floorId): ?EffectSpec
    {
        return self::find($floorId . self::TOUR_SUFFIX);
    }

    /** 精神カードのボーナス（`R1A`〜`R5`）。 */
    public static function spiritBonus(string $spiritId): EffectSpec
    {
        return self::get($spiritId);
    }

    // ---- 想起効果 13 種（008 §7.3） ----

    /**
     * @return list<EffectSpec>
     */
    private static function recallSpecs(): array
    {
        $recall = static fn (
            string $id,
            OwnerScope $scope,
            EffectNode $node,
        ): EffectSpec => new EffectSpec(
            id: $id,
            source: '',
            mechanism: Mechanism::ACTION,
            kind: EffectKind::NONE,
            ownerScope: $scope,
            ops: [$node],
            unit: true,
        );
        // 想起効果の操作は全て `origin = RECALL`（§6.6、§7.3 の末尾。ターンプレイヤー以外の発動でも同じ）
        $op = static fn (Op $op, array $params = []): EffectNode => EffectNode::op($op, [...$params, 'origin' => Origin::RECALL]);
        $alt = static fn (ActionKind $kind, int $discount): EffectNode => $op(
            Op::RECORD_FLOOR_ALTERNATIVE,
            ['kind' => $kind, 'discount' => $discount],
        );
        $move = static fn (int $level): EffectNode => EffectNode::optional(
            EffectNode::seq(
                $op(Op::MOVE_NEXT_ROOM, ['level' => $level]),
                $op(Op::GAIN_OPEN, ['n' => 1]),
            ),
            ChoiceKind::CONFIRM_MOVE,
        );

        return [
            $recall('RC-01', OwnerScope::ACTIVATOR, $op(Op::DRAW_DECK, ['n' => 1])),
            $recall('RC-02', OwnerScope::ACTIVATOR, $op(Op::GAIN_OPEN, ['n' => 1])),
            $recall('RC-03', OwnerScope::ACTIVATOR, $op(Op::GAIN_LIGHT, ['n' => 1])),
            $recall('RC-04', OwnerScope::ACTIVATOR, $op(Op::ADD_NEXT_COMPOSE_DISCOUNT, ['n' => 1])),
            $recall('RC-05', OwnerScope::ACTIVATOR, $op(Op::DISCARD_FOR_LIGHT)),
            $recall('RC-06', OwnerScope::ACTIVATOR, $op(Op::SWAP_HAND_OPEN)),
            $recall(
                'RC-07',
                OwnerScope::ACTIVATOR,
                EffectNode::optional($op(Op::SWAP_COMPOSED_HAND), ChoiceKind::SELECT_SWAP_SAME_COST),
            ),
            $recall('RC-08', OwnerScope::TURN_PLAYER, $alt(ActionKind::ANALYSIS, 0)),
            $recall('RC-09', OwnerScope::TURN_PLAYER, $alt(ActionKind::COMPOSE, 0)),
            $recall('RC-10', OwnerScope::TURN_PLAYER, $alt(ActionKind::ANALYSIS, 1)),
            $recall('RC-11', OwnerScope::TURN_PLAYER, $alt(ActionKind::COMPOSE, 1)),
            $recall('RC-12', OwnerScope::TURN_PLAYER, $move(1)),
            $recall('RC-13', OwnerScope::TURN_PLAYER, $move(2)),
        ];
    }

    // ---- 床アクション 12 室（008 §7.4） ----

    /**
     * @return list<EffectSpec>
     */
    private static function floorSpecs(): array
    {
        $draw = static fn (int $n): EffectNode => EffectNode::op(Op::DRAW_DECK, ['n' => $n, 'origin' => Origin::FLOOR]);
        $open = static fn (int $n): EffectNode => EffectNode::op(Op::GAIN_OPEN, ['n' => $n, 'origin' => Origin::FLOOR]);
        $light = static fn (int $n): EffectNode => EffectNode::op(Op::GAIN_LIGHT, ['n' => $n, 'origin' => Origin::FLOOR]);
        $discard = static fn (int $n): EffectNode => EffectNode::op(Op::DISCARD_HAND, ['n' => $n]);
        $analyze = static fn (): EffectNode => EffectNode::optional(
            EffectNode::op(Op::ANALYZE, ['ctx' => 'FLOOR']),
        );

        $actions = [
            'F01' => $draw(3),
            'F02' => $analyze(),
            'F03' => EffectNode::seq($draw(3), $discard(1)),
            'F04' => EffectNode::seq(
                EffectNode::op(Op::SET_TURN_COMPOSE_DISCOUNT, ['n' => 1]),
                EffectNode::optional(EffectNode::op(Op::COMPOSE, ['ctx' => ComposeContext::FLOOR])),
            ),
            'F05' => EffectNode::seq($open(1), $light(1)),
            'F06' => $analyze(),
            'F07' => EffectNode::seq($open(1), $draw(1), $light(1)),
            'F08' => EffectNode::seq($draw(3), $discard(1)),
            'F09' => EffectNode::chooseOne($open(2), $light(2)),
            'F10' => EffectNode::seq($analyze(), $light(1)),
            'F11' => EffectNode::seq($open(1), $light(1)),
            'F12' => EffectNode::chooseOne(
                EffectNode::optional(
                    EffectNode::op(Op::COMPOSE, ['ctx' => ComposeContext::FLOOR, 'discount' => 1]),
                ),
                $draw(2),
            ),
        ];

        $specs = [];
        foreach ($actions as $id => $node) {
            $specs[] = new EffectSpec(
                id: $id,
                source: $id,
                mechanism: Mechanism::ACTION,
                kind: EffectKind::NONE,
                ownerScope: OwnerScope::TURN_PLAYER,
                ops: [$node],
                unit: true,
            );
        }

        return $specs;
    }

    /**
     * 周遊ボーナス 4 種（レベル 2 の床 F01・F04・F07・F10。手順の一部なので手札上限の単位は持たない）。
     *
     * @return list<EffectSpec>
     */
    private static function tourSpecs(): array
    {
        $bonuses = [
            'F01' => EffectNode::op(Op::GAIN_TOKEN, ['token' => TokenKind::ANALYSIS, 'n' => 1, 'origin' => Origin::TOUR]),
            'F04' => EffectNode::op(Op::GAIN_LIGHT, ['n' => 1, 'origin' => Origin::TOUR]),
            'F07' => EffectNode::op(Op::GAIN_TOKEN, ['token' => TokenKind::WILD_MOVE_KEY, 'n' => 1, 'origin' => Origin::TOUR]),
            'F10' => EffectNode::op(Op::GAIN_TOKEN, ['token' => TokenKind::COMPOSE, 'n' => 1, 'origin' => Origin::TOUR]),
        ];

        $specs = [];
        foreach ($bonuses as $floorId => $node) {
            $specs[] = new EffectSpec(
                id: $floorId . self::TOUR_SUFFIX,
                source: $floorId,
                mechanism: Mechanism::ACTION,
                kind: EffectKind::NONE,
                ownerScope: OwnerScope::TURN_PLAYER,
                ops: [$node],
            );
        }

        return $specs;
    }

    // ---- 精神カード 9 枚のボーナス（008 §7.5） ----

    /**
     * @return list<EffectSpec>
     */
    private static function spiritSpecs(): array
    {
        $draw = EffectNode::op(Op::DRAW_DECK, ['n' => 1, 'origin' => Origin::BONUS]);
        $ego = EffectNode::op(Op::GAIN_ENHANCED_EGO);
        $bonuses = [
            'R1A' => $draw,
            'R1B' => $draw,
            'R1C' => $draw,
            'R2' => EffectNode::optional(EffectNode::op(Op::COMPOSE, ['ctx' => ComposeContext::BONUS_LV1])),
            'R3A' => $ego,
            'R3B' => $ego,
            'R3C' => $ego,
            'R4' => EffectNode::optional(EffectNode::op(Op::COMPOSE, ['ctx' => ComposeContext::BONUS_ANY])),
            'R5' => EffectNode::op(Op::WIN, ['kind' => 'APEX_OF_PSYCHE']),
        ];

        $specs = [];
        foreach ($bonuses as $id => $node) {
            $specs[] = new EffectSpec(
                id: $id,
                source: $id,
                mechanism: Mechanism::ACTION,
                kind: EffectKind::NONE,
                ownerScope: OwnerScope::TURN_PLAYER,
                ops: [$node],
            );
        }

        return $specs;
    }
    // ---- 想い出カードの効果 42 種（008 §7.2） ----

    /**
     * @return list<EffectSpec>
     */
    private static function memorySpecs(): array
    {
        $effect = static fn (Op $op, array $params = []): EffectNode => EffectNode::op($op, [...$params, 'origin' => Origin::EFFECT]);
        $light = static fn (int|ValueExpr $n = 1): EffectNode => $effect(Op::GAIN_LIGHT, ['n' => $n]);
        $draw = static fn (int|ValueExpr $n = 1): EffectNode => $effect(Op::DRAW_DECK, ['n' => $n]);
        $open = static fn (int|ValueExpr $n = 1): EffectNode => $effect(Op::GAIN_OPEN, ['n' => $n]);
        $extra = static fn (Op $op): EffectNode => EffectNode::op($op, ['n' => 1, 'origin' => Origin::EXTRA]);
        $hasKey = static fn (KeyType $type): ConditionRef => ConditionRef::of(Condition::COMPOSED_CARD_HAS_KEY, ['type' => $type]);
        $fourRooms = static fn (): ConditionRef => ConditionRef::of(Condition::ROOMS_ENTERED_GE, ['n' => 4]);
        $schedule = [1, 1, 2, 2, 3];

        $trigger = static fn (
            string $card,
            EffectKind $kind,
            array $events,
            array $ops,
            array $conditions = [],
            bool $optional = false,
            EffectLimit $limit = EffectLimit::PER_EVENT,
            bool $unit = false,
        ): EffectSpec => new EffectSpec(
            id: $card . '.1',
            source: $card,
            mechanism: Mechanism::TRIGGER,
            kind: $kind,
            ownerScope: OwnerScope::OWNER,
            ops: $ops,
            events: $events,
            conditions: $conditions,
            optional: $optional,
            limit: $limit,
            unit: $unit,
        );
        $modifier = static fn (
            string $card,
            EffectKind $kind,
            Modifier $modifier,
            array $params = [],
            array $conditions = [],
            EffectLimit $limit = EffectLimit::PER_EVENT,
        ): EffectSpec => new EffectSpec(
            id: $card . '.1',
            source: $card,
            mechanism: Mechanism::MODIFIER,
            kind: $kind,
            ownerScope: OwnerScope::OWNER,
            modifier: $modifier,
            conditions: $conditions,
            limit: $limit,
            params: $params,
        );

        $swapCards = EffectNode::optional(EffectNode::op(Op::SWAP_ROOM_MEMORY), ChoiceKind::SWAP_ROOM_CARDS);
        $swapFloors = EffectNode::optional(EffectNode::op(Op::SWAP_ROOM_FLOOR), ChoiceKind::SWAP_ROOM_FLOORS);
        $turnStart = static fn (string $card, EffectNode $node): EffectSpec => $trigger(
            $card,
            EffectKind::TURN_START,
            [Event::TURN_START],
            [$node],
            optional: true,
            unit: true,
        );
        $lockReduction = ['reduction' => 1];
        $firstLv2 = [ConditionRef::of(Condition::FIRST_LV2_ENTRY_THIS_TURN)];
        $onceTurn = EffectLimit::ONCE_PER_TURN;
        $tierUp = [Event::TIER_UP];
        $composed = [Event::COMPOSED];
        $floorGain = [Event::FLOOR_GAIN];
        $element = static fn (string $type): array => [ConditionRef::of(Condition::FLOOR_ELEMENT, ['type' => $type])];
        $instant = EffectKind::INSTANT;
        $activated = EffectKind::ACTIVATED;
        $constant = EffectKind::CONSTANT;

        return [
            // ターン開始時（EV-01）。入れ替える部屋・ワープ先は選択で決める
            $turnStart('M052', $swapCards),
            $turnStart('M053', $swapCards),
            $turnStart('M054', $swapCards),
            $turnStart('M055', $swapFloors),
            $turnStart('M056', $swapFloors),
            $turnStart('M057', $swapFloors),
            $trigger('M058', $activated, $tierUp, [$light()], [
                ConditionRef::of(Condition::TIER_UP_VIA, ['via' => 'ACTION']),
                ConditionRef::of(Condition::DEST_HAD_NO_LIGHT),
            ]),
            $modifier('M059', $constant, Modifier::LIGHT_SCHEDULE, ['context' => ActionContext::ANALYSIS, 'table' => $schedule]),
            $modifier('M060', $constant, Modifier::COMPOSE_ENERGY_DELTA, ['delta' => -1], [
                ConditionRef::of(Condition::COMPOSED_CARD_LEVEL, ['level' => 2]),
            ]),
            $trigger('M061', $activated, [Event::MOVE_ENDED], [$draw()], [ConditionRef::of(Condition::ALL_KEY_KINDS_PAID)], limit: $onceTurn),
            $modifier('M062', $activated, Modifier::RECALL_TIMES, ['times' => 2], [ConditionRef::of(Condition::OTHER_PERSONA_IN_ROOM)]),
            $modifier('M063', $constant, Modifier::LIGHT_SCHEDULE, ['context' => ActionContext::MOVE, 'table' => $schedule]),
            $modifier('M064', $activated, Modifier::MOVE_LOCK_REDUCTION, $lockReduction, $firstLv2),
            $modifier('M065', $activated, Modifier::MOVE_LOCK_REDUCTION, $lockReduction, $firstLv2),
            $modifier('M066', $activated, Modifier::MOVE_LOCK_REDUCTION, $lockReduction, $firstLv2),
            $trigger('M067', $activated, [Event::MOVE_ENDED, Event::PERSONA_STOPPED], [$light()], [$fourRooms()], limit: $onceTurn),
            $modifier('M068', $activated, Modifier::COMPOSE_ENERGY_DELTA, ['delta' => -1], [
                $fourRooms(),
                ConditionRef::of(Condition::FIRST_COMPOSE_ACTION_THIS_TURN),
            ]),
            $trigger('M069', $activated, [Event::MOVE_ENDED, Event::PERSONA_STOPPED], [$open()], [$fourRooms()], limit: $onceTurn),
            $modifier('M070', $constant, Modifier::LIGHT_SCHEDULE, ['context' => ActionContext::COMPOSE, 'table' => $schedule]),
            // M071: 自己構成したカードの想起効果のうち、引く・得る・交換（RC-01〜RC-07）だけ使ってよい（R8-5）。
            // 'EVENT_CARD' は RecallActivate::EVENT_CARD（誘発したイベントの card_type = 自己構成したカード）
            $trigger('M071', $activated, $composed, [
                EffectNode::optional(EffectNode::op(Op::RECALL_ACTIVATE, [
                    'card' => 'EVENT_CARD',
                    'times' => 1,
                    'groups' => [RecallGroup::DRAW, RecallGroup::GAIN, RecallGroup::EXCHANGE],
                ])),
            ], optional: true),
            $turnStart('M072', EffectNode::optional(EffectNode::op(Op::WARP), ChoiceKind::WARP_ROOM)),
            $trigger('M073', $activated, $composed, [$draw()], [$hasKey(KeyType::WISDOM)]),
            $trigger('M074', $activated, $composed, [$draw()], [$hasKey(KeyType::EMOTION)]),
            $trigger('M075', $activated, $composed, [$draw()], [$hasKey(KeyType::WILL)]),
            $trigger('M076', $activated, $composed, [$light()], [$hasKey(KeyType::WISDOM)]),
            $modifier('M077', $constant, Modifier::COMPOSE_ENERGY_DELTA, ['delta' => -1], [$hasKey(KeyType::EMOTION)]),
            $trigger('M078', $activated, $composed, [$open()], [$hasKey(KeyType::WILL)]),
            $trigger('M079', $activated, $tierUp, [$light()]),
            $trigger('M080', $activated, $tierUp, [EffectNode::op(Op::ADD_NEXT_COMPOSE_DISCOUNT, ['n' => 1])]),
            $trigger('M081', $activated, $tierUp, [$open()]),
            // M085〜M087: 床アクションの「引く・場札・心光」の要素そのものに反応する（FAQ 行 27）。追加分は連鎖しない
            $trigger('M085', $activated, $floorGain, [$extra(Op::GAIN_LIGHT)], $element('LIGHT')),
            $trigger('M086', $activated, $floorGain, [$extra(Op::DRAW_DECK)], $element('DRAW')),
            $trigger('M087', $activated, $floorGain, [$extra(Op::GAIN_OPEN)], $element('OPEN')),
            // 即時効果（自己構成したカード自身にだけ反応する。TriggerCollector）
            $trigger('M088', $instant, $composed, [EffectNode::op(Op::ADVANCE_TIER, ['via' => 'EFFECT', 'max_tier' => 4])]),
            // M089: 「してもよい」は 61 の `actCancel`（R2・R4 と同じ。確認を重ねて聞かない）
            $trigger('M089', $instant, $composed, [
                EffectNode::optional(EffectNode::op(Op::COMPOSE, ['ctx' => ComposeContext::EFFECT_FREE_LV1])),
            ]),
            $trigger('M090', $instant, $composed, [
                EffectNode::repeat(
                    ValueExpr::floorDiv(ValueExpr::composedCount(), 3),
                    EffectNode::op(Op::GAIN_TOKEN, ['token' => TokenKind::WILD_MOVE_KEY, 'n' => 1]),
                ),
            ]),
            $modifier('M094', $constant, Modifier::KEY_VIEW, [
                'from' => KeyType::WISDOM,
                'to' => KeyType::WILD,
                'context' => ActionContext::ANALYSIS,
            ]),
            $modifier('M095', $constant, Modifier::TAP_FOR_ENERGY, ['key' => KeyType::EMOTION]),
            $modifier('M096', $constant, Modifier::KEY_VIEW, [
                'from' => KeyType::WILL,
                'to' => KeyType::WILD,
                'context' => ActionContext::MOVE,
            ]),
            $trigger('M097', $instant, $composed, [$light(ValueExpr::composedKeys(KeyType::WISDOM))]),
            $modifier('M098', EffectKind::HELD, Modifier::HELD_ENERGY_DELTA, ['key' => KeyType::EMOTION, 'per' => 1]),
            $trigger('M099', $instant, $composed, [$open(ValueExpr::composedKeys(KeyType::WILL))]),
        ];
    }

    // ---- 初期自我 8 枚・強化自我 5 枚（008 §7.6） ----

    /**
     * @return list<EffectSpec>
     */
    private static function egoSpecs(): array
    {
        $effect = static fn (Op $op, array $params = []): EffectNode => EffectNode::op($op, [...$params, 'origin' => Origin::EFFECT]);
        $trigger = static fn (
            string $id,
            string $card,
            EffectKind $kind,
            array $events,
            array $ops,
            array $conditions = [],
            bool $optional = false,
            EffectLimit $limit = EffectLimit::PER_EVENT,
            bool $unit = false,
            array $params = [],
        ): EffectSpec => new EffectSpec(
            id: $id,
            source: $card,
            mechanism: Mechanism::TRIGGER,
            kind: $kind,
            ownerScope: OwnerScope::OWNER,
            ops: $ops,
            events: $events,
            conditions: $conditions,
            optional: $optional,
            limit: $limit,
            unit: $unit,
            params: $params,
        );
        $modifier = static fn (
            string $id,
            string $card,
            EffectKind $kind,
            Modifier $modifier,
            array $params = [],
        ): EffectSpec => new EffectSpec(
            id: $id,
            source: $card,
            mechanism: Mechanism::MODIFIER,
            kind: $kind,
            ownerScope: OwnerScope::OWNER,
            modifier: $modifier,
            params: $params,
        );
        $turnStart = static fn (string $card, EffectNode $node, bool $optional = false): EffectSpec => $trigger(
            $card . '.1',
            $card,
            EffectKind::TURN_START,
            [Event::TURN_START],
            [$node],
            optional: $optional,
            unit: true,
        );
        $draw = $effect(Op::DRAW_DECK, ['n' => 1]);

        return [
            $trigger('I01.1', 'I01', EffectKind::ACTIVATED, [Event::COMPOSED], [$draw], [
                ConditionRef::of(Condition::COMPOSED_COUNT_IN, ['set' => [1, 3, 5, 7, 9]]),
            ]),
            $trigger('I02.1', 'I02', EffectKind::CONSTANT, [Event::LIGHT_GAINED], [
                EffectNode::op(Op::GAIN_LIGHT, ['n' => 1, 'origin' => Origin::EXTRA]),
            ], [ConditionRef::of(Condition::LIGHT_ORIGIN, ['origin' => Origin::SPIRIT_CARD])]),
            $modifier('I03.1', 'I03', EffectKind::ACTIVATED, Modifier::ARCHITECT_SUBSTITUTE, ['plus' => 2]),
            $modifier('I04.1', 'I04', EffectKind::CONSTANT, Modifier::ANALYSIS_PAY_REDIRECT),
            // I04.2: 止まった部屋の床の下にカードがあるときだけ働く（`needs`。TriggerCollector が確かめる）
            $trigger('I04.2', 'I04', EffectKind::ACTIVATED, [Event::PERSONA_STOPPED], [EffectNode::op(Op::COLLECT_UNDER_FLOOR)], params: [
                'needs' => 'UNDER_FLOOR_CARDS',
            ]),
            $turnStart('I05', EffectNode::optional(EffectNode::op(Op::SWAP_HAND_ROOM), ChoiceKind::ENTREPRENEUR_SWAP), true),
            $trigger('I06.1', 'I06', EffectKind::ACTIVATED, [Event::MOVE_ENDED], [$draw], [
                ConditionRef::of(Condition::NO_WILD_TOKEN_USED),
            ], limit: EffectLimit::ONCE_PER_TURN),
            $trigger('I07.1', 'I07', EffectKind::ACTIVATED, [Event::PERSONA_PASSED_OTHER], [$draw], limit: EffectLimit::ONCE_PER_TURN),
            $modifier('I08.1', 'I08', EffectKind::CONSTANT, Modifier::EXTRA_PAY_SCORE, ['max' => 2]),
            $modifier('E01.1', 'E01', EffectKind::CONSTANT, Modifier::COMPOSE_ENERGY_DELTA, ['delta' => -1]),
            $modifier('E02.1', 'E02', EffectKind::CONSTANT, Modifier::ANALYSIS_COST_DELTA, ['delta' => -1]),
            $turnStart('E03', $draw),
            $turnStart('E04', $effect(Op::GAIN_LIGHT, ['n' => 1])),
            $turnStart('E05', EffectNode::op(Op::GAIN_TOKEN, ['token' => TokenKind::WILD_MOVE_KEY, 'n' => 1])),
        ];
    }

    /**
     * 目標カード 7（008 §7.7）。条件は O001 だけ CD-01 で、他は `params['when']` の名前で `ObjectiveEvaluator` が判定する。
     * X の数え方は `params['x']`、得点表（X の下限 → 得点）は 002 §10 の生成物 `ObjectiveCards` から引く（重複して持たない）。
     *
     * @return list<EffectSpec>
     */
    private static function objectiveSpecs(): array
    {
        $objective = static fn (string $id, string $when, string $x, array $conditions = [], array $params = []): EffectSpec => new EffectSpec(
            id: $id,
            source: $id,
            mechanism: Mechanism::OBJECTIVE,
            kind: EffectKind::NONE,
            ownerScope: OwnerScope::TURN_PLAYER,
            conditions: $conditions,
            params: [...$params, 'when' => $when, 'x' => $x, 'table' => ObjectiveCards::get($id)->scoreTable],
        );

        return [
            $objective('O001', 'ROOMS_ENTERED', 'WILD_MOVE_KEY_TOKENS', [ConditionRef::of(Condition::ROOMS_ENTERED_GE, ['n' => 6])]),
            $objective('O002', 'LEVEL2_COMPOSED_GE', 'LEVEL2_COMPOSED', params: ['n' => 2]),
            $objective('O003', 'MOST_KEYS_GE', 'MOST_KEYS', params: ['n' => 4]),
            $objective('O004', 'HAND_AT_TURN_END_GE', 'HAND_COUNT', params: ['n' => 6]),
            $objective('O005', 'LIGHTS_GAINED_GE', 'LIGHT_COUNT', params: ['n' => 4]),
            $objective('O006', 'ANALYSIS_ACTIONS_GE', 'SPIRIT_TIER', params: ['n' => 2]),
            $objective('O007', 'ALL_KEY_KINDS_WITH_WILD', 'KEY_SETS'),
        ];
    }
}
