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
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Flow\FreeActions;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 60: 自己構成フェイズの窓。自己構成アクション（フェイズの 1 回）、自己構成トークン、精神分析トークン、フリーアクションを
 * 任意の順に使い、actEndConstruct で終える（006 §5.5）。中身は Flow/ConstructPhase。
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
     * フェイズの自己構成を使用済みか、保有トークンの数、トークンで実行できる状況か（空撃ち不可。Q-28）。
     *
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return $this->game->irTurn()->construct->args($this->game->irGlobals()->turnPlayer());
    }

    /**
     * フェイズの自己構成アクション（1 回だけ）を始める。確定は 61 の actCompose。
     */
    #[PossibleAction]
    public function actStartCompose(int $activePlayerId): string
    {
        return $this->irStart(fn() => $this->game->irTurn()->construct->startPhaseCompose($activePlayerId));
    }

    /**
     * 自己構成トークンを使って追加の自己構成アクションを始める。確定したときにトークンを消費する。
     */
    #[PossibleAction]
    public function actUseComposeToken(int $activePlayerId): string
    {
        return $this->irStart(fn() => $this->game->irTurn()->construct->useComposeToken($activePlayerId));
    }

    /**
     * 精神分析トークンを使って追加の精神分析アクションを始める。コストを払えないときは使えない（空撃ち不可）。
     */
    #[PossibleAction]
    public function actUseAnalysisToken(int $activePlayerId): string
    {
        return $this->irStart(fn() => $this->game->irTurn()->construct->useAnalysisToken($activePlayerId));
    }

    /**
     * 自己構成フェイズを終える。手札上限の確認を積んでクリーンアップへ（001 §12、Q-30）。
     */
    #[PossibleAction]
    public function actEndConstruct(int $activePlayerId): string
    {
        $this->game->irTurn()->construct->end($activePlayerId);

        return Transition::END;
    }

    /**
     * 何も使わずにフェイズを終える（006 §10）。
     */
    public function zombie(int $playerId): string
    {
        return $this->actEndConstruct($playerId);
    }

    /**
     * @param \Closure(): void $start
     */
    private function irStart(\Closure $start): string
    {
        try {
            $start();
        } catch (EffectViolation $e) {
            throw new UserException($e->getMessage());
        }

        return Transition::ACT;
    }
}
