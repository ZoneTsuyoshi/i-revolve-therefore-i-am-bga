<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects;

use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\SpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\ActionAvailability;
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
use Bga\Games\IRevolveThereforeIAm\Effects\Value\ValueEvaluator;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\Control;
use Bga\Games\IRevolveThereforeIAm\Material\EffectKind;
use Bga\Games\IRevolveThereforeIAm\Material\EffectNode;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\EffectStep;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\Op;
use Bga\Games\IRevolveThereforeIAm\Material\OwnerScope;
use Bga\Games\IRevolveThereforeIAm\Material\ValueExpr;

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
 * - `REPEAT`: 値式で決まる回数だけ子を繰り返す（M090 の「3 枚につき」）。残り回数は `decisions` に持つ。
 *
 * 61・62 に入る行動（OP-10・OP-11）は {@see ChoiceRequest::action()} を返して止まる。61・62 が確定したら {@see self::completeAction()}、
 * 行わないなら {@see self::cancelAction()} で続きを解決する。解決スタックに frame を積む操作（`Op::pushesFrames()`）の後は、
 * 積まれた frame を先に解決するため {@see EngineResult::$yielded} で止まる。
 *
 * 起きたイベントがある操作の後も、続きの節があれば {@see EngineResult::$yielded} で止まる。呼び出し側がイベントに誘発した効果を
 * 解決スタックの先頭に積み、先に解決してから続きを解決する（M085〜M087 の追加分は、床の要素の直後、次の要素の前に解決する。008 §6.6）。
 *
 * ハンドラの無い原子操作（OP-20 `PLACE_UNDER_FLOOR` は 62 が行う）に当たったら {@see UnsupportedOp}。
 */
final class EffectEngine
{
    /** 原子操作 1 つだけの frame の効果 ID の接頭辞（`OP:OP-26`） */
    public const OP_PREFIX = 'OP:';

    private readonly ComposeEnergyQuery $energy;

    /** @var array<string, EffectSpec> */
    private array $opSpecs = [];

    public function __construct(
        private readonly GameView $view,
        private readonly GameMutator $mutator,
        private readonly SpecSource $specs,
        private readonly OpRegistry $registry = new OpRegistry(),
        private readonly ?ActionAvailability $availability = null,
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
     * 原子操作 1 つだけを実行する frame を作る（`OP` frame。`OP:OP-26` のような効果 ID を使う。パラメータの無い操作だけ）。
     */
    public static function opSpecId(Op $op): string
    {
        return self::OP_PREFIX . $op->value;
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
        if ($pending->isAction()) {
            throw new EffectViolation('Use cancelAction() for a compose or analysis action');
        }
        if ($pending->kind !== null && !$pending->kind->skippable()) {
            throw new EffectViolation("{$pending->kind->value} cannot be skipped");
        }

        return $this->resume($frame, OpAnswer::skipped());
    }

    /**
     * 61・62 で行動が確定した。その節を済ませて続きを解決する。
     *
     * @throws EffectViolation 待っているのが行動ではないとき
     */
    public function completeAction(EffectFrame $frame): EngineResult
    {
        $pending = $this->pendingAction($frame);
        $frame->pending = null;
        $frame->step = $pending->step + 1;

        return $this->advance($frame, []);
    }

    /**
     * 61・62 で行動を行わなかった（`actCancel`）。その行動を包む `OPTIONAL` を「行わない」にして続きを解決する。
     *
     * @throws EffectViolation 待っているのが行動ではないとき、行わない選択ができないとき
     */
    public function cancelAction(EffectFrame $frame): EngineResult
    {
        $pending = $this->pendingAction($frame);
        $optional = null;
        foreach ($this->spec($frame->specId)->steps() as $step) {
            if ($step->index < $pending->step && $pending->step < $step->end && $step->node->kind === Control::OPTIONAL) {
                $optional = $step;
            }
        }
        if ($optional === null) {
            throw new EffectViolation('This action cannot be skipped');
        }
        $frame->decisions[$optional->index] = 0;
        $frame->pending = null;
        $frame->step = $optional->end;

        return $this->advance($frame, []);
    }

    /**
     * 先頭が `CHOOSE_ONE` の効果（APHRODITE・HEPHAESTUS）で、分岐を先に決めておく（51 の選択。選んだ分岐の先頭へ進める）。
     *
     * @throws EffectViolation 分岐のある効果でないとき、分岐の番号が違うとき
     */
    public function selectBranch(EffectFrame $frame, int $branch): void
    {
        $steps = $this->spec($frame->specId)->steps();
        $chooser = $steps[0] ?? null;
        if ($chooser === null || $chooser->node->kind !== Control::CHOOSE_ONE || $frame->step !== 0) {
            throw new EffectViolation('This effect has no branches to choose');
        }
        if ($branch < 0 || $branch >= count($chooser->node->children)) {
            throw new EffectViolation('Unknown branch');
        }
        $frame->decisions[0] = $branch;
        $frame->pending = null;
        $frame->step = $this->branchStart($steps, $chooser, $branch);
    }

    /**
     * 効果の分岐の数（分岐の無い効果は 0）。51 が選択肢を作るのに使う。
     */
    public function branchCount(string $specId): int
    {
        $first = $this->spec($specId)->steps()[0] ?? null;

        return $first !== null && $first->node->kind === Control::CHOOSE_ONE ? count($first->node->children) : 0;
    }

    private function pendingAction(EffectFrame $frame): ChoiceRequest
    {
        $pending = $frame->pending;
        if ($pending === null || !$pending->isAction()) {
            throw new EffectViolation('No compose or analysis action is pending');
        }

        return $pending;
    }

    private function resume(EffectFrame $frame, OpAnswer $answer): EngineResult
    {
        $pending = $frame->pending ?? throw new EffectViolation('No pending choice');
        if ($pending->isAction()) {
            throw new EffectViolation('A compose or analysis action is pending');
        }
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
            if (($events !== [] || $step->node->atomicOp()?->pushesFrames() === true) && $frame->step < count($steps)) {
                return new EngineResult(null, $events, true);
            }
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

        while (true) {
            $this->loopBack($steps, $frame);
            if ($frame->step >= $count) {
                break;
            }
            if ($this->mutator->isGameOver()) {
                return new EngineResult(null, $events);
            }
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
            if (($events !== [] || $node->atomicOp()?->pushesFrames() === true) && $frame->step < $count) {
                return new EngineResult(null, $events, true);
            }
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

            case Control::REPEAT:
                if (!isset($frame->decisions[$step->index])) {
                    $times = $node->params['times'] ?? 0;
                    $frame->decisions[$step->index] = is_int($times) || $times instanceof ValueExpr
                        ? max(0, ValueEvaluator::evaluate($times, $this->view, $frame->owner))
                        : 0;
                }
                if ($frame->decisions[$step->index] > 0) {
                    $frame->decisions[$step->index]--;
                    $frame->step = $step->index + 1;
                } else {
                    $frame->step = $step->end;
                }

                return null;
            default:
                throw UnsupportedOp::forKind($node->kind);
        }
    }

    /**
     * `REPEAT` の子を 1 回終えたとき（位置が節の終わり）、残りの回数があれば先頭へ戻る。
     *
     * @param list<EffectStep> $steps
     */
    private function loopBack(array $steps, EffectFrame $frame): void
    {
        foreach ($steps as $step) {
            if (
                $step->node->kind === Control::REPEAT && $frame->step === $step->end
                && ($frame->decisions[$step->index] ?? 0) > 0
            ) {
                $frame->step = $step->index;

                return;
            }
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
        return new OpContext($this->view, $this->mutator, $this->energy, $node, $frame->owner, $this->availability, $frame->payload);
    }

    private function spec(string $specId): EffectSpec
    {
        if (str_starts_with($specId, self::OP_PREFIX)) {
            $op = Op::tryFrom(substr($specId, strlen(self::OP_PREFIX)))
                ?? throw new \OutOfBoundsException("Unknown effect id: {$specId}");

            return $this->opSpecs[$specId] ??= new EffectSpec(
                id: $specId,
                source: '',
                mechanism: Mechanism::ACTION,
                kind: EffectKind::NONE,
                ownerScope: OwnerScope::TURN_PLAYER,
                ops: [EffectNode::op($op)],
            );
        }

        return $this->specs->find($specId) ?? throw new \OutOfBoundsException("Unknown effect id: {$specId}");
    }
}
