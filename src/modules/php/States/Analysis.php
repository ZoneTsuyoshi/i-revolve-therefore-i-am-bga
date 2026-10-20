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
 * 62: 精神分析アクションの実行（006 §5.4）。骨格では 70 が入れないので入らない。
 */
class Analysis extends GameState
{
    use FreeActions;

    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::ANALYSIS,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must perform an analysis'),
            descriptionMyTurn: clienttranslate('${you} must perform an analysis'),
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
            'context' => null, // TODO(I-07): frame の context（FLOOR / TOKEN）、コスト割引、キャンセル可否、進める先の精神カード
        ];
    }

    /**
     * @param array<string, mixed> $payment 支払い（TABLEAU / HAND / LIGHT。万能移動鍵トークンは不可）
     */
    #[PossibleAction]
    public function actAnalyze(string $destination, #[JsonParam(alphanum: false)] array $payment, int $activePlayerId): string
    {
        // TODO(I-07): 支払いを検証して階層を 1 つ上げ、心光の獲得・ボーナス・トリガーを積む。第五階層なら APEX_OF_PSYCHE で GAME_END
        throw new UserException(clienttranslate('Analysis is not available yet'));
    }

    /**
     * 何も消費せずに戻る。
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
