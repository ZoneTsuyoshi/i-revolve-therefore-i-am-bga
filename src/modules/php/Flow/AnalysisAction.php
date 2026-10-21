<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Data\StoredGameMutator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\LightScheduleQuery;
use Bga\Games\IRevolveThereforeIAm\Material\ActionContext;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use Bga\Games\IRevolveThereforeIAm\Material\Op;

/**
 * 62 `Analysis`: 精神分析アクション 1 回を確定する（001 §10、006 §5.4、008 §6.5.3）。検証は {@see AnalysisPlanner}。
 *
 * 確定すると、支払い（横向き・捨札・心光）、トークンの消費（TOKEN）、階層の上昇（{@see SpiritArrival}。実行回数、心光の獲得、
 * ボーナス、EV-06 の誘発の積み込み。第五階層は心理の極致で勝利）を行い、行動の frame を済ませる。
 * トークンは確定したときにだけ消費する（取り消しでは何も消費しない。空撃ち防止 Q-28）。
 */
final class AnalysisAction
{
    public function __construct(
        private readonly AnalysisPlanner $planner,
        private readonly ActionFrames $frames,
        private readonly MemoryCardRepository $memory,
        private readonly PlayerValues $players,
        private readonly StoredGameMutator $mutator,
        private readonly SpiritArrival $arrival,
        private readonly GameView $view,
        private readonly LightScheduleQuery $light,
        private readonly \Closure $nameOf,
    ) {
    }

    /**
     * 62 の args。進む先ごとのコスト、軽減できる鍵の個数、心光の逓増、自己構成済みカードの鍵（公開）。手札の鍵は本人にだけ見せる。
     *
     * @return array{public: array<string, mixed>, private: array<string, mixed>}
     */
    public function args(int $player): array
    {
        $pending = $this->frames->current(Op::ANALYZE, $player);
        $destinations = [];
        foreach ($this->planner->destinations($player) as $card) {
            $destinations[] = ['id' => $card->id, 'tier' => $card->floor, 'cost' => $card->cost];
        }
        $tableau = [];
        foreach ($this->view->tableauIds($player) as $cardId) {
            $tableau[] = $this->cardView($cardId, true);
        }
        $hand = [];
        foreach ($this->view->handIds($player) as $cardId) {
            $hand[] = $this->cardView($cardId, false);
        }
        $lightTotals = [];
        for ($uses = 1; $uses <= 8; $uses++) {
            $lightTotals[] = $this->light->totalLights($player, ActionContext::ANALYSIS, $uses);
        }

        return [
            'public' => [
                'context' => $pending->context,
                'discount' => $pending->discount,
                'reduction' => $this->planner->reduction($player, $pending->discount),
                'destinations' => $destinations,
                'tableau' => $tableau,
                'light' => $this->players->light($player),
                'lightTotals' => $lightTotals,
                'maxLightUses' => $this->planner->maxLightUses($player),
                'canAnalyze' => $this->planner->canAnalyze($player, $pending->discount),
            ],
            'private' => ['hand' => $hand],
        ];
    }

    /**
     * @param array<string, mixed> $payment {@see AnalysisPlanner} の入力
     * @return list<Notice>
     * @throws EffectViolation
     */
    public function analyze(int $player, string $destination, array $payment): array
    {
        $pending = $this->frames->current(Op::ANALYZE, $player);
        if ($pending->context === 'TOKEN' && $this->players->analysisTokens($player) < 1) {
            throw new EffectViolation('You have no analysis token');
        }
        $plan = $this->planner->plan($player, $pending->discount, $destination, $payment);
        $name = ($this->nameOf)($player);

        $notices = [];
        if ($plan->tapped !== []) {
            $this->memory->setTapped($plan->tapped, true);
            $notices[] = Notification::cardsTapped($player, $name, $plan->tapped, true);
        }
        $discarded = [];
        if ($plan->discarded !== [] && $this->planner->redirectsToFloor($player)) {
            // I04.1: 払った手札は捨札ではなく、現在の部屋から反時計回りに床カードの下へ 1 枚ずつ置く（OP-20。R8-15）
            $this->mutator->placeUnderFloor($player, $plan->discarded);
            $notices = [...$notices, ...$this->mutator->drain()];
        } else {
            foreach ($plan->discarded as $id) {
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
                    $this->memory->count(MemoryLocation::Hand, $player),
                );
            }
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
        if ($pending->context === 'TOKEN') {
            $this->players->addToken($player, PlayerValues::COLUMN_ANALYSIS, -1);
            $notices[] = Notification::tokensChanged(
                $player,
                $name,
                ['analysis' => -1],
                ['analysis' => $this->players->analysisTokens($player)],
                'PAYMENT',
            );
        }
        $this->mutator->add($notices);

        $result = $this->arrival->arrive($player, $destination, 'ACTION', [
            'tapped' => $plan->tapped,
            'discarded' => $discarded,
            'light' => $plan->lights,
        ]);
        $this->mutator->add($result->notices);
        $this->mutator->checkVictory();
        $this->frames->complete($pending);

        return $this->mutator->drain();
    }

    /**
     * 精神分析を行わない（何も消費しない）。
     *
     * @return list<Notice>
     * @throws EffectViolation 行わない選択ができない行動のとき
     */
    public function cancel(int $player): array
    {
        $this->frames->cancel($this->frames->current(Op::ANALYZE, $player));

        return $this->mutator->drain();
    }

    /**
     * @return array{id: int, type: string, keys: list<string>, tapped: bool}
     */
    private function cardView(int $cardId, bool $tableau): array
    {
        $type = $this->view->cardType($cardId);

        return [
            'id' => $cardId,
            'type' => $type,
            'keys' => array_map(static fn (KeyType $k): string => $k->value, MemoryCards::get($type)->keys),
            'tapped' => $tableau && $this->view->isTapped($cardId),
        ];
    }
}
