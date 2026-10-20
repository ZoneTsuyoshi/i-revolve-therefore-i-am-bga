<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 精神カード 1 枚の定義（002 §8）。
 * 精神分析ボーナスの構造化は仕様書 008。
 */
final readonly class AnalysisCardDefinition
{
    /**
     * @param int $floor 階層（1〜5）
     * @param string|null $branch 分岐（`A` / `B` / `C`）。分岐の無い階層は null
     * @param array<string, int> $cost 精神分析コスト。キーは KeyType の値、値は本数
     * @param bool $hasLight セットアップで心光トークンを置くか
     */
    public function __construct(
        public string $id,
        public int $floor,
        public ?string $branch,
        public array $cost,
        public string $bonusTextJa,
        public bool $hasLight,
    ) {
    }

    /** 指定した種類の鍵のコスト本数（無ければ 0）。 */
    public function costOf(KeyType $key): int
    {
        return $this->cost[$key->value] ?? 0;
    }
}
