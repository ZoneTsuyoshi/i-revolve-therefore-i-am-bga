<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\IRevolveThereforeIAm\Data\GamePhase;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 80: クリーンアップ a〜d（001 §13）と山札切れの判定（006 §5.6）。
 * 中身は Flow/CleanupPhase。O004 を獲得できるときだけ窓 81 へ入る（006 §6.3）。
 */
class Cleanup extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::CLEANUP,
            type: StateType::GAME,
            transitions: [
                Transition::ASK => StateId::TURN_END,
                Transition::SKIP => StateId::NEXT_TURN,
                Transition::GAME_END => StateId::GAME_END,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $this->game->irEnterPhase(GamePhase::Cleanup);

        $transition = $this->game->irCleanup()->run();
        $this->game->irSend(...$this->game->irTurn()->mutator->drain());

        return $transition;
    }
}
