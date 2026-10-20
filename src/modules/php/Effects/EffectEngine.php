<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects;

use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\SpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameMutator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\EngineResult;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\UnsupportedOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpAnswer;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpContext;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpHandler;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\OpRegistry;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ActiveSpecs;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ComposeEnergyQuery;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\Control;
use Bga\Games\IRevolveThereforeIAm\Material\EffectNode;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\EffectStep;
use Bga\Games\IRevolveThereforeIAm\Material\Op;

/**
 * 効果解決スタックの frame（`SPEC_RUN`）を進めるエンジン（008 §11.1。70 `EffectResolve` が呼ぶ）。フレームワークを呼ばない。
 *
 * `EffectSpec` の操作列を先行順に並べ（{@see EffectSpec::steps()}）、`frame.step` の位置から順に実行する。
 * 選択の要らない操作は自動で解決し、選択が要るところで {@see ChoiceRequest} を返して止まる（frame は待機）。
 * 答えが来たら {@see self::answer()}（スキップは {@see self::skip()}）で検証して反映し、続きを解決する。
 *
 * 制御構造:
 * - `SEQ`: 子を順に実行する（先行順に並べているので何もしない）。
 * - `OPTIONAL`: `via` が null（61・62 の `actCancel` が担う）、または子の操作自身の選択なら確認を挟まない。
 *   `CONFIRM_USE` / `CONFIRM_MOVE` なら聞く（答えは `['use' => bool]`、スキップは「行わない」）。
 * - `CHOOSE_ONE`: 分岐を聞く（`ChoiceRequest.kind` が null。答えは `['branch' => 番号]`）。選ばれなかった分岐は飛ばす。
 * - `REPEAT`: 後続のタスク（M090。008 §11.2 の順 5）で足す。
 *
 * 未実装の原子操作（OP-10〜OP-21、OP-25）に当たったら {@see UnsupportedOp}。
 */
final class EffectEngine
{
    private readonly ComposeEnergyQuery $energy;

    public function __construct(
        private readonly GameView $view,
        private readonly GameMutator $mutator,
        private readonly SpecSource $specs,
        private readonly OpRegistry $registry = new OpRegistry(),
    ) {
        $this->energy = new ComposeEnergyQuery(
            new ActiveSpecs($view, $specs, new ConditionEvaluator($view)),
        );
    }

    /**
     * 効果を実行する frame を作る（まだ進めない）。
     *
     * @param array<string, scalar|null> $payload
     */
    public function start(string $specId, int $owner, string $source = '', array $payload = []): EffectFrame
    {
        $this->spec($specId);

        return new EffectFrame($specId, $owner, $source, 0, [], null, $payload);
    }

    /**
     * frame を進められるところまで進める。
     */
    public function run(EffectFrame $frame): EngineResult
    {
        if ($frame->pending !== null) {
            return new EngineResult($frame->pending);
        }

        return $this->advance($frame, []);
    }

    /**
     * 待機中の選択に答える。検証して反映し、続きを解決する。
     *
     * @param array<string, mixed> $answer
     * @throws EffectViolation 答えが規則に反するとき
     */
    public function answer(EffectFrame $frame, array $answer): EngineResult
    {
        return $this->resume($frame, OpAnswer::of($answer));
    }

    /**
     * 待機中の選択をスキップする（`actSkipChoice`）。スキップできない種類では例外。
     *
     * @throws EffectViolation スキップできない選択
     */
    public function skip(EffectFrame $frame): EngineResult
    {
        $pending = $frame->pending ?? throw new EffectViolation('No pending choice');
        if ($pending->kind !== null && !$pending->kind->skippable()) {
            throw new EffectViolation("{$pending->kind->value} cannot be skipped");
        }

        return $this->resume($frame, OpAnswer::skipped());
    }

    private function resume(EffectFrame $frame, OpAnswer $answer): EngineResult
    {
        $pending = $frame->pending ?? throw new EffectViolation('No pending choice');
        $steps = $this->spec($frame->specId)->steps();
        $step = $steps[$pending->step];
        $events = [];

        if ($step->node->kind === Control::OPTIONAL) {
            $use = !$answer->skipped && ($answer->data['use'] ?? null) === true;
            $frame->decisions[$step->index] = $use ? 1 : 0;
            $frame->pending = null;
        } elseif ($step->node->kind === Control::CHOOSE_ONE) {
            $branch = $answer->data['branch'] ?? null;
            if (!is_int($branch) || $branch < 0 || $branch >= count($step->node->children)) {
                throw new EffectViolation('Unknown branch');
            }
            $frame->decisions[$step->index] = $branch;
            $frame->pending = null;
            $frame->step = $this->branchStart($steps, $step, $branch);
        } else {
            $handler = $this->handlerFor($step->node);
            $events = $this->execute($frame, $step, $handler, $answer);
            $frame->pending = null;
            $frame->step = $step->index + 1;
        }

        return $this->advance($frame, $events);
    }

    /**
     * @param list<GameEvent> $events
     */
    private function advance(EffectFrame $frame, array $events): EngineResult
    {
        $steps = $this->spec($frame->specId)->steps();
        $count = count($steps);

        while ($frame->step < $count) {
            $skipTo = $this->skipTarget($steps, $frame);
            if ($skipTo !== null) {
                $frame->step = $skipTo;
                continue;
            }
            $step = $steps[$frame->step];
            $node = $step->node;

            if ($node->kind instanceof Control) {
                $choice = $this->controlStep($frame, $step);
                if ($choice !== null) {
                    $frame->pending = $choice;

                    return new EngineResult($choice, $events);
                }
                continue;
            }

            $handler = $this->handlerFor($node);
            $request = $handler->request($this->context($frame, $node));
            if ($request !== null) {
                $request = new ChoiceRequest($request->kind, $request->owner, $step->index, $request->data);
                $frame->pending = $request;

                return new EngineResult($request, $events);
            }
            array_push($events, ...$this->execute($frame, $step, $handler, OpAnswer::auto()));
            $frame->step = $step->index + 1;
        }

        return new EngineResult(null, $events);
    }

    /**
     * 制御構造の節に当たったときの処理。位置を進め、選択が要るなら内容を返す。
     */
    private function controlStep(EffectFrame $frame, EffectStep $step): ?ChoiceRequest
    {
        $node = $step->node;
        switch ($node->kind) {
            case Control::SEQ:
                $frame->step = $step->index + 1;

                return null;

            case Control::OPTIONAL:
                if (!isset($frame->decisions[$step->index])) {
                    if (!$this->asksConfirmation($node)) {
                        $frame->decisions[$step->index] = 1;
                    } else {
                        return new ChoiceRequest($node->via, $frame->owner, $step->index);
                    }
                }
                $frame->step = $frame->decisions[$step->index] === 1 ? $step->index + 1 : $step->end;

                return null;

            case Control::CHOOSE_ONE:
                if (!isset($frame->decisions[$step->index])) {
                    return new ChoiceRequest(null, $frame->owner, $step->index, ['branches' => count($node->children)]);
                }
                $frame->step = $step->index + 1;

                return null;

            default:
                throw UnsupportedOp::forKind(Control::REPEAT);
        }
    }

    /**
     * `OPTIONAL` が確認（`CONFIRM_USE` / `CONFIRM_MOVE`）を聞くか。`via` が null か、子の操作自身の選択なら聞かない。
     */
    private function asksConfirmation(EffectNode $optional): bool
    {
        if ($optional->via === null) {
            return false;
        }
        $child = $optional->children[0] ?? null;
        $op = $child?->atomicOp();
        if ($op !== null && $this->registry->has($op) && $this->registry->get($op)->choiceKind() === $optional->via) {
            return false;
        }

        return $optional->via === ChoiceKind::CONFIRM_USE || $optional->via === ChoiceKind::CONFIRM_MOVE;
    }

    /**
     * 選ばれなかった分岐の先頭に着いたら、その分岐の終わりまで飛ぶ。
     *
     * @param list<EffectStep> $steps
     */
    private function skipTarget(array $steps, EffectFrame $frame): ?int
    {
        foreach ($frame->decisions as $index => $decision) {
            $chooser = $steps[$index] ?? null;
            if ($chooser === null || $chooser->node->kind !== Control::CHOOSE_ONE) {
                continue;
            }
            $start = $chooser->index + 1;
            foreach ($chooser->node->children as $branch => $child) {
                $branchStep = $steps[$start];
                if ($start === $frame->step && $branch !== $decision) {
                    return $branchStep->end;
                }
                $start = $branchStep->end;
            }
        }

        return null;
    }

    /**
     * @param list<EffectStep> $steps
     */
    private function branchStart(array $steps, EffectStep $chooser, int $branch): int
    {
        $start = $chooser->index + 1;
        for ($i = 0; $i < $branch; $i++) {
            $start = $steps[$start]->end;
        }

        return $start;
    }

    /**
     * 原子操作を実行する。実行後に勝利判定が必要な操作（008 §6.8）は判定を呼ぶ。
     *
     * @return list<GameEvent>
     */
    private function execute(EffectFrame $frame, EffectStep $step, OpHandler $handler, OpAnswer $answer): array
    {
        $events = $handler->execute($this->context($frame, $step->node), $answer);
        $op = $step->node->atomicOp();
        if ($op !== null && $op->checksVictory()) {
            $this->mutator->checkVictory();
        }

        return $events;
    }

    private function handlerFor(EffectNode $node): OpHandler
    {
        $op = $node->atomicOp();
        if (!$op instanceof Op) {
            throw new \LogicException('Not an atomic operation');
        }

        return $this->registry->get($op);
    }

    private function context(EffectFrame $frame, EffectNode $node): OpContext
    {
        return new OpContext($this->view, $this->mutator, $this->energy, $node, $frame->owner);
    }

    private function spec(string $specId): EffectSpec
    {
        return $this->specs->find($specId) ?? throw new \OutOfBoundsException("Unknown effect id: {$specId}");
    }
}
