<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\States;

use Bga\GameFramework\StateType;
use Bga\Games\IRevolveThereforeIAm\Game;

class NextPlayer extends \Bga\GameFramework\States\GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: 90,
            type: StateType::GAME,
            updateGameProgression: true,
        );
    }

    /**
     * Game state action, example content.
     *
     * The onEnteringState method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
     */
    public function onEnteringState(int $activePlayerId)
    {

        // Give some extra time to the active player when he completed an action
        $this->game->giveExtraTime($activePlayerId);

        $this->game->activeNextPlayer();

        // Go to another gamestate
        $gameEnd = false; // Here, we would detect if the game is over to make the appropriate transition
        // 雛形の仮実装（常に false）。I-03 以降で本物の終了判定に置き換える
        // @phpstan-ignore if.alwaysFalse
        if ($gameEnd) {
            return EndScore::class;
        } else {
            return PlayerTurn::class;
        }
    }
}
