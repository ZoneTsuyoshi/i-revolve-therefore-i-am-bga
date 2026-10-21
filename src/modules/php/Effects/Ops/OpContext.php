<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops;

use Bga\Games\IRevolveThereforeIAm\Effects\Context\ActionAvailability;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameMutator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ComposeEnergyQuery;
use Bga\Games\IRevolveThereforeIAm\Material\EffectNode;

/**
 * 原子操作 1 つの実行に渡す文脈。
 */
final readonly class OpContext
{
    /**
     * @param array<string, scalar|null> $payload この frame を誘発したイベントの内容（`card_type` など）
     */
    public function __construct(
        public GameView $view,
        public GameMutator $mutator,
        public ComposeEnergyQuery $energy,
        public EffectNode $node,
        public int $owner,
        public ?ActionAvailability $availability = null,
        public array $payload = [],
    ) {
    }

    /** 実行者がターンプレイヤーか（OP-23・OP-24 などはターンプレイヤーだけに効く。FAQ 行 7・10）。 */
    public function isTurnPlayer(): bool
    {
        return $this->owner === $this->view->turnPlayer();
    }
}
