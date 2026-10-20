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
 * 目標カード 7 枚の定義（002 §10）。
 */
final class ObjectiveCards
{
    /** 行の並びは ObjectiveCardDefinition のコンストラクタ引数（`id` の次から）と同じ。 */
    private const ROWS = [
        'O001' => [
            '万能移動鍵の目標', 'wild_move_key_goal',
            '1 ターンに 6 部屋以上入室',
            '万能移動鍵トークンの数',
            [[1, 1], [2, 2], [4, 3], [6, 4]],
        ],
        'O002' => [
            'Lv2自己構成の目標', 'level2_composed_goal',
            'レベル 2 の想い出カードを 2 枚以上自己構成',
            '自己構成済みのレベル 2 想い出カードの枚数',
            [[1, 1], [2, 2], [3, 3], [4, 4]],
        ],
        'O003' => [
            '同一エンジンの目標', 'single_engine_goal',
            '自己構成済みカードの最多鍵の本数が 4 本以上（MAX(WILL, WISDOM, EMOTION) ≧ 4）',
            '自己構成済みカードの最多鍵の本数',
            [[1, 1], [2, 2], [4, 3], [6, 4]],
        ],
        'O004' => [
            '手札の目標', 'hand_goal',
            'ターン終了時の手札が 6 枚以上',
            '手札枚数',
            [[1, 1], [3, 2], [5, 3], [7, 4]],
        ],
        'O005' => [
            '心光の目標', 'light_goal',
            '1 ターンに心光を 4 つ以上獲得',
            '心光保有数',
            [[1, 1], [2, 2], [4, 3], [6, 4]],
        ],
        'O006' => [
            '精神分析階層の目標', 'analysis_floor_goal',
            '1 ターンに精神分析を 2 回以上実行',
            '精神分析階層',
            [[1, 1], [2, 2], [3, 3], [4, 4]],
        ],
        'O007' => [
            '多種エンジンの目標', 'multi_engine_goal',
            '自己構成済みカードに知恵鍵・意志鍵・感情鍵・万能鍵が全て存在',
            '知恵鍵・意志鍵・感情鍵のセットの数',
            [[1, 1], [2, 2], [3, 3], [4, 4]],
        ],
    ];

    /** @var array<string, ObjectiveCardDefinition>|null */
    private static ?array $all = null;

    /**
     * @return array<string, ObjectiveCardDefinition> ID をキーとする定義（定義順）
     */
    public static function all(): array
    {
        if (self::$all === null) {
            $all = [];
            foreach (self::ROWS as $id => $row) {
                $all[$id] = new ObjectiveCardDefinition($id, ...$row);
            }
            self::$all = $all;
        }

        return self::$all;
    }

    /**
     * 目標カードの定義を返す（`O001`〜`O007`）。
     *
     * @throws \OutOfBoundsException 未知の ID
     */
    public static function get(string $id): ObjectiveCardDefinition
    {
        return self::all()[$id] ?? throw new \OutOfBoundsException("Unknown card id: {$id}");
    }
}
