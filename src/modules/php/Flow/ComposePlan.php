<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

/**
 * 検証済みの自己構成の支払い（{@see ComposePlanner::plan()}）。
 */
final readonly class ComposePlan
{
    /**
     * @param list<int> $discard エネルギーの支払いに捨てる手札
     * @param int $lightUses 心光の行使の回数（エネルギー何個分を心光で払うか）
     * @param int $lights 払う心光の総数（逓増と軽減を反映）
     * @param list<int> $taps 横向きにする自己構成済みカード（M095）
     * @param int $need 払う必要のあるエネルギー（軽減と横向きを反映。0 未満にならない）
     * @param list<int> $extra I08 の追加支払い（自己構成したカードの下に裏向きで置く。1 枚につきスコア +1）
     */
    public function __construct(
        public int $cardId,
        public array $discard,
        public int $lightUses,
        public int $lights,
        public array $taps,
        public int $need,
        public array $extra = [],
    ) {
    }
}
