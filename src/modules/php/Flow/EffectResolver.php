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
use Bga\Games\IRevolveThereforeIAm\Data\StoredGameMutator;
use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\SpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionContext;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectEngine;
use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\EffectLimit;
use Bga\Games\IRevolveThereforeIAm\Material\EgoCards;
use Bga\Games\IRevolveThereforeIAm\Material\Mechanism;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use Bga\Games\IRevolveThereforeIAm\Material\Op;
use Bga\Games\IRevolveThereforeIAm\Material\OwnerScope;

/**
 * 70 `EffectResolve` のループ（006 §7.2、008 §1.2）。解決スタックの先頭の frame を種類ごとに解決する。
 *
 * - 自動で済む効果（`SPEC_RUN`・`OP`・`ACQUIRE_ROOM_CARD`）はエンジンで解決する。選択が要れば frame を待機にして 71 へ、
 *   自己構成・精神分析の行動が要れば 61・62 へ出る。
 * - `RECALL_ACTIVATION` は想起効果の `SPEC_RUN` に展開する（M062 の 2 回目は `CONFIRM_USE` を聞く）。
 * - `HAND_LIMIT_CHECK` は超過したプレイヤーがいれば 72 へ。
 * - スタックが空になったら戻り先（`resume`）へ。勝利が成立していれば、スタックを捨てて 95 へ。
 */
final class EffectResolver
{
    /** 1 回の入場で処理する frame の上限（006 §7.2。超えたら暴走として例外にする） */
    public const MAX_STEPS = 500;

    public function __construct(
        private readonly EffectStack $stack,
        private readonly FrameRunner $runner,
        private readonly EffectEngine $engine,
        private readonly HandLimit $handLimit,
        private readonly GlobalValues $globals,
        private readonly SpecSource $specs,
        private readonly ConditionEvaluator $conditions,
        private readonly StoredGameMutator $mutator,
        private readonly \Closure $nameOf,
    ) {
    }

    public function resolve(): ResolveResult
    {
        $turn = $this->globals->turnPlayer();
        for ($i = 0; $i < self::MAX_STEPS; $i++) {
            if ($this->globals->endReason() !== null) {
                $this->stack->clear();

                return $this->finish(Transition::GAME_END, $turn);
            }
            $head = $this->stack->peek();
            if (($head === null || $head->status !== EffectFrame::WAITING) && $this->globals->deckOut() !== []) {
                // frame の終了時に山札切れを判定する（勝利が先。Q-05）。成立したら以降の frame は破棄
                $this->mutator->settleDeckOut();
                if ($this->globals->endReason() !== null) {
                    continue;
                }
            }
            if ($head === null) {
                return $this->finish(Transition::resumeTransition($this->globals->resume()), $turn);
            }
            if ($head->status === EffectFrame::WAITING) {
                return $this->waiting($head);
            }
            switch ($head->kind) {
                case EffectFrame::KIND_HAND_LIMIT_CHECK:
                    $over = $this->handLimit->firstOver($turn);
                    if ($over !== null) {
                        return $this->finish(Transition::HAND_LIMIT, $over);
                    }
                    $this->complete($head);
                    break;
                case EffectFrame::KIND_RECALL_ACTIVATION:
                    $this->recallActivation($head);
                    break;
                case EffectFrame::KIND_SPEC_RUN:
                case EffectFrame::KIND_OP:
                case EffectFrame::KIND_ACQUIRE_ROOM_CARD:
                    $this->run($head);
                    break;
                case EffectFrame::KIND_COMPOSE:
                    return $this->finish(Transition::COMPOSE, $head->owner);
                case EffectFrame::KIND_ANALYZE:
                    return $this->finish(Transition::ANALYSIS, $head->owner);
                case EffectFrame::KIND_END_CHECK:
                    $this->complete($head);
                    break;
                default:
                    throw new \LogicException("effect frame is not supported: {$head->kind}");
            }
        }

        throw new \LogicException('effect resolution did not finish (runaway effects)');
    }

    /**
     * プレイヤーの入力待ちの frame。選択（71）か、自己構成・精神分析の行動（61・62）。
     */
    private function waiting(EffectFrame $head): ResolveResult
    {
        $choice = $head->choice ?? throw new \LogicException('waiting frame has no choice');
        $owner = (int) ($choice['owner'] ?? $head->owner);
        $action = $choice['data']['action'] ?? null;
        if ($action === Op::COMPOSE->value) {
            return $this->finish(Transition::COMPOSE, $owner);
        }
        if ($action === Op::ANALYZE->value) {
            return $this->finish(Transition::ANALYSIS, $owner);
        }

        return $this->finish(Transition::CHOICE, $owner);
    }

    /**
     * エンジンで frame を進める。`TRIGGER` の効果は、解決の開始時に条件をもう一度評価し（008 §4.2 の 4）、
     * `ONCE_PER_TURN` を使用済みにする。
     */
    private function run(EffectFrame $head): void
    {
        $frame = $this->runner->load($head);
        $extra = [];
        if ($head->kind === EffectFrame::KIND_SPEC_RUN && ($head->params['started'] ?? false) !== true) {
            $spec = $this->specs->find($head->spec ?? '');
            if ($spec !== null && $spec->mechanism === Mechanism::TRIGGER) {
                // 同じ効果が同時に 2 度誘発しても、1 ターンに 1 回の効果は 1 度だけ解決する（008 のエッジケース）
                if ($spec->limit === EffectLimit::ONCE_PER_TURN && $this->globals->turnContext()->isOnceUsed($head->owner, $spec->id)) {
                    $this->complete($head);

                    return;
                }
                $card = isset($head->payload['card_type']) && is_string($head->payload['card_type'])
                    ? MemoryCards::get($head->payload['card_type'])
                    : null;
                if (!$this->conditions->all($spec->conditions, new ConditionContext($head->owner, $card, $head->payload ?? []))) {
                    $this->complete($head);

                    return;
                }
                if ($spec->limit === EffectLimit::ONCE_PER_TURN) {
                    $this->mutator->markOnceUsed($head->owner, $spec->id);
                }
                $this->announce($head, $spec->id);
            }
            $extra['started'] = true;
        }
        $this->runner->apply($head, $frame, $this->engine->run($frame), $extra);
    }

    /**
     * 自己構成済みカード・自我カードのトリガー効果の発動を知らせる（N11 `effectActivated`。結果は続く通知で表す）。
     */
    private function announce(EffectFrame $head, string $specId): void
    {
        $source = $head->source ?? '';
        $name = match (true) {
            isset(MemoryCards::all()[$source]) => MemoryCards::get($source)->nameEn,
            isset(EgoCards::all()[$source]) => EgoCards::get($source)->nameEn,
            default => null,
        };
        if ($name === null) {
            return;
        }
        $this->mutator->add([Notification::effectActivated($head->owner, ($this->nameOf)($head->owner), $source, $name, $specId)]);
    }

    /**
     * 想起効果の発動を、効果の `SPEC_RUN` に展開する（006 §5.2）。ターンプレイヤーだけが使える効果は他の発動者では飛ばす
     * （通知もしない。008 §7.3）。M062 の 2 回目（`nth = 2`）は先に `CONFIRM_USE` を聞く（T8-7）。
     */
    private function recallActivation(EffectFrame $head): void
    {
        $id = $head->id ?? throw new \LogicException('frame is not stored');
        $params = $head->params ?? [];
        $card = MemoryCards::get($head->source ?? throw new \LogicException('RECALL_ACTIVATION needs a source'));
        $spec = $this->specs->find(EffectCatalog::recallId($card->recall))
            ?? throw new \LogicException('unknown recall effect');
        $groups = array_map('strval', (array) ($params['groups'] ?? []));
        $allowed = $groups === [] || in_array($card->recall->group()->value, $groups, true);
        $turn = $this->globals->turnPlayer();
        if (!$allowed || ($spec->ownerScope === OwnerScope::TURN_PLAYER && $head->owner !== $turn)) {
            $this->complete($head);

            return;
        }
        $times = (int) ($params['times'] ?? 1);
        $nth = (int) ($params['nth'] ?? 1);
        if ($nth >= 2 && ($params['confirmed'] ?? false) !== true) {
            $this->stack->setParams($id, [...$params, 'gate' => EffectFrame::GATE_CONFIRM]);
            $this->stack->markWaiting($id, [
                'kind' => 'CONFIRM_USE',
                'owner' => $head->owner,
                'step' => 0,
                'data' => ['source' => $head->source, 'nth' => $nth, 'times' => $times],
            ]);

            return;
        }
        $this->mutator->add([Notification::effectActivated(
            $head->owner,
            ($this->nameOf)($head->owner),
            $card->id,
            $card->nameEn,
            $spec->id,
            $nth,
            $times,
        )]);
        $frames = [EffectFrame::specRun($spec->id, $head->owner, $card->id)];
        if ($nth < $times) {
            $frames[] = new EffectFrame(
                EffectFrame::KIND_RECALL_ACTIVATION,
                $head->owner,
                source: $card->id,
                params: ['times' => $times, 'nth' => $nth + 1, 'groups' => $groups],
            );
        }
        $this->stack->replace($id, $frames);
    }

    private function complete(EffectFrame $head): void
    {
        $this->stack->complete($head->id ?? throw new \LogicException('frame is not stored'));
    }

    private function finish(string $transition, int $activePlayer): ResolveResult
    {
        return new ResolveResult($transition, $activePlayer, $this->mutator->drain());
    }
}
