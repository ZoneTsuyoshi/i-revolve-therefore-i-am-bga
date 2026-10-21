<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Data\EffectStack;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Effects\Trigger\ConflictResolver;
use Bga\Games\IRevolveThereforeIAm\Effects\Trigger\TriggeredEffect;
use Bga\Games\IRevolveThereforeIAm\Effects\Trigger\TriggerCollector;

/**
 * 誘発した効果を解決スタックの frame にする（008 §4.2、§6.3、006 §7.5）。
 * 1 つだけ、または衝突が無いときは固定順の `SPEC_RUN` を並べ、衝突する対があれば `ORDER_EFFECTS` を 1 つ積む。
 * 「してもよい」の効果（`EffectSpec::$optional`）は、解決の番が来たところで `CONFIRM_USE` を聞く（門付きの frame）。
 */
final class TriggerPlanner
{
    public function __construct(
        private readonly TriggerCollector $collector,
        private readonly ConflictResolver $resolver,
        private readonly GameView $view,
        private readonly EffectStack $stack,
    ) {
    }

    /**
     * イベントを集め、誘発した効果の frame を解決スタックの先頭に積む。
     *
     * @param list<GameEvent> $events
     */
    public function emit(array $events): void
    {
        $effects = [];
        foreach ($events as $event) {
            array_push($effects, ...$this->collector->collect($event));
        }
        $this->stack->pushFront($this->framesFor($effects));
    }

    /**
     * @param list<GameEvent> $events
     * @return list<TriggeredEffect>
     */
    public function collect(array $events): array
    {
        $effects = [];
        foreach ($events as $event) {
            array_push($effects, ...$this->collector->collect($event));
        }

        return $effects;
    }

    /**
     * 同時に誘発した効果（同じ持ち主 = ターンプレイヤー）の frame。
     *
     * @param list<TriggeredEffect> $effects
     * @return list<EffectFrame>
     */
    public function framesFor(array $effects): array
    {
        if ($effects === []) {
            return [];
        }
        $owner = $this->view->turnPlayer();
        if (count($effects) > 1 && $this->resolver->needsOrder($effects)) {
            // 候補の並びは固定順（zombie の既定の答えは先頭 = 効果 ID が最小のもの）
            $list = array_map(static fn (TriggeredEffect $e): array => [
                'spec' => $e->spec->id,
                'source' => $e->source,
                'payload' => $e->payload,
            ], $this->resolver->fixedOrder($effects));

            return [new EffectFrame(
                EffectFrame::KIND_ORDER_EFFECTS,
                $owner,
                status: EffectFrame::WAITING,
                params: ['effects' => $list],
                choice: [
                    'kind' => 'ORDER_EFFECTS',
                    'owner' => $owner,
                    'step' => 0,
                    'data' => ['candidates' => array_map(static fn (array $e): array => [
                        'spec' => $e['spec'],
                        'source' => $e['source'],
                    ], $list)],
                ],
            )];
        }

        return array_map(
            static fn (TriggeredEffect $e): EffectFrame => EffectFrame::specRun(
                $e->spec->id,
                $owner,
                $e->source,
                $e->payload,
                $e->spec->optional,
            ),
            $this->resolver->fixedOrder($effects),
        );
    }
}
