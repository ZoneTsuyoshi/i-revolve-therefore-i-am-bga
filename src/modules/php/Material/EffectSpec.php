<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 効果 1 つの構造化された定義（008 §2.1）。値オブジェクト。
 */
final readonly class EffectSpec
{
    /**
     * @param string $id 効果 ID（`M058.1`、想起効果は `RC-01`、床は `F01`、周遊ボーナスは `F01.TOUR`、精神カードは `R1A`）
     * @param string $source 出典のカード ID。想起効果は表向きにした想い出カードの ID が実行時に入るので空文字列
     * @param list<EffectNode> $ops 操作の列（左から右へ順に実行する。全体を 1 つの `SEQ` として扱う）
     * @param list<Event> $events `TRIGGER` の hook
     * @param Modifier|null $modifier `MODIFIER` の hook
     * @param list<ConditionRef> $conditions 条件の AND
     * @param bool $unit この効果の解決が終わったとき、手札上限を確認する単位か（008 §6.9）
     * @param array<string, mixed> $params 効果ごとの数値（`delta`、`table` など）
     * @param list<ResourceTouch>|null $touchesOverride 宣言した資源。null なら操作の触る資源の和集合
     */
    public function __construct(
        public string $id,
        public string $source,
        public Mechanism $mechanism,
        public EffectKind $kind,
        public OwnerScope $ownerScope,
        public array $ops = [],
        public array $events = [],
        public ?Modifier $modifier = null,
        public array $conditions = [],
        public bool $optional = false,
        public EffectLimit $limit = EffectLimit::PER_EVENT,
        public bool $unit = false,
        public array $params = [],
        public ?array $touchesOverride = null,
    ) {
    }

    /**
     * 触る資源（008 §6.3）。宣言が無ければ、操作の触る資源の和集合（重複なし、出現順）。
     *
     * @return list<ResourceTouch>
     */
    public function touches(): array
    {
        if ($this->touchesOverride !== null) {
            return $this->touchesOverride;
        }

        return self::unionOfOps($this->ops);
    }

    /**
     * 操作の触る資源の和集合（重複なし、出現順）。
     *
     * @param list<EffectNode> $nodes
     * @return list<ResourceTouch>
     */
    public static function unionOfOps(array $nodes): array
    {
        $seen = [];
        $touches = [];
        foreach ($nodes as $node) {
            foreach ($node->touches() as $touch) {
                $key = $touch->toString();
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $touches[] = $touch;
                }
            }
        }

        return $touches;
    }

    /**
     * 操作列を先行順に並べる（`effect_frame.frame_step` が指す位置。008 §10.1）。
     *
     * @return list<EffectStep>
     */
    public function steps(): array
    {
        $nodes = [];
        foreach ($this->ops as $node) {
            self::collect($node, $nodes);
        }
        $steps = [];
        foreach ($nodes as $index => $node) {
            $steps[] = new EffectStep($index, $node, $index + self::size($node));
        }

        return $steps;
    }

    /**
     * @param list<EffectNode> $nodes
     */
    private static function collect(EffectNode $node, array &$nodes): void
    {
        $nodes[] = $node;
        foreach ($node->children as $child) {
            self::collect($child, $nodes);
        }
    }

    /** 部分木の節の数（自分を含む）。 */
    private static function size(EffectNode $node): int
    {
        $size = 1;
        foreach ($node->children as $child) {
            $size += self::size($child);
        }

        return $size;
    }

    /**
     * 操作列に含まれる原子操作（出現順）。
     *
     * @return list<Op>
     */
    public function allOps(): array
    {
        $ops = [];
        foreach ($this->ops as $node) {
            array_push($ops, ...$node->ops());
        }

        return $ops;
    }
}
