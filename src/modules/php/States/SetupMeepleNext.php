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
 * 23: ミープル（人格）の配置（001 §4 手順 14）。逆順で次の人を決め、全員置いたら 25 へ。
 */
class SetupMeepleNext extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::SETUP_MEEPLE_NEXT,
            type: StateType::GAME,
            transitions: [
                Transition::PLACE => StateId::SETUP_MEEPLE_PLACE,
                Transition::DONE => StateId::SETUP_FINISH,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $this->game->irGlobals()->setPhase(GamePhase::SetupPersona);

        $placer = $this->game->irPersonaPlacement()->nextPlacer($this->game->irSeatOrder());
        if ($placer === null) {
            return Transition::DONE;
        }
        $this->game->gamestate->changeActivePlayer($placer);

        return Transition::PLACE;
    }
}
