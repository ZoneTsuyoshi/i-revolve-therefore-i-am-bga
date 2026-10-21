<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Data\EffectStack;
use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Material\ComposeContext;

/**
 * 60 `Construct`: 自己構成フェイズの窓（001 §5・§11-1、006 §5.5）。自己構成アクション（フェイズの 1 回。Q-02）、自己構成トークン、
 * 精神分析トークンを任意の順に使い、終えたら手札上限を確認してクリーンアップへ進む（Q-30）。
 *
 * 各アクションは行動の frame（と手札上限の確認）を積んで 61・62 へ出る。フェイズの自己構成の使用済みとトークンの消費は、
 * 行動が確定したときに行う（取り消しでは何も消費しない。空撃ち防止 Q-28）。トークンは、行える状況（自己構成できるカードがある、
 * コストを払える）でだけ使える。
 */
final class ConstructPhase
{
    public function __construct(
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
        private readonly EffectStack $stack,
        private readonly ComposePlanner $compose,
        private readonly AnalysisPlanner $analysis,
    ) {
    }

    /**
     * 60 の args。
     *
     * @return array<string, mixed>
     */
    public function args(int $turnPlayer): array
    {
        return [
            'turnPlayer' => $turnPlayer,
            'phaseComposeUsed' => $this->globals->turnContext()->phaseComposeUsed,
            'composeTokens' => $this->players->composeTokens($turnPlayer),
            'analysisTokens' => $this->players->analysisTokens($turnPlayer),
            'canComposeToken' => $this->players->composeTokens($turnPlayer) > 0
                && $this->compose->canCompose($turnPlayer, ComposeContext::TOKEN, 0),
            'canAnalysisToken' => $this->players->analysisTokens($turnPlayer) > 0 && $this->analysis->canAnalyze($turnPlayer, 0),
        ];
    }

    /**
     * フェイズの自己構成アクション（1 回だけ）。
     *
     * @throws EffectViolation
     */
    public function startPhaseCompose(int $player): void
    {
        if ($this->globals->turnContext()->phaseComposeUsed) {
            throw new EffectViolation(clienttranslate('You already composed in the compose phase'));
        }
        $this->pushAction(EffectFrame::KIND_COMPOSE, $player, ComposeContext::PHASE->value);
    }

    /**
     * 自己構成トークンで追加の自己構成アクション。
     *
     * @throws EffectViolation
     */
    public function useComposeToken(int $player): void
    {
        if ($this->players->composeTokens($player) < 1) {
            throw new EffectViolation(clienttranslate('You have no compose token'));
        }
        if (!$this->compose->canCompose($player, ComposeContext::TOKEN, 0)) {
            throw new EffectViolation(clienttranslate('There is no card you can compose, so the token cannot be used'));
        }
        $this->pushAction(EffectFrame::KIND_COMPOSE, $player, ComposeContext::TOKEN->value);
    }

    /**
     * 精神分析トークンで追加の精神分析アクション。コストを払えないときは使えない（空撃ち不可。Q-28）。
     *
     * @throws EffectViolation
     */
    public function useAnalysisToken(int $player): void
    {
        if ($this->players->analysisTokens($player) < 1) {
            throw new EffectViolation(clienttranslate('You have no analysis token'));
        }
        if (!$this->analysis->canAnalyze($player, 0)) {
            throw new EffectViolation(clienttranslate('You cannot pay for an analysis, so the token cannot be used'));
        }
        $this->pushAction(EffectFrame::KIND_ANALYZE, $player, 'TOKEN');
    }

    /**
     * 自己構成フェイズを終える。手札上限を確認してからクリーンアップへ（Q-30）。
     */
    public function end(int $player): void
    {
        $this->stack->pushFront([EffectFrame::handLimitCheck($player)]);
        $this->globals->setResume(StateId::CLEANUP);
    }

    private function pushAction(string $kind, int $player, string $context): void
    {
        $this->stack->pushFront([EffectFrame::action($kind, $player, $context, 0), EffectFrame::handLimitCheck($player)]);
        $this->globals->setResume(StateId::CONSTRUCT);
    }
}
