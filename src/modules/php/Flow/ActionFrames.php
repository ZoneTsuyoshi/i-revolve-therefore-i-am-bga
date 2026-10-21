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
use Bga\Games\IRevolveThereforeIAm\Effects\EffectEngine;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Material\Op;

/**
 * 61・62 が行う行動の frame の読み書き。行動が確定したら、その frame を消す（または効果の操作列を次へ進める）。
 */
final class ActionFrames
{
    public function __construct(
        private readonly EffectStack $stack,
        private readonly FrameRunner $runner,
        private readonly EffectEngine $engine,
        private readonly GlobalValues $globals,
    ) {
    }

    /**
     * 解決スタックの先頭にある、`$op`（`OP-10` または `OP-11`）の行動。
     *
     * @throws EffectViolation 先頭がその行動でないとき、他のプレイヤーの行動のとき
     */
    public function current(Op $op, int $player): PendingAction
    {
        $head = $this->stack->peek();
        $kind = $op === Op::COMPOSE ? EffectFrame::KIND_COMPOSE : EffectFrame::KIND_ANALYZE;
        if ($head === null) {
            throw new EffectViolation(clienttranslate('There is nothing to do'));
        }
        if ($head->kind === $kind) {
            $pending = new PendingAction(
                $head,
                (string) ($head->params['ctx'] ?? ''),
                (int) ($head->params['discount'] ?? 0),
                false,
                $head->cancelTo,
            );
        } elseif (
            $head->kind === EffectFrame::KIND_SPEC_RUN && $head->status === EffectFrame::WAITING && $head->choice !== null
            && (ChoiceRequest::fromArray($head->choice)->data['action'] ?? null) === $op->value
        ) {
            $data = ChoiceRequest::fromArray($head->choice)->data;
            $pending = new PendingAction($head, (string) ($data['ctx'] ?? ''), (int) ($data['discount'] ?? 0), true, null);
        } else {
            throw new EffectViolation(clienttranslate('That action is not pending'));
        }
        if ($head->owner !== $player) {
            throw new EffectViolation(clienttranslate('It is not your action'));
        }

        return $pending;
    }

    /**
     * 行動が確定した。frame を消す、または効果の操作列を次へ進めて続きを解決する。
     */
    public function complete(PendingAction $pending): void
    {
        if (!$pending->viaSpec) {
            $this->stack->complete($pending->row->id ?? throw new \LogicException('frame is not stored'));

            return;
        }
        $frame = $this->runner->load($pending->row);
        $this->runner->apply($pending->row, $frame, $this->engine->completeAction($frame));
    }

    /**
     * 行動を行わない。効果の操作列の途中なら、その行動を「行わない」ことにして続きを解決する。
     * 戻り先（`cancel_to`）のある行動は、積んだ frame を捨ててその状態に戻る（床アクションの代替は 50 から選び直す。006 §5.3）。
     *
     * @throws EffectViolation 行わない選択ができない行動のとき
     */
    public function cancel(PendingAction $pending): void
    {
        if ($pending->viaSpec) {
            $frame = $this->runner->load($pending->row);
            $this->runner->apply($pending->row, $frame, $this->engine->cancelAction($frame));

            return;
        }
        if ($pending->cancelTo !== null) {
            $this->stack->clear();
            $this->globals->setResume($pending->cancelTo);

            return;
        }
        $this->stack->complete($pending->row->id ?? throw new \LogicException('frame is not stored'));
    }
}
