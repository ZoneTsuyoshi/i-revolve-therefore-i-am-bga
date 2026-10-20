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
 * 21: 回ってきた山から初期自我を 1 枚秘密裏に選ぶ（001 §4 手順 13）。候補は選択者の _private にだけ入れる（007 §9）。
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
        $candidates = $this->game->irEgoDraft()->candidates();

        return [
            'pickerId' => $activePlayerId,
            'packSize' => count($candidates),
            '_private' => [
                $activePlayerId => ['candidates' => $candidates],
            ],
        ];
    }

    #[PossibleAction]
    public function actPickInitialEgo(string $egoId, int $activePlayerId): string
    {
        $draft = $this->game->irEgoDraft();
        if (!in_array($egoId, $draft->candidates(), true)) {
            throw new UserException(clienttranslate('This initial ego card cannot be chosen'));
        }
        $packSize = $draft->pick($this->game->irSeatOrder(), $activePlayerId, $egoId);
        $this->game->irSend(Notification::initialEgoPicked(
            $activePlayerId,
            $this->game->getPlayerNameById($activePlayerId),
            $packSize,
            $egoId,
        ));

        return Transition::PICKED;
    }

    /**
     * 候補からランダムに 1 枚選ぶ（作者の指示。006 §10 の「ID 昇順の先頭」から変更）。
     */
    public function zombie(int $playerId): string
    {
        return $this->actPickInitialEgo($this->game->irEgoDraft()->randomCandidate(), $playerId);
    }
}
