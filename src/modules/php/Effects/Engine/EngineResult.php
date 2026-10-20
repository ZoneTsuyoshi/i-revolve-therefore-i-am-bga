<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Engine;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;

/**
 * `EffectEngine` が frame を進めた結果（006 §7.2 の「自動で済んだ / 選択が要る」）。
 */
final readonly class EngineResult
{
    /**
     * @param ChoiceRequest|null $choice 選択が要るとき、その内容（frame は待機）。null なら frame は最後まで解決した
     * @param list<GameEvent> $events 解決中に起きたイベント（トリガーの収集に渡す）
     */
    public function __construct(
        public ?ChoiceRequest $choice,
        public array $events = [],
    ) {
    }

    public function isDone(): bool
    {
        return $this->choice === null;
    }
}
