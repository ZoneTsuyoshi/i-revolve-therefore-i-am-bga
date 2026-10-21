<?php

declare(strict_types=1);

namespace IRevolveTests\Support;

use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;

/**
 * 鍵とレベルから想い出カードの種類を探す、テスト用の道具（目標の X の入力を作る）。
 */
final class CardTypes
{
    /**
     * 鍵の並びが一致する（`$level` が null でなければレベルも一致する）カードの ID を返す。
     *
     * @param list<KeyType> $keys
     * @param list<string> $exclude 使わない ID
     */
    public static function id(array $keys, ?int $level = null, array $exclude = []): string
    {
        foreach (MemoryCards::all() as $id => $def) {
            if ($def->keys === $keys && ($level === null || $def->level === $level) && !in_array($id, $exclude, true)) {
                return $id;
            }
        }
        throw new \LogicException('no card with those keys');
    }

    /**
     * レベル 2 のカードの ID を n 種類（定義順）。
     *
     * @return list<string>
     */
    public static function level2(int $n): array
    {
        $ids = [];
        foreach (MemoryCards::all() as $id => $def) {
            if ($def->level === 2) {
                $ids[] = $id;
            }
        }

        return array_slice($ids, 0, $n);
    }

    /** `M058` → 58 */
    public static function number(string $id): int
    {
        return (int) substr($id, 1);
    }
}
