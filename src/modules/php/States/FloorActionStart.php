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
 * 50: 床アクションフェイズ。ターンプレイヤーの現在の部屋の床アクションと、想起で得た代替から選択肢を作る（006 §5.3）。
 * 選ぶ余地がなければ自動で実行する（EXECUTE）。骨格では何も実行せずに 70 へ進む。
 */
class FloorActionStart extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::FLOOR_ACTION_START,
            type: StateType::GAME,
            transitions: [
                Transition::CHOOSE => StateId::FLOOR_ACTION_CHOOSE,
                Transition::EXECUTE => StateId::EFFECT_RESOLVE,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $this->game->irEnterPhase(GamePhase::Floor);

        // TODO(I-07): 選択肢（床アクションの分岐と floor_alternatives）を作り、複数あれば CHOOSE。
        //  1 つなら床アクションの frame を積んで EXECUTE
        $this->game->irGlobals()->setResume(StateId::CONSTRUCT);

        return Transition::EXECUTE;
    }
}
