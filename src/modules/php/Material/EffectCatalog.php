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
 * 周遊ボーナス 4（F01.TOUR など。§7.4）、精神カードのボーナス 9（R1A〜R5。§7.5）。
 * 想い出カード 42・自我カード 13・目標カード 7 は後続のタスク（実装順 5・7。§11.2）で足す。
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
            foreach ([...self::recallSpecs(), ...self::floorSpecs(), ...self::tourSpecs(), ...self::spiritSpecs()] as $spec) {
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
}
