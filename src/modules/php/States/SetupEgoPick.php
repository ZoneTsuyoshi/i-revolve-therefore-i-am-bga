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
use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 21: 回ってきた山から初期自我を 1 枚秘密裏に選ぶ（001 §4 手順 13）。候補は選択者の _private にだけ入れる（007 §9）。
 * 骨格では 20 が REVEAL へ直行するので入らない。
 */
class SetupEgoPick extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::SETUP_EGO_PICK,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must choose an initial ego card'),
            descriptionMyTurn: clienttranslate('${you} must choose an initial ego card'),
            transitions: [
                Transition::PICKED => StateId::SETUP_EGO_DRAFT_NEXT,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getArgs(int $activePlayerId): array
    {
        $pack = $this->game->irEgos()->inLoc(EgoRepository::PACK);

        return [
            'pickerId' => $activePlayerId,
            'packSize' => count($pack),
            '_private' => [
                $activePlayerId => ['candidates' => array_map(static fn(array $e): string => $e['id'], $pack)],
            ],
        ];
    }

    #[PossibleAction]
    public function actPickInitialEgo(string $egoId, int $activePlayerId): string
    {
        // TODO(I-04): egoId が PACK にあることを検査し、PICKED に移して残りを次の人に回す。N01 initialEgoPicked を送る
        return Transition::PICKED;
    }

    /**
     * 候補の先頭（カード ID の昇順）を選ぶ（006 §10）。
     */
    public function zombie(int $playerId): string
    {
        $pack = $this->game->irEgos()->inLoc(EgoRepository::PACK);
        $ids = array_map(static fn(array $e): string => $e['id'], $pack);
        sort($ids);
        $this->actPickInitialEgo($ids[0] ?? '', $playerId);

        return Transition::PICKED;
    }
}
