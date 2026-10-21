<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\IRevolveThereforeIAm\Flow\SeatOrder;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 82: 「このターン」限りの効果と手番内カウンタを破棄し、次のターンプレイヤーを決める（006 §5.6）。進行度を更新する状態。
 * 次のターンプレイヤーは自前の席順で決める（activeNextPlayer が離脱者を飛ばすかは未確認。006 §14 V-10）。
 * ターンプレイヤーをアクティブにするのは 30 TurnStart。
 */
class NextTurn extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::NEXT_TURN,
            type: StateType::GAME,
            updateGameProgression: true,
            transitions: [
                Transition::NEXT => StateId::TURN_START,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $globals = $this->game->irGlobals();
        $previous = $globals->turnPlayer();

        // 手札上限などで待たせた時間を考慮して、手番を終えたプレイヤーに追加時間を与える
        $this->game->giveExtraTime($previous);

        $this->game->irStats()->turnFinished($globals->turnContext()->roomsEnteredCount);
        $globals->resetTurnContext();
        $globals->setTurnPlayer(SeatOrder::next($this->game->irSeatOrder(), $previous));

        return Transition::NEXT;
    }
}
