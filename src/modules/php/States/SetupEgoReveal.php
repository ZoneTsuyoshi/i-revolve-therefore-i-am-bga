<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\IRevolveThereforeIAm\Flow\Notification;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 22: 全員の選択を一斉に公開する。最後に残った 1 枚はゲームから除外（001 §4 手順 13、Q-20）。
 */
class SetupEgoReveal extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::SETUP_EGO_REVEAL,
            type: StateType::GAME,
            transitions: [
                Transition::DONE => StateId::SETUP_MEEPLE_NEXT,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $revealed = $this->game->irEgoDraft()->reveal($this->game->irSeatOrder());
        $this->game->irSend(Notification::initialEgosRevealed($revealed));

        return Transition::DONE;
    }
}
