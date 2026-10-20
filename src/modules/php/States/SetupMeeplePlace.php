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
use Bga\GameFramework\UserException;
use Bga\Games\IRevolveThereforeIAm\Flow\Notification;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 24: まだミープルがいない四隅（レベル 2 の部屋）から 1 つ選んで置く（001 §4 手順 14）。
 */
class SetupMeeplePlace extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::SETUP_MEEPLE_PLACE,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must place their persona in a corner room'),
            descriptionMyTurn: clienttranslate('${you} must place your persona in a corner room'),
            transitions: [
                Transition::PLACED => StateId::SETUP_MEEPLE_NEXT,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return [
            'freeCorners' => $this->game->irPersonaPlacement()->freeCorners($this->game->irSeatOrder()),
        ];
    }

    #[PossibleAction]
    public function actPlaceMeeple(int $pos, int $activePlayerId): string
    {
        $seats = $this->game->irSeatOrder();
        $placement = $this->game->irPersonaPlacement();
        if (!in_array($pos, $placement->freeCorners($seats), true)) {
            throw new UserException(clienttranslate('You cannot place your persona there'));
        }
        $roomName = $placement->place($seats, $activePlayerId, $pos);
        $this->game->irSend(Notification::personaPlaced(
            $activePlayerId,
            $this->game->getPlayerNameById($activePlayerId),
            $pos,
            $roomName,
        ));

        return Transition::PLACED;
    }

    /**
     * 空いている四隅のうち位置番号が最小のものに置く（006 §10）。
     */
    public function zombie(int $playerId): string
    {
        $corners = $this->getArgs()['freeCorners'];

        return $this->actPlaceMeeple($corners[0] ?? 0, $playerId);
    }
}
