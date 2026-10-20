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
 * 25: セットアップの仕上げ。1 番手（席順の先頭。スタートプレイヤーは BGA が席順をランダム化する。Q-18）をターンプレイヤーにして、
 * ターン番号と手番内カウンタを初期化する（006 §3.2）。
 */
class SetupFinish extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::SETUP_FINISH,
            type: StateType::GAME,
            transitions: [
                Transition::START => StateId::TURN_START,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $globals = $this->game->irGlobals();
        $first = $this->game->irSeatOrder()[0];
        $globals->setTurnPlayer($first);
        $globals->setTurnNo(0);
        $globals->resetTurnContext();
        $this->game->gamestate->changeActivePlayer($first);

        return Transition::START;
    }
}
