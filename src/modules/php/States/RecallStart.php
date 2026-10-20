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
 * 40: 想起フェイズの開始。部屋の想い出カードを表向きにし、発動者ごとの効果をスタックに積む（006 §5.2）。
 * 骨格では何も積まずに 70 へ進む。
 */
class RecallStart extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::RECALL_START,
            type: StateType::GAME,
            transitions: [
                Transition::RESOLVE => StateId::EFFECT_RESOLVE,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $this->game->irEnterPhase(GamePhase::Recall);

        // TODO(I-07): ターンプレイヤーの部屋の想い出カードを公開（N09）し、発動者の列
        //  （SeatOrder::startingFrom）ごとに RECALL_ACTIVATION、続けて ACQUIRE_ROOM_CARD と HAND_LIMIT_CHECK を積む
        $this->game->irGlobals()->setResume(StateId::FLOOR_ACTION_START);

        return Transition::RESOLVE;
    }
}
