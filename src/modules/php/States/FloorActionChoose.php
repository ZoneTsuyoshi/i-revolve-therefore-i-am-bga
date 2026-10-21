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
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Flow\FreeActions;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;
use Bga\Games\IRevolveThereforeIAm\Material\FloorCards;

/**
 * 51: 床アクションか代替かの選択（「/」の分岐を含む。006 §5.3）。50 が選択肢を 2 つ以上作ったときだけ入る。
 * 実行は Flow/FloorActionPhase が frame を積み、70 が解決する。
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
                Transition::GAME_END => StateId::GAME_END,
            ],
        );
    }

    /**
     * 選択肢の一覧（`kind`: `FLOOR` / `FLOOR_A` / `FLOOR_B`（「/」の分岐。分岐の順）/ `ALT_ANALYSIS` / `ALT_COMPOSE`（想起効果の代替。
     * `discount` はコスト・エネルギーの割引））と、現在の部屋の床カード。
     *
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        $floor = $this->game->irTurn()->floor;

        return [
            'turnPlayer' => $this->game->irGlobals()->turnPlayer(),
            'floor' => sprintf('F%02d', $floor->floorNumber()),
            'roomName' => FloorCards::get(sprintf('F%02d', $floor->floorNumber()))->roomName,
            'options' => $floor->options(),
        ];
    }

    /**
     * 床アクションか代替を選んで実行する。`kind` が空文字なら床アクション自体（分岐は先頭）。
     */
    #[PossibleAction]
    public function actChooseFloorAction(string $kind, int $activePlayerId): string
    {
        try {
            $notices = $this->game->irTurn()->floor->choose($kind);
        } catch (EffectViolation $e) {
            throw new UserException($e->getMessage());
        }
        $this->game->irSend(...$notices);

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
