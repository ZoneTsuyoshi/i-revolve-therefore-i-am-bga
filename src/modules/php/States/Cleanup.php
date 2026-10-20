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
 * 骨格では何もせず 82 へ進む（O004 の窓 81 にも入らない）。
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

        // TODO(I-08): a 自己構成済みカードを縦に戻す、b 万能移動鍵トークンを表に戻す、c 想い出カードのない部屋に補充、
        //  d 場札を 6 枚まで補充。山札が足りなければ補充できるだけ補充して DECK_OUT で GAME_END（Q-05・Q-24）。
        //  O004 を獲得できる状況（006 §6.3）なら ASK
        return Transition::SKIP;
    }
}
