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
use Bga\GameFramework\UserException;
use Bga\Games\IRevolveThereforeIAm\Flow\FreeActions;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 61: 自己構成アクションの実行。コンテキスト（フェイズ・床・トークン・ボーナス・代替）は DB の frame が持つ（006 §5.5）。
 * 骨格では 70 が入れないので入らない。
 */
class Compose extends GameState
{
    use FreeActions;

    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::COMPOSE,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must compose a memory card'),
            descriptionMyTurn: clienttranslate('${you} must compose a memory card'),
            transitions: [
                Transition::DONE => StateId::EFFECT_RESOLVE,
                Transition::GAME_END => StateId::GAME_END,
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
            'context' => null, // TODO(I-07): frame の context（PHASE / TOKEN / FLOOR / BONUS_LV1 / BONUS_ANY / EFFECT）、割引、optional
        ];
    }

    /**
     * @param array<string, mixed> $payment エネルギーの支払い（手札を捨てる、心光の行使など）
     */
    #[PossibleAction]
    public function actCompose(int $cardId, #[JsonParam(alphanum: false)] array $payment, int $activePlayerId): string
    {
        // TODO(I-07): 割引を計算して支払いを検証し、手札から自己構成済みへ。即時効果・起動効果のトリガーを積み、
        //  自己構成スコアの更新と 20 点の判定を行う
        throw new UserException(clienttranslate('Composing is not available yet'));
    }

    /**
     * I03（建築家）: 自己構成する代わりに、自己構成済みのカードと手札の新しいカードを入れ替える。
     */
    #[PossibleAction]
    public function actComposeArchitect(int $tableauCardId, int $newCardId, int $activePlayerId): string
    {
        // TODO(I-09): I03 の代替自己構成
        throw new UserException(clienttranslate('Composing is not available yet'));
    }

    /**
     * 自己構成を行わずに戻る（何も消費していないとき）。
     */
    #[PossibleAction]
    public function actCancel(int $activePlayerId): string
    {
        // TODO(I-07): frame を捨てる（cancel_to があれば 51 へ戻る）
        return Transition::DONE;
    }

    /**
     * 行わない（006 §10）。
     */
    public function zombie(int $playerId): string
    {
        return $this->actCancel($playerId);
    }
}
