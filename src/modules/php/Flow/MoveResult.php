<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;

/**
 * 移動を適用した結果。通知は値として返し、`Game` が送る（`Flow/` はフレームワークを呼ばない）。
 */
final readonly class MoveResult
{
    /**
     * @param list<Notice> $notices 送る順
     * @param bool $ended 刹那の追憶が成立してゲームが終わる（`end_reason` と勝者は書き込み済み）
     * @param list<GameEvent> $events 移動で起きたイベント（EV-02〜EV-04。勝利したときは空）。トリガーの収集は I-09
     */
    public function __construct(
        public array $notices,
        public bool $ended,
        public array $events,
    ) {
    }
}
