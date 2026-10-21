<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Data\StoredGameMutator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ComposeEnergyQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\LightScheduleQuery;
use Bga\Games\IRevolveThereforeIAm\Material\ActionContext;
use Bga\Games\IRevolveThereforeIAm\Material\ComposeContext;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use Bga\Games\IRevolveThereforeIAm\Material\Op;

/**
 * 61 `Compose`: 自己構成アクション 1 回を確定する（001 §9、006 §5.5、008 §6.5）。検証は {@see ComposePlanner}。
 *
 * 確定すると、軽減の消費と回数の記録（PHASE・TOKEN・FLOOR のとき）、トークンの消費（TOKEN）、フェイズの使用済み（PHASE）を行い、
 * EV-05（自己構成した）の誘発を解決スタックの先頭に積み、行動の frame を済ませる。トークンは確定したときにだけ消費する
 * （取り消しでは何も消費しない。空撃ち防止 Q-28）。自己構成スコアの更新と 20 点の判定は {@see StoredGameMutator::checkVictory()} （I-10）。
 */
final class ComposeAction
{
    public function __construct(
        private readonly ComposePlanner $planner,
        private readonly ActionFrames $frames,
        private readonly MemoryCardRepository $memory,
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
        private readonly StoredGameMutator $mutator,
        private readonly TriggerPlanner $triggers,
        private readonly GameView $view,
        private readonly ComposeEnergyQuery $energy,
        private readonly LightScheduleQuery $light,
        private readonly \Closure $nameOf,
    ) {
    }

    /**
     * 61 の args。公開部分は文脈と軽減・心光の逓増、手札ごとの必要エネルギーは本人にだけ見せる。
     *
     * @return array{public: array<string, mixed>, private: array<string, mixed>}
     */
    public function args(int $player): array
    {
        $pending = $this->frames->current(Op::COMPOSE, $player);
        $context = ComposeContext::from($pending->context);
        $turn = $this->globals->turnContext();
        $candidates = $this->planner->candidates($player, $context);
        $hand = [];
        foreach ($this->view->handIds($player) as $cardId) {
            $definition = MemoryCards::get($this->view->cardType($cardId));
            $hand[] = [
                'id' => $cardId,
                'type' => $definition->id,
                'level' => $definition->level,
                'need' => $this->energy->need($player, $cardId, $context, $pending->discount),
                'composable' => in_array($cardId, $candidates, true),
            ];
        }
        $lightTotals = [];
        for ($uses = 1; $uses <= 8; $uses++) {
            $lightTotals[] = $this->light->totalLights($player, ActionContext::COMPOSE, $uses);
        }

        return [
            'public' => [
                'context' => $pending->context,
                'discount' => $context->paysEnergy() ? $pending->discount : 0,
                'turnDiscount' => $turn->turnComposeDiscount,
                'nextDiscount' => $context->consumesNextDiscount() ? $turn->nextComposeDiscount : 0,
                'light' => $this->players->light($player),
                'lightTotals' => $lightTotals,
                'maxLightUses' => $this->planner->maxLightUses($player),
                'canCompose' => $candidates !== [],
            ],
            'private' => ['hand' => $hand],
        ];
    }

    /**
     * @param array<string, mixed> $payment {@see ComposePlanner} の入力
     * @return list<Notice>
     * @throws EffectViolation
     */
    public function compose(int $player, int $cardId, array $payment): array
    {
        $pending = $this->frames->current(Op::COMPOSE, $player);
        $context = ComposeContext::from($pending->context);
        if ($context === ComposeContext::TOKEN && $this->players->composeTokens($player) < 1) {
            throw new EffectViolation('You have no compose token');
        }
        if ($context === ComposeContext::PHASE && $this->globals->turnContext()->phaseComposeUsed) {
            throw new EffectViolation('You already composed in the compose phase');
        }
        $plan = $this->planner->plan($player, $context, $pending->discount, $cardId, $payment);
        $name = ($this->nameOf)($player);

        $notices = [];
        if ($plan->taps !== []) {
            $this->memory->setTapped($plan->taps, true);
            $notices[] = Notification::cardsTapped($player, $name, $plan->taps, true);
        }
        $discarded = [];
        foreach ($plan->discard as $id) {
            $card = $this->memory->find($id) ?? throw new \LogicException("unknown card $id");
            $this->memory->move($id, MemoryLocation::discard(PublicView::definition($card->type)->level));
            $discarded[] = Notification::cardRef($id, $card->type);
        }
        if ($discarded !== []) {
            $notices[] = Notification::cardsDiscarded(
                $player,
                $name,
                $discarded,
                'COST',
                [1 => $this->memory->count(MemoryLocation::Discard1), 2 => $this->memory->count(MemoryLocation::Discard2)],
                $this->memory->count(MemoryLocation::Hand, $player) - 1,
            );
        }
        if ($plan->lights > 0) {
            $this->players->addToken($player, PlayerValues::COLUMN_LIGHT, -$plan->lights);
            $notices[] = Notification::tokensChanged(
                $player,
                $name,
                ['light' => -$plan->lights],
                ['light' => $this->players->light($player)],
                'PAYMENT',
            );
        }
        if ($context === ComposeContext::TOKEN) {
            $this->players->addToken($player, PlayerValues::COLUMN_COMPOSE, -1);
            $notices[] = Notification::tokensChanged(
                $player,
                $name,
                ['compose' => -1],
                ['compose' => $this->players->composeTokens($player)],
                'PAYMENT',
            );
        }

        $this->memory->compose($cardId, $player);
        $turn = $this->globals->turnContext();
        $nextChanged = false;
        if ($context->consumesNextDiscount()) {
            $nextChanged = $turn->nextComposeDiscount !== 0;
            $turn->nextComposeDiscount = 0;
            $turn->composeActionsDone++;
        }
        if ($context === ComposeContext::PHASE) {
            $turn->phaseComposeUsed = true;
        }
        $this->globals->setTurnContext($turn);

        $card = $this->memory->find($cardId) ?? throw new \LogicException("unknown card $cardId");
        $definition = PublicView::definition($card->type);
        $tableauCount = $this->memory->count(MemoryLocation::Tableau, $player);
        $this->mutator->add($notices);
        $this->mutator->add([Notification::cardComposed(
            $player,
            $name,
            Notification::cardRef($cardId, $card->type),
            $definition->nameEn,
            $pending->context,
            [
                'discarded' => $discarded,
                'light' => $plan->lights,
                'energy' => $plan->need,
                'tapped' => $plan->taps,
            ],
            $tableauCount,
            $this->memory->count(MemoryLocation::Hand, $player),
        )]);
        if ($nextChanged) {
            $this->mutator->add([Notification::composeDiscountChanged($player, $name, $turn->turnComposeDiscount, 0)]);
        }
        $this->mutator->checkVictory();

        $this->frames->complete($pending);
        if (!$this->mutator->isGameOver()) {
            $this->triggers->emit([new GameEvent(Event::COMPOSED, $player, [
                'card_type' => $definition->id,
                'ctx' => $pending->context,
                'composed_count' => $tableauCount,
            ])]);
        }

        return $this->mutator->drain();
    }

    /**
     * 自己構成を行わない（何も消費しない）。
     *
     * @return list<Notice>
     * @throws EffectViolation 行わない選択ができない行動のとき
     */
    public function cancel(int $player): array
    {
        $this->frames->cancel($this->frames->current(Op::COMPOSE, $player));

        return $this->mutator->drain();
    }
}
