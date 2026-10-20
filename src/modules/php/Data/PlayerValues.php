<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

/**
 * player テーブルの追加列のアクセサ（仕様書 007 §7.1）。player 行を丸ごと JSON にして返さない。
 * トークンは負にならない。
 */
final class PlayerValues
{
    public const COLUMN_LIGHT = 'player_light';
    public const COLUMN_WILD_UP = 'player_wild_up';
    public const COLUMN_WILD_USED = 'player_wild_used';
    public const COLUMN_COMPOSE = 'player_compose_tok';
    public const COLUMN_ANALYSIS = 'player_analysis_tok';

    private const TOKEN_COLUMNS = [
        self::COLUMN_LIGHT,
        self::COLUMN_WILD_UP,
        self::COLUMN_WILD_USED,
        self::COLUMN_COMPOSE,
        self::COLUMN_ANALYSIS,
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * 席順（player_no）のプレイヤー ID。手番順（昇順）。ドラフトと人格配置の逆順は降順。
     *
     * @return list<int>
     */
    public function playerIds(): array
    {
        $rows = $this->db->rows('SELECT `player_id` FROM `player` ORDER BY `player_no` ASC');
        return array_map(static fn(array $r): int => (int) $r['player_id'], $rows);
    }

    /**
     * 人格の位置（0 = 未配置、1〜12）。
     */
    public function persona(int $playerId): int
    {
        return (int) $this->column($playerId, 'player_persona');
    }

    public function setPersona(int $playerId, int $pos): void
    {
        if ($pos < 0 || $pos > RoomRepository::ROOM_COUNT) {
            throw new \InvalidArgumentException("invalid persona position: $pos");
        }
        $this->update($playerId, "`player_persona` = $pos");
    }

    /**
     * 精神カード（R1A 等。NULL = 階層 0）。変更は SpiritRepository::advance を通す（M088 など特別な移動を除く）。
     */
    public function spirit(int $playerId): ?string
    {
        $v = $this->column($playerId, 'player_spirit');
        return $v === null ? null : (string) $v;
    }

    public function light(int $playerId): int
    {
        return (int) $this->column($playerId, self::COLUMN_LIGHT);
    }

    public function wildUp(int $playerId): int
    {
        return (int) $this->column($playerId, self::COLUMN_WILD_UP);
    }

    public function wildUsed(int $playerId): int
    {
        return (int) $this->column($playerId, self::COLUMN_WILD_USED);
    }

    public function composeTokens(int $playerId): int
    {
        return (int) $this->column($playerId, self::COLUMN_COMPOSE);
    }

    public function analysisTokens(int $playerId): int
    {
        return (int) $this->column($playerId, self::COLUMN_ANALYSIS);
    }

    /**
     * トークンを増減する。結果が負になる場合は例外。
     *
     * @param string $column self::TOKEN_COLUMNS のいずれか（実行時に検査する）
     * @return int 更新後の値
     */
    public function addToken(int $playerId, string $column, int $delta): int
    {
        if (!in_array($column, self::TOKEN_COLUMNS, true)) {
            throw new \InvalidArgumentException("not a token column: $column");
        }
        $value = (int) $this->column($playerId, $column) + $delta;
        $this->setToken($playerId, $column, $value);
        return $value;
    }

    /**
     * トークンを直接書く。負は例外。
     *
     * @param string $column self::TOKEN_COLUMNS のいずれか（実行時に検査する）
     */
    public function setToken(int $playerId, string $column, int $value): void
    {
        if (!in_array($column, self::TOKEN_COLUMNS, true)) {
            throw new \InvalidArgumentException("not a token column: $column");
        }
        if ($value < 0) {
            throw new \LogicException("$column must not be negative (player $playerId: $value)");
        }
        $this->update($playerId, "`$column` = $value");
    }

    /**
     * 万能移動鍵トークンを使う（表 → 裏）。表が足りなければ例外。
     */
    public function useWild(int $playerId, int $count = 1): void
    {
        $this->addToken($playerId, self::COLUMN_WILD_UP, -$count);
        $this->addToken($playerId, self::COLUMN_WILD_USED, $count);
    }

    private function column(int $playerId, string $column): mixed
    {
        $rows = $this->db->rows("SELECT `$column` AS v FROM `player` WHERE `player_id` = $playerId");
        if ($rows === []) {
            throw new \InvalidArgumentException("unknown player: $playerId");
        }
        return $rows[0]['v'];
    }

    private function update(int $playerId, string $assignment): void
    {
        $this->db->execute("UPDATE `player` SET $assignment WHERE `player_id` = $playerId");
    }
}
