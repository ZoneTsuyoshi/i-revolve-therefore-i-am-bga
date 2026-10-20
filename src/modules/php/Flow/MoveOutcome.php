<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Material\KeyType;

/**
 * 検証済みの移動全体。`steps` が空なら、払えないために移動しなかった（Q-38）。
 */
final readonly class MoveOutcome
{
    /**
     * @param list<MoveStep> $steps
     * @param list<KeyType> $keyKinds 支払いで使った鍵の種類（印刷された非万能鍵。CD-02）
     */
    public function __construct(
        public array $steps,
        public array $keyKinds = [],
        public int $wildTokensUsed = 0,
        public int $lightUses = 0,
    ) {
    }

    public function moved(): bool
    {
        return $this->steps !== [];
    }
}
