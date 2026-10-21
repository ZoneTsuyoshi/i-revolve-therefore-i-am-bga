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
use Bga\Games\IRevolveThereforeIAm\Data\SpiritRepository;
use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\SpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\Trigger\TriggeredEffect;
use Bga\Games\IRevolveThereforeIAm\Material\AnalysisCards;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use Bga\Games\IRevolveThereforeIAm\Material\Op;

/**
 * 精神トラックの階層を 1 つ上げる処理と、到着した精神カードの処理（008 §7.5）。精神分析アクション（62）と OP-12（M088）が共有する。
 *
 * 1. キューブを動かす。精神分析アクション（`via = ACTION`）なら実行回数を数える（FAQ 行 28・39）。
 * 2. 第五階層なら心理の極致で即座に勝利する（001 §14-1）。
 * 3. そうでなければ、心光の獲得（OP-05）、続けて精神カードのボーナスと EV-06 の誘発を 1 つの集合にして順序づけ（FAQ 行 26）、
 *    解決スタックの先頭に積む。
 */
final class SpiritArrival
{
    public function __construct(
        private readonly SpiritRepository $spirits,
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
        private readonly EffectStack $stack,
        private readonly SpecSource $specs,
        private readonly TriggerPlanner $planner,
        private readonly \Closure $nameOf,
    ) {
    }

    /**
     * @param array{tapped: list<int>, discarded: list<array{id: int, type: int}>, light: int}|null $payment 精神分析アクションの支払い
     */
    public function arrive(int $player, string $spiritId, string $via, ?array $payment = null): ArrivalResult
    {
        $from = $this->players->spirit($player);
        $hadLight = $this->spirits->hasLight($spiritId);
        $this->spirits->advance($player, $spiritId);
        $context = $this->globals->turnContext();
        if ($via === 'ACTION') {
            $context->analysisActionsDone++;
            $this->globals->setTurnContext($context);
        }
        $tier = AnalysisCards::get($spiritId)->floor;
        $notices = [Notification::spiritAdvanced(
            $player,
            ($this->nameOf)($player),
            $from,
            $spiritId,
            $tier,
            $via,
            $hadLight,
            $payment,
            $context->analysisActionsDone,
        )];
        if ($tier >= 5) {
            $this->globals->setEndReason('APEX_OF_PSYCHE');
            $this->globals->setWinnerId($player);

            return new ArrivalResult($hadLight, $notices);
        }

        $payload = ['via' => $via, 'spirit' => $spiritId, 'to' => $tier, 'dest_had_light' => $hadLight];
        $effects = [];
        $bonus = $this->specs->find($spiritId);
        if ($bonus !== null) {
            $effects[] = new TriggeredEffect($bonus, $spiritId, $payload);
        }
        array_push($effects, ...$this->planner->collect([new GameEvent(Event::TIER_UP, $player, $payload)]));
        $this->stack->pushFront([
            EffectFrame::op(Op::CLAIM_SPIRIT_LIGHT->value, $player),
            ...$this->planner->framesFor($effects),
        ]);

        return new ArrivalResult($hadLight, $notices);
    }
}
