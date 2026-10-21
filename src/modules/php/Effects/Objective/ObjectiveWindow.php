<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Objective;

use Bga\Games\IRevolveThereforeIAm\Material\ActionContext;

/**
 * 目標を評価する「窓」（呼び出した状態）。状態によって変わる 2 つの条件を持つ。
 * - 鍵の読み替えの文脈: O007 の「万能鍵が存在する」は、31（`MOVE`。M096）と 62（`ANALYSIS`。M094）の中でだけ読み替えを含める（FAQ 行 29、T8-6）。
 * - ターン終了時: O004 は 81 の中でだけ条件を満たせる（Q-30、006 §6.3）。
 */
final readonly class ObjectiveWindow
{
    private function __construct(
        public ?ActionContext $keyContext,
        public bool $turnEnd,
    ) {
    }

    /** 読み替えもターン終了時の判定もない状態（51・60・61・71・72 など）。 */
    public static function none(): self
    {
        return new self(null, false);
    }

    /** 31 移動フェイズ */
    public static function move(): self
    {
        return new self(ActionContext::MOVE, false);
    }

    /** 62 精神分析アクション */
    public static function analysis(): self
    {
        return new self(ActionContext::ANALYSIS, false);
    }

    /** 81 ターン終了時 */
    public static function turnEnd(): self
    {
        return new self(null, true);
    }
}
