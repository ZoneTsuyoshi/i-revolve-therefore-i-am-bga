<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 効果の操作列の 1 つの節（原子操作 1 つ、または制御構造。008 §3）。
 *
 * - 原子操作: `kind` が `Op`。`params` に仕様書のパラメータ（`n`、`origin`、`ctx` など）。
 * - `SEQ` / `OPTIONAL` / `REPEAT`: 子を持つ。`CHOOSE_ONE`: 子の 1 つ 1 つが分岐。
 * - `OPTIONAL` の `via` は「してもよい」を聞く方法。null は 61・62 の `actCancel`（行動ごと）、
 *   値があれば `CONFIRM_USE` / `CONFIRM_MOVE` / 操作自身の選択（スキップ可）。
 */
final readonly class EffectNode
{
    /**
     * @param array<string, mixed> $params
     * @param list<EffectNode> $children
     */
    private function __construct(
        public Op|Control $kind,
        public array $params = [],
        public array $children = [],
        public ?ChoiceKind $via = null,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function op(Op $op, array $params = []): self
    {
        return new self($op, $params);
    }

    public static function seq(self ...$children): self
    {
        return new self(Control::SEQ, [], array_values($children));
    }

    public static function chooseOne(self ...$branches): self
    {
        return new self(Control::CHOOSE_ONE, [], array_values($branches));
    }

    public static function optional(self $child, ?ChoiceKind $via = null): self
    {
        return new self(Control::OPTIONAL, [], [$child], $via);
    }

    /**
     * @param mixed $times 回数（整数または値式）
     */
    public static function repeat(mixed $times, self $child): self
    {
        return new self(Control::REPEAT, ['times' => $times], [$child]);
    }

    /**
     * この節以下が触る資源（008 §6.3。重複なし、出現順）。`COMPOSE` は、次の自己構成の軽減（`ND`）を消費する文脈
     * （PHASE / TOKEN / FLOOR）のときだけ `ND w` を含める（ボーナスと M089 の無料の自己構成は消費しない。R8-6）。
     *
     * @return list<ResourceTouch>
     */
    public function touches(): array
    {
        $seen = [];
        $touches = [];
        $add = static function (ResourceTouch $touch) use (&$seen, &$touches): void {
            $key = $touch->toString();
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $touches[] = $touch;
            }
        };
        if ($this->kind instanceof Op) {
            $context = $this->params['ctx'] ?? null;
            $dropsNext = $this->kind === Op::COMPOSE && $context instanceof ComposeContext && !$context->consumesNextDiscount();
            foreach ($this->kind->touches() as $touch) {
                if (!($dropsNext && $touch->symbol === ResourceSymbol::ND)) {
                    $add($touch);
                }
            }

            return $touches;
        }
        foreach ($this->children as $child) {
            foreach ($child->touches() as $touch) {
                $add($touch);
            }
        }

        return $touches;
    }

    public function atomicOp(): ?Op
    {
        return $this->kind instanceof Op ? $this->kind : null;
    }

    public function intParam(string $key, int $default = 0): int
    {
        $value = $this->params[$key] ?? $default;

        return is_int($value) ? $value : $default;
    }

    public function originParam(): ?Origin
    {
        $value = $this->params['origin'] ?? null;

        return $value instanceof Origin ? $value : null;
    }

    /**
     * この節以下の原子操作を、出現順に返す。
     *
     * @return list<Op>
     */
    public function ops(): array
    {
        if ($this->kind instanceof Op) {
            return [$this->kind];
        }
        $ops = [];
        foreach ($this->children as $child) {
            array_push($ops, ...$child->ops());
        }

        return $ops;
    }
}
