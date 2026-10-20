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
 * 骨格では誰も選ばずに公開（22）へ進む。
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
        // TODO(I-04): setup_step から次の選択者を逆順（SeatOrder::reversed）で決め、changeActivePlayer して PICK を返す。
        //  全員が選び終えたら REVEAL
        return Transition::REVEAL;
    }
}
