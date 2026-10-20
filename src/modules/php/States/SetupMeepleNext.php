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
 * 骨格では誰も置かずに 25 へ進む（人格の位置は 0 = 未配置のまま）。
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

        // TODO(I-04): setup_step から次の配置者を逆順で決め、changeActivePlayer して PLACE を返す。全員置いたら DONE
        return Transition::DONE;
    }
}
