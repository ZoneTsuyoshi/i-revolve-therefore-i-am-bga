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
 * （取り消しでは何も消費しない。空撃ち防止 Q-28）。自己構成スコアの更新と 20 点の判定は {@see StoredGameMutator::checkVictory()} 。
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
                'tapCandidates' => $this->energy->tapCandidates($player, $context),
                'extraMax' => $this->energy->extraPayMax($player),
            ],
            'private' => ['hand' => $hand, 'architectPairs' => $this->architectPairs($player, $context)],
        ];
    }

    /**
     * I03（建築家）で入れ替えられる `[自己構成済みのカード ID, 手札のカード ID]` の組（`architect` が受け付けるものだけ）。
     *
     * @return list<array{int, int}>
     */
    private function architectPairs(int $player, ComposeContext $context): array
    {
        $tableau = $this->view->tableauIds($player);
        $pairs = [];
        foreach ($tableau as $tableauId) {
            foreach ($this->view->handIds($player) as $handId) {
                if (!$this->energy->architectSubstituteAllowed($player, $tableauId, $handId, $context)) {
                    continue;
                }
                $type = $this->view->cardType($handId);
                $duplicate = false;
                foreach ($tableau as $otherId) {
                    if ($otherId !== $tableauId && $this->view->cardType($otherId) === $type) {
                        $duplicate = true;
                    }
                }
                if (!$duplicate) {
                    $pairs[] = [$tableauId, $handId];
                }
            }
        }

        return $pairs;
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
            throw new EffectViolation(clienttranslate('You have no compose token'));
        }
        if ($context === ComposeContext::PHASE && $this->globals->turnContext()->phaseComposeUsed) {
            throw new EffectViolation(clienttranslate('You already composed in the compose phase'));
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
        $attached = $this->attachExtra($player, $cardId, $plan->extra);

        return $this->finish($player, $context, $pending, $cardId, $notices, $pending->context, [
            'discarded' => $discarded,
            'light' => $plan->lights,
            'energy' => $plan->need,
            'tapped' => $plan->taps,
        ], $attached);
    }

    /**
     * I03（建築家）の代替: 自己構成する代わりに、自己構成済みの 1 枚を捨札にし、そのエネルギーより 2 まで大きい手札のカードを
     * エネルギーなしで自己構成する（捨てる処理が先。FAQ 行 32、R8-17）。自己構成アクション 1 回として数える（R8-7）。
     *
     * @return list<Notice>
     * @throws EffectViolation
     */
    public function architect(int $player, int $tableauCardId, int $newCardId): array
    {
        $pending = $this->frames->current(Op::COMPOSE, $player);
        $context = ComposeContext::from($pending->context);
        if ($context === ComposeContext::TOKEN && $this->players->composeTokens($player) < 1) {
            throw new EffectViolation(clienttranslate('You have no compose token'));
        }
        if ($context === ComposeContext::PHASE && $this->globals->turnContext()->phaseComposeUsed) {
            throw new EffectViolation(clienttranslate('You already composed in the compose phase'));
        }
        if (!$this->energy->architectSubstituteAllowed($player, $tableauCardId, $newCardId, $context)) {
            throw new EffectViolation(clienttranslate('That substitution is not allowed'));
        }
        $newType = $this->view->cardType($newCardId);
        foreach ($this->view->tableauIds($player) as $otherId) {
            if ($otherId !== $tableauCardId && $this->view->cardType($otherId) === $newType) {
                throw new EffectViolation(clienttranslate('A card with the same name is already composed'));
            }
        }
        $notices = [];
        $name = ($this->nameOf)($player);
        $old = $this->memory->find($tableauCardId) ?? throw new \LogicException("unknown card $tableauCardId");
        $levelOf = static fn (int $type): int => PublicView::definition($type)->level;
        $under = array_map(
            static fn ($c): array => Notification::cardRef($c->id, $c->type),
            $this->memory->inLoc(MemoryLocation::UnderCard, $tableauCardId),
        );
        $this->memory->releaseUnder($tableauCardId, $levelOf);
        $this->memory->move($tableauCardId, MemoryLocation::discard($levelOf($old->type)));
        $piles = [1 => $this->memory->count(MemoryLocation::Discard1), 2 => $this->memory->count(MemoryLocation::Discard2)];
        $hand = $this->memory->count(MemoryLocation::Hand, $player);
        // 場を離れた自己構成済みカード（捨札へ）。クライアントは `from_tableau` で自己構成済みエリアから取り除く
        $notices[] = Notification::tableauCardDiscarded($player, $name, Notification::cardRef($tableauCardId, $old->type), $piles, $hand);
        if ($under !== []) {
            $notices[] = Notification::cardsDiscarded($player, $name, $under, 'UNDER_RELEASED', $piles, $hand);
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
        $this->memory->compose($newCardId, $player);

        return $this->finish($player, $context, $pending, $newCardId, $notices, 'ARCHITECT', [
            'discarded' => [],
            'light' => 0,
            'energy' => 0,
            'tapped' => [],
        ]);
    }

    /**
     * I08 の追加支払い: 手札を、自己構成したカードの下に裏向きで置く（他人には枚数だけ。N19）。
     *
     * @param list<int> $extra
     * @return list<Notice> 自己構成の通知（N18）の後に送る
     */
    private function attachExtra(int $player, int $hostId, array $extra): array
    {
        if ($extra === []) {
            return [];
        }
        $refs = [];
        foreach ($extra as $id) {
            $card = $this->memory->find($id) ?? throw new \LogicException("unknown card $id");
            $refs[] = Notification::cardRef($id, $card->type);
            $this->memory->move($id, MemoryLocation::UnderCard, $hostId);
        }
        $host = $this->memory->find($hostId) ?? throw new \LogicException("unknown card $hostId");

        return [Notification::cardsAttached(
            $player,
            ($this->nameOf)($player),
            $hostId,
            PublicView::definition($host->type)->nameEn,
            $refs,
            $this->memory->count(MemoryLocation::Hand, $player),
        )];
    }

    /**
     * 自己構成の確定の共通部分: 回数と軽減の消費、通知、勝利判定、行動の frame を済ませる、EV-05 の誘発。
     *
     * @param list<Notice> $notices
     * @param array{discarded: list<array{id: int, type: int}>, light: int, energy: int, tapped: list<int>} $payment
     * @param list<Notice> $afterComposed 自己構成の通知の直後に送る通知（I08 の追加支払い）
     * @return list<Notice>
     */
    private function finish(
        int $player,
        ComposeContext $context,
        PendingAction $pending,
        int $cardId,
        array $notices,
        string $noticeContext,
        array $payment,
        array $afterComposed = [],
    ): array {
        $name = ($this->nameOf)($player);
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
            $noticeContext,
            $payment,
            $tableauCount,
            $this->memory->count(MemoryLocation::Hand, $player),
        )]);
        $this->mutator->add($afterComposed);
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
