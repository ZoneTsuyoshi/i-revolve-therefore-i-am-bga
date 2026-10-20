<?php

declare(strict_types=1);

namespace IRevolveTests\Support;

use Bga\Games\IRevolveThereforeIAm\Flow\MoveBoard;
use Bga\Games\IRevolveThereforeIAm\Flow\MoveCard;
use Bga\Games\IRevolveThereforeIAm\Flow\MoveRoom;
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;

/**
 * 移動の検証（`MovePlanner`）の単体テスト用の盤面。回廊は固定配置（位置 = 床カード ID。001 §3）で、
 * 位置 1・4・7・10 がレベル 2（周遊ボーナス: 精神分析トークン・心光・万能移動鍵・自己構成トークン）。
 */
final class MoveBoards
{
    /**
     * @param array<int, KeyType> $locks 位置 → 錠前の種類（指定が無い位置は知恵）
     * @param list<MoveCard> $hand
     * @param list<MoveCard> $tableau
     * @param list<int> $othersAt 他の人格がいる位置
     */
    public static function board(
        int $position = 1,
        array $locks = [],
        array $hand = [],
        array $tableau = [],
        int $lights = 0,
        int $wildUp = 0,
        int $priorLv2Entries = 0,
        array $othersAt = [],
        int $player = 1,
    ): MoveBoard {
        $bonus = [1 => FloorBonus::ANALYSIS_TOKEN, 4 => FloorBonus::LIGHT, 7 => FloorBonus::WILD_MOVE_KEY, 10 => FloorBonus::COMPOSE_TOKEN];
        $rooms = [];
        for ($pos = 1; $pos <= 12; $pos++) {
            $level = in_array($pos, [1, 4, 7, 10], true) ? 2 : 1;
            $rooms[$pos] = new MoveRoom(
                $pos,
                $level,
                $locks[$pos] ?? KeyType::WISDOM,
                $bonus[$pos] ?? FloorBonus::NONE,
                "ROOM$pos",
                in_array($pos, $othersAt, true),
            );
        }

        return new MoveBoard(
            $player,
            $position,
            $rooms,
            self::byId($hand),
            self::byId($tableau),
            $lights,
            $wildUp,
            $priorLv2Entries,
        );
    }

    /**
     * @param KeyType ...$keys 印刷された鍵（1 本または 2 本）
     */
    public static function card(int $id, KeyType ...$keys): MoveCard
    {
        return new MoveCard($id, 1, array_values($keys), false);
    }

    public static function tapped(int $id, KeyType ...$keys): MoveCard
    {
        return new MoveCard($id, 1, array_values($keys), true);
    }

    /**
     * @param list<MoveCard> $cards
     * @return array<int, MoveCard>
     */
    private static function byId(array $cards): array
    {
        $out = [];
        foreach ($cards as $card) {
            $out[$card->id] = $card;
        }

        return $out;
    }
}
