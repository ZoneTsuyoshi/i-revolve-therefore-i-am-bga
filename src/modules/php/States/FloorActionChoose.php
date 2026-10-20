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
use Bga\Games\IRevolveThereforeIAm\Flow\FreeActions;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 51: 床アクションか代替かの選択（「/」の分岐を含む。006 §5.3）。骨格では 50 が EXECUTE へ直行するので入らない。
 */
class FloorActionChoose extends GameState
{
    use FreeActions;

    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::FLOOR_ACTION_CHOOSE,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must choose a floor action'),
            descriptionMyTurn: clienttranslate('${you} must choose a floor action'),
            transitions: [
                Transition::CHOSEN => StateId::EFFECT_RESOLVE,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return [
            'turnPlayer' => $this->game->irGlobals()->turnPlayer(),
            'options' => [], // TODO(I-07): 選択肢の一覧（床アクションの分岐、floor_alternatives）
        ];
    }

    #[PossibleAction]
    public function actChooseFloorAction(string $kind, int $activePlayerId): string
    {
        // TODO(I-07): kind が選択肢にあることを検査し、床アクションの frame を積む。N10 floorActionChosen を送る
        $this->game->irGlobals()->setResume(StateId::CONSTRUCT);

        return Transition::CHOSEN;
    }

    /**
     * 床アクション自体（代替ではないもの。分岐は先頭）を選ぶ（006 §10）。
     */
    public function zombie(int $playerId): string
    {
        return $this->actChooseFloorAction('', $playerId);
    }
}
