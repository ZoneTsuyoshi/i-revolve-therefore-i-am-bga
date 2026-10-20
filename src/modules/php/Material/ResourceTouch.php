<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 効果が触る資源 1 つ（記号 + 触り方。例: `D+`、`Hw`）。
 */
final readonly class ResourceTouch
{
    public function __construct(
        public ResourceSymbol $symbol,
        public Access $access,
    ) {
    }

    /** `D+`・`Hw`・`NDw` のような記法から作る。 */
    public static function parse(string $text): self
    {
        $access = Access::from(substr($text, -1));

        return new self(ResourceSymbol::from(substr($text, 0, -1)), $access);
    }

    /**
     * 空白区切りの記法から作る。
     *
     * @return list<self>
     */
    public static function parseList(string $text): array
    {
        $touches = [];
        foreach (preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $touches[] = self::parse($part);
        }

        return $touches;
    }

    /** 衝突 = 片方の `r` / `w` と、他方の同じ記号への `+` / `r` / `w`（008 §6.3.2）。`r` どうし、`+` どうしは衝突しない。 */
    public function conflictsWith(self $other): bool
    {
        if ($this->symbol !== $other->symbol) {
            return false;
        }
        if ($this->access === Access::ADD && $other->access === Access::ADD) {
            return false;
        }

        return !($this->access === Access::READ && $other->access === Access::READ);
    }

    /**
     * 2 つの効果の触る資源の集合に、衝突する対が 1 つでもあるか（008 §6.3.1 の 2。同時に誘発したとき `ORDER_EFFECTS` を聞く条件）。
     *
     * @param list<self> $a
     * @param list<self> $b
     */
    public static function anyConflict(array $a, array $b): bool
    {
        foreach ($a as $left) {
            foreach ($b as $right) {
                if ($left->conflictsWith($right)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function toString(): string
    {
        return $this->symbol->value . $this->access->value;
    }
}
