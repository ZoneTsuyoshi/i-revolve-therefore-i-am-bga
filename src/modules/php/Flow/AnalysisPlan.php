<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

/**
 * 検証済みの精神分析の支払い（{@see AnalysisPlanner::plan()}）。
 */
final readonly class AnalysisPlan
{
    /**
     * @param string $destination 進む先の精神カード（`R1A` など）
     * @param list<int> $tapped 横向きにする自己構成済みカード
     * @param list<int> $discarded 捨てる手札
     * @param int $lightUses 心光の行使の回数
     * @param int $lights 払う心光の総数（逓増と軽減を反映）
     */
    public function __construct(
        public string $destination,
        public array $tapped,
        public array $discarded,
        public int $lightUses,
        public int $lights,
    ) {
    }
}
