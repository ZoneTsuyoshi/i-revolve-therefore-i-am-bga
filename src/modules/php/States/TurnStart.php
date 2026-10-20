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
use Bga\Games\IRevolveThereforeIAm\Data\TurnContext;
use Bga\Games\IRevolveThereforeIAm\Flow\Notification;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 30: ターンの開始。ターン番号を進め、手番内カウンタ（TurnContext）を初期化し、ターンプレイヤーをアクティブにする（006 §3.2）。
 */
class TurnStart extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::TURN_START,
            type: StateType::GAME,
            transitions: [
                Transition::EFFECTS => StateId::EFFECT_RESOLVE,
                Transition::MOVE => StateId::MOVE,
            ],
        );
    }

    public function onEnteringState(): string
    {
        $globals = $this->game->irGlobals();
        $turnPlayer = $globals->turnPlayer();

        $turnNo = $globals->turnNo() + 1;
        $globals->setTurnNo($turnNo);
        $globals->setPhase(GamePhase::TurnStart);

        $context = new TurnContext();
        $context->startPos = $this->game->irPlayerValues()->persona($turnPlayer);
        $globals->setTurnContext($context);

        $this->game->irSend(Notification::turnStarted($turnPlayer, $this->game->getPlayerNameById($turnPlayer), $turnNo));
        $this->game->gamestate->changeActivePlayer($turnPlayer);

        // TODO(I-09): ターン開始時の効果（TURN_START の想い出カード、強化自我 E03〜E05、初期自我 I05 など）を
        //  スタックに積み、resume = MOVE にして EFFECTS を返す（006 §7）
        return Transition::MOVE;
    }
}
