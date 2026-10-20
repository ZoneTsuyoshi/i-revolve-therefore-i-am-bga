<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 操作列を先行順（pre-order）に並べたときの 1 項目。`effect_frame.frame_step` はこの `index` を指す（008 §10.1）。
 * `end` はこの節の部分木の次の `index`（飛ばすときの行き先）。
 */
final readonly class EffectStep
{
    public function __construct(
        public int $index,
        public EffectNode $node,
        public int $end,
    ) {
    }
}
