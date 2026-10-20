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
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 24: まだミープルがいない四隅（レベル 2 の部屋）から 1 つ選んで置く（001 §4 手順 14）。
 * 骨格では 23 が DONE へ直行するので入らない。
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
        $taken = [];
        foreach ($this->game->irSeatOrder() as $playerId) {
            $taken[] = $this->game->irPlayerValues()->persona($playerId);
        }

        return [
            'freeCorners' => array_values(array_diff(RoomRepository::LEVEL2_POSITIONS, $taken)),
        ];
    }

    #[PossibleAction]
    public function actPlaceMeeple(int $pos, int $activePlayerId): string
    {
        // TODO(I-04): pos が空いている四隅であることを検査し、人格を置く。N03 personaPlaced を送る
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
