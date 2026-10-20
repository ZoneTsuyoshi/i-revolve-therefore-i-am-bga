<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

// 自動生成。仕様書 002 の表から scripts/build-card-data.mjs が生成した。手で編集しない。
// 変更は仕様書 002（または card.csv）を直して、npm run build:cards で再生成する。

/**
 * 精神カード 9 枚の定義（002 §8）。
 */
final class AnalysisCards
{
    /** 行の並びは AnalysisCardDefinition のコンストラクタ引数（`id` の次から）と同じ。 */
    private const ROWS = [
        'R1A' => [
            1, 'A', ['WISDOM' => 2],
            '[山札]+1', true,
        ],
        'R1B' => [
            1, 'B', ['WILL' => 2],
            '[山札]+1', true,
        ],
        'R1C' => [
            1, 'C', ['EMOTION' => 2],
            '[山札]+1', true,
        ],
        'R2' => [
            2, null, ['WISDOM' => 1, 'EMOTION' => 1, 'WILL' => 1],
            '[エネルギー]を支払わずにレベル 1 の想い出カードを 1 枚自己構成してもよい', true,
        ],
        'R3A' => [
            3, 'A', ['WISDOM' => 3],
            '強化自我（強化自我カードの表を秘密裏に確認して 1 枚獲得する。p.VII の例）', true,
        ],
        'R3B' => [
            3, 'B', ['WILL' => 3],
            '強化自我', true,
        ],
        'R3C' => [
            3, 'C', ['EMOTION' => 3],
            '強化自我', true,
        ],
        'R4' => [
            4, null, ['WISDOM' => 2, 'EMOTION' => 2, 'WILL' => 2],
            '[エネルギー]を支払わずに想い出カードを 1 枚自己構成してもよい', true,
        ],
        'R5' => [
            5, null, ['WISDOM' => 4, 'EMOTION' => 4, 'WILL' => 4],
            '【心理の極致】即座にゲームに勝利する', false,
        ],
    ];

    /** @var array<string, AnalysisCardDefinition>|null */
    private static ?array $all = null;

    /**
     * @return array<string, AnalysisCardDefinition> ID をキーとする定義（定義順）
     */
    public static function all(): array
    {
        if (self::$all === null) {
            $all = [];
            foreach (self::ROWS as $id => $row) {
                $all[$id] = new AnalysisCardDefinition($id, ...$row);
            }
            self::$all = $all;
        }

        return self::$all;
    }

    /**
     * 精神カードの定義を返す（`R1A`〜`R5`）。
     *
     * @throws \OutOfBoundsException 未知の ID
     */
    public static function get(string $id): AnalysisCardDefinition
    {
        return self::all()[$id] ?? throw new \OutOfBoundsException("Unknown card id: {$id}");
    }
}
