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
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Flow\Notification;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;
use Bga\Games\IRevolveThereforeIAm\Material\Event;

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

        $this->game->irStats()->turnStarted($turnPlayer);
        $this->game->irSend(Notification::turnStarted($turnPlayer, $this->game->getPlayerNameById($turnPlayer), $turnNo));
        $this->game->gamestate->changeActivePlayer($turnPlayer);
        // ターンプレイヤーが変わり、ターンの記録が初期化されたので、目標の状況（獲得できるか）を本人に送り直す（N23）
        $this->game->irSend(...$this->game->irObjectiveReporter()->all());

        // ターン開始時の効果（EV-01。E03〜E05、I05、M052〜M057、M072）。効果 1 つの解決ごとに手札上限を確認し（008 §6.9）、
        // 終わったら 31 へ進む
        $emitted = $this->game->irTurn()->triggers->emit([new GameEvent(Event::TURN_START, $turnPlayer)]);
        if ($emitted) {
            $globals->setResume(StateId::MOVE);

            return Transition::EFFECTS;
        }

        return Transition::MOVE;
    }
}
