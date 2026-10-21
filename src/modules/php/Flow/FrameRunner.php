<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Data\EffectStack;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectEngine;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\EffectFrame as EngineFrame;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\EngineResult;
use Bga\Games\IRevolveThereforeIAm\Material\Op;

/**
 * DB の frame（`effect_frame` の行）と、エンジンのメモリ上の frame を行き来する（007 §6、008 §10.1）。
 * エンジンが進めた結果（`EngineResult`）を DB に反映し、起きたイベントの誘発を解決スタックの先頭に積む。
 */
final class FrameRunner
{
    public function __construct(
        private readonly EffectStack $stack,
        private readonly TriggerPlanner $triggers,
    ) {
    }

    /**
     * DB の frame（`SPEC_RUN` / `OP` / `ACQUIRE_ROOM_CARD`）からエンジンの frame を作る。
     */
    public function load(EffectFrame $row): EngineFrame
    {
        $spec = match ($row->kind) {
            EffectFrame::KIND_OP => EffectEngine::opSpecId(Op::from($row->op ?? throw new \LogicException('OP frame needs an op'))),
            EffectFrame::KIND_ACQUIRE_ROOM_CARD => EffectEngine::opSpecId(Op::ACQUIRE_ROOM_CARD),
            default => $row->spec ?? throw new \LogicException('SPEC_RUN frame needs a spec'),
        };
        $decisions = [];
        foreach ((array) ($row->params['decisions'] ?? []) as $step => $decision) {
            $decisions[(int) $step] = (int) $decision;
        }
        $pending = $row->status === EffectFrame::WAITING && $row->choice !== null && ($row->params['gate'] ?? null) === null
            ? ChoiceRequest::fromArray($row->choice)
            : null;

        return new EngineFrame(
            $spec,
            $row->owner,
            $row->source ?? '',
            $row->step,
            $decisions,
            $pending,
            $row->payload ?? [],
        );
    }

    /**
     * エンジンの frame の途中経過（`step`・`decisions`・保留中の選択）を DB に保存する。
     *
     * @param array<string, mixed> $extra `params` に足す値（`started` など）
     */
    public function save(EffectFrame $row, EngineFrame $frame, array $extra = []): void
    {
        $params = [...($row->params ?? []), ...$extra, 'decisions' => $frame->decisions];
        $this->stack->saveProgress($this->id($row), $frame->step, $params, $frame->pending?->toArray());
    }

    /**
     * エンジンが frame を進めた結果を反映する: 選択待ち・積んだ frame の解決待ちなら途中経過を保存し、最後まで解決したら frame を消す。
     * 起きたイベントの誘発した効果は、解決スタックの先頭に積む。
     *
     * @param array<string, mixed> $extra
     */
    public function apply(EffectFrame $row, EngineFrame $frame, EngineResult $result, array $extra = []): void
    {
        if ($result->isDone()) {
            $this->stack->complete($this->id($row));
        } else {
            $this->save($row, $frame, $extra);
        }
        $this->triggers->emit($result->events);
    }

    private function id(EffectFrame $row): int
    {
        return $row->id ?? throw new \LogicException('frame is not stored');
    }
}
