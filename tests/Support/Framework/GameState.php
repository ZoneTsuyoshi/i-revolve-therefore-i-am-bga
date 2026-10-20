<?php

declare(strict_types=1);

namespace Bga\GameFramework\States;

use Bga\GameFramework\StateType;

/**
 * BGA フレームワークの最小のスタブ（PHPUnit 専用）。コンストラクタの宣言（ID・種類・遷移など）を保持するだけ。
 */
abstract class GameState
{
    /**
     * @param array<string, int>|null $transitions
     */
    public function __construct(
        object $game,
        public readonly int $id,
        public readonly StateType $type,
        public readonly ?string $name = null,
        public readonly string $description = '',
        public readonly string $descriptionMyTurn = '',
        public readonly ?array $transitions = null,
        public readonly bool $updateGameProgression = false,
        public readonly ?int $initialPrivate = null,
    ) {
    }
}
