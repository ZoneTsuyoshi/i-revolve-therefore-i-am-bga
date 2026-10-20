<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

/**
 * 進行度（仕様書 006 §11）。勝利条件への近さ（精神階層、自己構成スコア）と山札切れへの近さの最大値で近似し、
 * 最大値を保って単調増加にする。フレームワークを呼ばない純粋な関数。
 */
final class GameProgress
{
    /** 断片の結集の自己構成スコア。 */
    public const WIN_SCORE = 20;

    /** 第五階層（心理の極致）。 */
    public const TOP_TIER = 5;

    private function __construct()
    {
    }

    /**
     * @param int $maxTier 全プレイヤーの精神階層の最大（0〜5）
     * @param int $maxScore 全プレイヤーの自己構成スコアの最大
     * @param int $deck1Left レベル 1 の山札の残り
     * @param int $deck1Initial セットアップ終了時のレベル 1 の山札
     * @param int $deck2Left レベル 2 の山札の残り
     * @param int $deck2Initial セットアップ終了時のレベル 2 の山札
     * @param int $highWater これまでの最大値
     * @return int 0〜99
     */
    public static function calculate(
        int $maxTier,
        int $maxScore,
        int $deck1Left,
        int $deck1Initial,
        int $deck2Left,
        int $deck2Initial,
        int $highWater,
    ): int {
        $raw = max(
            min(max($maxTier, 0), self::TOP_TIER) / self::TOP_TIER,
            min(max($maxScore, 0), self::WIN_SCORE) / self::WIN_SCORE,
            self::deckProgress($deck1Left, $deck1Initial),
            self::deckProgress($deck2Left, $deck2Initial),
        );
        $progress = min(99, (int) floor(100 * $raw));

        return max($progress, min(99, max($highWater, 0)));
    }

    private static function deckProgress(int $left, int $initial): float
    {
        if ($initial <= 0) {
            return 0.0;
        }

        return min(1.0, max(0.0, 1 - $left / $initial));
    }
}
