<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\IRevolveThereforeIAm\Data\GamePhase;
use Bga\Games\IRevolveThereforeIAm\Flow\FreeActions;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 81: ターン終了時の窓。ターン終了時にだけ条件を満たせる目標（O004）を獲得するため（006 §6.3）。
 * 80 が ASK を返したときだけ入る。骨格では 80 が SKIP を返すので入らない。
 */
class TurnEnd extends GameState
{
    use FreeActions;

    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::TURN_END,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} may claim a goal card before the turn ends'),
            descriptionMyTurn: clienttranslate('${you} may claim a goal card before the turn ends'),
            transitions: [
                Transition::DONE => StateId::NEXT_TURN,
                Transition::GAME_END => StateId::GAME_END,
            ],
        );
    }

    public function onEnteringState(): void
    {
        $this->game->irGlobals()->setPhase(GamePhase::TurnEnd);
    }

    /**
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return ['turnPlayer' => $this->game->irGlobals()->turnPlayer()];
    }

    #[PossibleAction]
    public function actEndTurn(int $activePlayerId): string
    {
        return Transition::DONE;
    }

    /**
     * 目標を獲得せずにターンを終える（006 §10）。
     */
    public function zombie(int $playerId): string
    {
        return $this->actEndTurn($playerId);
    }
}
