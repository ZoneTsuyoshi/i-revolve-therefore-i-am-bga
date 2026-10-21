<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

/**
 * 70 `EffectResolve` の 1 回の入場の結果（006 §7.2）。状態クラスは `changeActivePlayer` して `transition` を返すだけ。
 */
final readonly class ResolveResult
{
    /**
     * @param string $transition 遷移名（`Transition::CHOICE`・`COMPOSE`・`ANALYSIS`・`HAND_LIMIT`・`GAME_END`、または `resume` の戻り先）
     * @param int $activePlayer 次の状態でアクティブにするプレイヤー（選択者、超過したプレイヤー、戻り先ではターンプレイヤー）
     * @param list<Notice> $notices
     */
    public function __construct(
        public string $transition,
        public int $activePlayer,
        public array $notices,
    ) {
    }
}
