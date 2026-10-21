<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

/**
 * 原子操作（仕様書 008 §3.1。26 種）。値は仕様書の ID。
 */
enum Op: string
{
    case DRAW_DECK = 'OP-01';
    case GAIN_OPEN = 'OP-02';
    case GAIN_LIGHT = 'OP-03';
    case GAIN_TOKEN = 'OP-04';
    case CLAIM_SPIRIT_LIGHT = 'OP-05';
    case DISCARD_HAND = 'OP-06';
    case DISCARD_FOR_LIGHT = 'OP-07';
    case SWAP_HAND_OPEN = 'OP-08';
    case SWAP_COMPOSED_HAND = 'OP-09';
    case COMPOSE = 'OP-10';
    case ANALYZE = 'OP-11';
    case ADVANCE_TIER = 'OP-12';
    case GAIN_ENHANCED_EGO = 'OP-13';
    case WIN = 'OP-14';
    case MOVE_NEXT_ROOM = 'OP-15';
    case WARP = 'OP-16';
    case SWAP_ROOM_MEMORY = 'OP-17';
    case SWAP_ROOM_FLOOR = 'OP-18';
    case SWAP_HAND_ROOM = 'OP-19';
    case PLACE_UNDER_FLOOR = 'OP-20';
    case COLLECT_UNDER_FLOOR = 'OP-21';
    case SET_TURN_COMPOSE_DISCOUNT = 'OP-22';
    case ADD_NEXT_COMPOSE_DISCOUNT = 'OP-23';
    case RECORD_FLOOR_ALTERNATIVE = 'OP-24';
    case RECALL_ACTIVATE = 'OP-25';
    case ACQUIRE_ROOM_CARD = 'OP-26';

    /**
     * 操作が触る資源（008 §3.1 の「触る資源」の列。§6.3.2 の記法）。
     * OP-10 の `ND w` は自己構成アクションの文脈のときだけだが、衝突判定は保守的に常に含める。
     * OP-25 は発動する想起効果で決まるので、ここでは空（呼び出し側が RC の資源を足す）。
     *
     * @return list<ResourceTouch>
     */
    public function touches(): array
    {
        return ResourceTouch::parseList(match ($this) {
            self::DRAW_DECK => 'D+ H+',
            self::GAIN_OPEN => 'O+ H+',
            self::GAIN_LIGHT => 'L+',
            self::GAIN_TOKEN => 'T+',
            self::CLAIM_SPIRIT_LIGHT => 'L+',
            self::DISCARD_HAND => 'Hw',
            self::DISCARD_FOR_LIGHT => 'Hw L+',
            self::SWAP_HAND_OPEN => 'Hw Ow',
            self::SWAP_COMPOSED_HAND => 'Hw Bw',
            self::COMPOSE => 'Hw Bw NDw',
            self::ANALYZE => 'Hw Lw Sw',
            self::ADVANCE_TIER => 'Sw L+',
            self::GAIN_ENHANCED_EGO => 'Gw',
            self::WIN => '',
            self::MOVE_NEXT_ROOM => 'Pw Ew',
            self::WARP => 'Pw',
            self::SWAP_ROOM_MEMORY => 'RMw',
            self::SWAP_ROOM_FLOOR => 'RFw',
            self::SWAP_HAND_ROOM => 'Hw RMw Pr',
            self::PLACE_UNDER_FLOOR => 'Fw',
            self::COLLECT_UNDER_FLOOR => 'Fw H+',
            self::SET_TURN_COMPOSE_DISCOUNT => 'TDw',
            self::ADD_NEXT_COMPOSE_DISCOUNT => 'ND+',
            self::RECORD_FLOOR_ALTERNATIVE => 'FAw',
            self::RECALL_ACTIVATE => '',
            self::ACQUIRE_ROOM_CARD => 'RMw H+',
        });
    }

    /**
     * この操作が、解決スタックに新しい frame を積むか（OP-12 の到着処理、OP-25 の想起効果の発動）。
     * 積んだ後は、続きの節より先に積んだ frame を解決するため、エンジンは一度止まる（`EngineResult::$yielded`）。
     */
    public function pushesFrames(): bool
    {
        return match ($this) {
            self::ADVANCE_TIER, self::RECALL_ACTIVATE => true,
            default => false,
        };
    }

    /**
     * この操作の直後に勝利判定を呼ぶか（008 §6.8。自己構成・移動・フリーアクション側の呼び出しは含めない）。
     */
    public function checksVictory(): bool
    {
        return match ($this) {
            self::DRAW_DECK, self::GAIN_OPEN, self::GAIN_LIGHT, self::GAIN_TOKEN, self::CLAIM_SPIRIT_LIGHT,
            self::DISCARD_HAND, self::DISCARD_FOR_LIGHT, self::SWAP_HAND_OPEN, self::SWAP_COMPOSED_HAND,
            self::ADVANCE_TIER, self::WIN, self::SWAP_HAND_ROOM, self::COLLECT_UNDER_FLOOR => true,
            default => false,
        };
    }
}
