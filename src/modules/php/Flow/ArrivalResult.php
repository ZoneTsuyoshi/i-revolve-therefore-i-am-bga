<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

/**
 * 精神カードへの到着の結果（{@see SpiritArrival::arrive()}）。
 */
final readonly class ArrivalResult
{
    /**
     * @param bool $hadLight 到着した精神カードに心光が置かれていたか（008 の `dest_had_light`）
     * @param list<Notice> $notices
     */
    public function __construct(
        public bool $hadLight,
        public array $notices,
    ) {
    }
}
