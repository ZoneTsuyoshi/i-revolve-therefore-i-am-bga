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
use Bga\Games\IRevolveThereforeIAm\Data\GamePhase;
use Bga\Games\IRevolveThereforeIAm\Flow\FreeActions;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 60: 自己構成フェイズの窓。自己構成アクション（フェイズの 1 回）、自己構成トークン、精神分析トークン、フリーアクションを
 * 任意の順に使い、actEndConstruct で終える（006 §5.5）。
 */
class Construct extends GameState
{
    use FreeActions;

    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::CONSTRUCT,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} may compose, analyze, or end the compose phase'),
            descriptionMyTurn: clienttranslate('${you} may compose, analyze, or end the compose phase'),
            transitions: [
                Transition::ACT => StateId::EFFECT_RESOLVE,
                Transition::END => StateId::EFFECT_RESOLVE,
                Transition::GAME_END => StateId::GAME_END,
            ],
        );
    }

    public function onEnteringState(): void
    {
        $this->game->irEnterPhase(GamePhase::Construct);
    }

    /**
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        $turnPlayer = $this->game->irGlobals()->turnPlayer();
        $values = $this->game->irPlayerValues();

        return [
            'turnPlayer' => $turnPlayer,
            'phaseComposeUsed' => $this->game->irGlobals()->turnContext()->phaseComposeUsed,
            'composeTokens' => $values->composeTokens($turnPlayer),
            'analysisTokens' => $values->analysisTokens($turnPlayer),
        ];
    }

    /**
     * フェイズの自己構成アクション（1 回だけ）を始める。
     */
    #[PossibleAction]
    public function actStartCompose(int $activePlayerId): string
    {
        // TODO(I-07): phase_compose_used を検査して立て、COMPOSE の frame（context = PHASE）を積む。resume = CONSTRUCT
        throw new UserException(clienttranslate('Composing is not available yet'));
    }

    #[PossibleAction]
    public function actUseComposeToken(int $activePlayerId): string
    {
        // TODO(I-07): 自己構成トークンが 1 以上のとき消費して COMPOSE の frame（context = TOKEN）を積む。resume = CONSTRUCT
        throw new UserException(clienttranslate('Composing is not available yet'));
    }

    #[PossibleAction]
    public function actUseAnalysisToken(int $activePlayerId): string
    {
        // TODO(I-07): 精神分析トークンが 1 以上で精神分析できるとき ANALYZE の frame（context = TOKEN）を積む。resume = CONSTRUCT
        throw new UserException(clienttranslate('Analysis is not available yet'));
    }

    /**
     * 自己構成フェイズを終える。手札上限の確認を積んでクリーンアップへ（001 §12、Q-30）。
     */
    #[PossibleAction]
    public function actEndConstruct(int $activePlayerId): string
    {
        // TODO(I-07): HAND_LIMIT_CHECK の frame を積む
        $this->game->irGlobals()->setResume(StateId::CLEANUP);

        return Transition::END;
    }

    /**
     * 何も使わずにフェイズを終える（006 §10）。
     */
    public function zombie(int $playerId): string
    {
        return $this->actEndConstruct($playerId);
    }
}
