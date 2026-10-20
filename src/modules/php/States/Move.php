<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\States;

use Bga\GameFramework\Actions\Types\JsonParam;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\IRevolveThereforeIAm\Data\GamePhase;
use Bga\Games\IRevolveThereforeIAm\Flow\FreeActions;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 31: 移動フェイズ。経路全体と支払いをまとめて 1 回の actMove で確定する（006 §5.1）。
 * 骨格では経路を検証せず、何も動かさずに想起フェイズへ進む。
 */
class Move extends GameState
{
    use FreeActions;

    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::MOVE,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must move'),
            descriptionMyTurn: clienttranslate('${you} must move'),
            transitions: [
                Transition::MOVED => StateId::EFFECT_RESOLVE,
                Transition::GAME_END => StateId::GAME_END,
            ],
        );
    }

    public function onEnteringState(): void
    {
        $this->game->irEnterPhase(GamePhase::Move);
    }

    /**
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        $turnPlayer = $this->game->irGlobals()->turnPlayer();

        return [
            'turnPlayer' => $turnPlayer,
            'position' => $this->game->irPlayerValues()->persona($turnPlayer),
            'rooms' => $this->game->irPublicView()->corridor(),
        ];
    }

    /**
     * 経路と支払いを確定する。
     *
     * @param array<string, mixed> $plan {"rooms": [位置...], "payments": [[{"src", "card", "key"}...]...]}
     */
    #[PossibleAction]
    public function actMove(#[JsonParam(alphanum: false)] array $plan, int $activePlayerId): string
    {
        // TODO(I-06): 経路・錠前・支払い・連続・心光の逓増を検証して 1 部屋ずつ適用し（周遊ボーナス、入室記録）、
        //  12 部屋に入室したら INSTANT_MEMORY で GAME_END。移動終了時のトリガーをスタックに積む
        $this->game->irGlobals()->setResume(StateId::RECALL_START);

        return Transition::MOVED;
    }

    /**
     * 次の部屋へ 1 部屋だけ移動できるなら払って移動し、払えなければ移動しない（006 §10）。骨格では何もせず進む。
     */
    public function zombie(int $playerId): string
    {
        // TODO(I-06): 万能移動鍵トークン → 自己構成済みカードの鍵 → 心光の順で払える最小の移動を行う
        return $this->actMove([], $playerId);
    }
}
