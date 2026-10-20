<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Context;

use Bga\Games\IRevolveThereforeIAm\Material\Event;

/**
 * 効果の解決中に起きたイベント（008 §4.1）。エンジンが集めて呼び出し側に返し、トリガーの収集（実装順 5）が使う。
 *
 * ペイロードは JSON にできる値だけ（`effect_frame.frame_payload` に保存する。008 §10.1）。
 */
final readonly class GameEvent
{
    /**
     * @param int $playerId イベントを起こした人
     * @param array<string, scalar|null> $payload EV-07: `count`・`origin`、EV-08: `element`（`DRAW` / `OPEN` / `LIGHT`）など
     */
    public function __construct(
        public Event $event,
        public int $playerId,
        public array $payload = [],
    ) {
    }
}
