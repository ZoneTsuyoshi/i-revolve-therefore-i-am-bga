<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 20: 初期自我のドラフト（001 §4 手順 13）。逆順（手番順の最後 → 1 番手）で次に選ぶ人を決め、全員選び終えたら 22 へ。
 * 進み具合は setup_step（選び終えた人数）。
 */
class SetupEgoDraftNext extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::SETUP_EGO_DRAFT_NEXT,
            type: StateType::GAME,
            transitions: [
                Transition::PICK => StateId::SETUP_EGO_PICK,
                Transition::REVEAL => StateId::SETUP_EGO_REVEAL,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $picker = $this->game->irEgoDraft()->nextPicker($this->game->irSeatOrder());
        if ($picker === null) {
            return Transition::REVEAL;
        }
        $this->game->gamestate->changeActivePlayer($picker);

        return Transition::PICK;
    }
}
