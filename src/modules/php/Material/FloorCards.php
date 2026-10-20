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
 * 床カード 12 枚の定義（002 §7）。
 */
final class FloorCards
{
    /** 行の並びは FloorCardDefinition のコンストラクタ引数（`id` の次から）と同じ。 */
    private const ROWS = [
        'F01' => [
            'DEMETER', 'demeter', 2, '[山札]+3（想い出の山札から 3 枚引く）',
            FloorBonus::ANALYSIS_TOKEN, 1,
        ],
        'F02' => [
            'HERMES', 'hermes', 1, '精神分析アクション',
            FloorBonus::NONE, 2,
        ],
        'F03' => [
            'ATHENA', 'athena', 1, '[山札]+3 のあと、手札を 1 枚捨てる',
            FloorBonus::NONE, 3,
        ],
        'F04' => [
            'POSEIDON', 'poseidon', 2, '自己構成アクション。このターン中、自己構成時のエネルギーは常に -1',
            FloorBonus::LIGHT, 4,
        ],
        'F05' => [
            'ARES', 'ares', 1, '[場札]+1、[心光]+1',
            FloorBonus::NONE, 5,
        ],
        'F06' => [
            'DIONYSUS', 'dionysus', 1, '精神分析アクション',
            FloorBonus::NONE, 6,
        ],
        'F07' => [
            'HESTIA', 'hestia', 2, '[場札]+1、[山札]+1、[心光]+1',
            FloorBonus::WILD_MOVE_KEY, 7,
        ],
        'F08' => [
            'APOLLO', 'apollo', 1, '[山札]+3 のあと、手札を 1 枚捨てる',
            FloorBonus::NONE, 8,
        ],
        'F09' => [
            'APHRODITE', 'aphrodite', 1, '[場札]+2 / [心光]+2（/ は左右いずれか）',
            FloorBonus::NONE, 9,
        ],
        'F10' => [
            'HADES', 'hades', 2, '精神分析アクション、[心光]+1',
            FloorBonus::COMPOSE_TOKEN, 10,
        ],
        'F11' => [
            'ARTEMIS', 'artemis', 1, '[場札]+1、[心光]+1',
            FloorBonus::NONE, 11,
        ],
        'F12' => [
            'HEPHAESTUS', 'hephaestus', 1, '自己構成アクション（エネルギー -1 で）/ [山札]+2（/ は左右いずれか）',
            FloorBonus::NONE, 12,
        ],
    ];

    /** @var array<string, FloorCardDefinition>|null */
    private static ?array $all = null;

    /**
     * @return array<string, FloorCardDefinition> ID をキーとする定義（定義順）
     */
    public static function all(): array
    {
        if (self::$all === null) {
            $all = [];
            foreach (self::ROWS as $id => $row) {
                $all[$id] = new FloorCardDefinition($id, ...$row);
            }
            self::$all = $all;
        }

        return self::$all;
    }

    /**
     * 床カードの定義を返す（`F01`〜`F12`）。
     *
     * @throws \OutOfBoundsException 未知の ID
     */
    public static function get(string $id): FloorCardDefinition
    {
        return self::all()[$id] ?? throw new \OutOfBoundsException("Unknown card id: {$id}");
    }
}
