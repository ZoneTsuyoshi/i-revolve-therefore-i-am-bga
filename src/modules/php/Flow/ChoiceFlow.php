<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Data\EffectStack;
use Bga\Games\IRevolveThereforeIAm\Data\StoredGameMutator;
use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\SpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectEngine;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Effects\Trigger\TriggeredEffect;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;

/**
 * 71 `EffectChoice`: 汎用の選択（006 §7.4、008 §6.4）。保留中の frame（`WAITING`）に答えを反映し、続きを解決する。
 *
 * 保留中の frame は 3 種類:
 * - エンジンの frame（`SPEC_RUN` など）が止まった選択。答えは `EffectEngine::answer()` が検証する。
 * - 確認の門（`gate = CONFIRM`）が付いた frame（M062 の 2 回目の想起、「してもよい」の誘発効果）。答えは `{"use": true|false}`。
 * - `ORDER_EFFECTS`。答えは `{"index": n}`（候補の何番目を次に解決するか）。
 */
final class ChoiceFlow
{
    public function __construct(
        private readonly EffectStack $stack,
        private readonly FrameRunner $runner,
        private readonly EffectEngine $engine,
        private readonly GameView $view,
        private readonly StoredGameMutator $mutator,
        private readonly SpecSource $specs,
        private readonly TriggerPlanner $planner,
    ) {
    }

    /**
     * 71 の args。公開部分と、選択者にだけ見せる部分（`_private`）。手札の中身はクライアントが持つので含めない。
     *
     * @return array{public: array<string, mixed>, private: array<string, mixed>}
     */
    public function args(): array
    {
        $head = $this->stack->peek();
        $choice = $head !== null && $head->status === EffectFrame::WAITING && $head->choice !== null ? ChoiceRequest::fromArray($head->choice) : null;
        if ($head === null || $choice === null) {
            return ['public' => ['chooser' => null, 'choiceKind' => null, 'source' => null, 'data' => [], 'skippable' => false], 'private' => []];
        }
        $data = $choice->data;
        $private = [];
        if ($choice->kind === ChoiceKind::SELECT_SWAP_SAME_COST && isset($data['pairs'])) {
            $private['pairs'] = $data['pairs'];
            unset($data['pairs']);
        }
        if ($choice->kind === ChoiceKind::PICK_ENHANCED_EGO) {
            $private['egoCandidates'] = $this->view->enhancedEgoIds();
        }

        return [
            'public' => [
                'chooser' => $choice->owner,
                'choiceKind' => $choice->kind?->value,
                'source' => $head->source,
                'spec' => $head->spec,
                'data' => $data,
                'skippable' => $this->skippable($head, $choice),
            ],
            'private' => $private,
        ];
    }

    /**
     * 選択に答える。
     *
     * @param array<string, mixed> $payload 選択の種類ごとの回答（008 §6.4）
     * @return list<Notice>
     * @throws EffectViolation
     */
    public function answer(int $player, array $payload): array
    {
        [$head] = $this->head($player);
        if (($head->params['gate'] ?? null) === EffectFrame::GATE_CONFIRM) {
            $this->confirm($head, ($payload['use'] ?? null) === true);
        } elseif ($head->kind === EffectFrame::KIND_ORDER_EFFECTS) {
            $this->order($head, $payload['index'] ?? null);
        } else {
            $frame = $this->runner->load($head);
            $this->runner->apply($head, $frame, $this->engine->answer($frame, $payload));
        }

        return $this->mutator->drain();
    }

    /**
     * 選択をスキップする（スキップできない種類は {@see EffectViolation}）。
     *
     * @return list<Notice>
     * @throws EffectViolation
     */
    public function skip(int $player): array
    {
        [$head] = $this->head($player);
        if (($head->params['gate'] ?? null) === EffectFrame::GATE_CONFIRM) {
            $this->confirm($head, false);
        } elseif ($head->kind === EffectFrame::KIND_ORDER_EFFECTS) {
            throw new EffectViolation('The order of effects cannot be skipped');
        } else {
            $frame = $this->runner->load($head);
            $this->runner->apply($head, $frame, $this->engine->skip($frame));
        }

        return $this->mutator->drain();
    }

    /**
     * 時間切れ（zombie）と仮の操作の既定の答え（008 §6.4）。スキップできればスキップし、必須の選択は種類ごとの既定で答える。
     *
     * @return list<Notice>
     */
    public function auto(int $player): array
    {
        [$head, $choice] = $this->head($player);
        $default = $this->defaultAnswer($head, $choice, $player);

        return $default === null ? $this->skip($player) : $this->answer($player, $default);
    }

    /**
     * 既定の答え（スキップするなら null）。
     *
     * @return array<string, mixed>|null
     */
    public function defaultAnswer(EffectFrame $head, ChoiceRequest $choice, int $player): ?array
    {
        if ($head->kind === EffectFrame::KIND_ORDER_EFFECTS) {
            $ids = array_map(static fn (array $c): string => (string) $c['spec'], (array) ($choice->data['candidates'] ?? []));
            $min = $ids === [] ? null : min($ids);

            return ['index' => $min === null ? 0 : (int) array_search($min, $ids, true)];
        }
        if (($head->params['gate'] ?? null) === EffectFrame::GATE_CONFIRM) {
            return null;
        }
        $data = $choice->data;

        return match ($choice->kind) {
            null => ['branch' => 0],
            ChoiceKind::PICK_FIELD_CARD => ['cards' => array_slice(array_map('intval', (array) ($data['candidates'] ?? [])), 0, (int) ($data['count'] ?? 1))],
            ChoiceKind::PICK_DISCARD => ['cards' => $this->discardDefault($player, (int) ($data['count'] ?? 1))],
            ChoiceKind::PICK_ENHANCED_EGO => ['ego' => min($this->view->enhancedEgoIds())],
            ChoiceKind::PICK_SPIRIT_BRANCH => ['spirit' => min(array_map('strval', (array) ($data['candidates'] ?? [])))],
            default => null,
        };
    }

    /**
     * 71 にいるか（保留中の選択がエンジンの選択・確認・順序のいずれか）。
     */
    public function isWaiting(): bool
    {
        $head = $this->stack->peek();

        return $head !== null && $head->status === EffectFrame::WAITING && $head->choice !== null
            && ($head->choice['data']['action'] ?? null) === null;
    }

    /**
     * @return array{EffectFrame, ChoiceRequest}
     * @throws EffectViolation
     */
    private function head(int $player): array
    {
        $head = $this->stack->peek();
        if ($head === null || $head->status !== EffectFrame::WAITING || $head->choice === null) {
            throw new EffectViolation('There is no choice to make');
        }
        $choice = ChoiceRequest::fromArray($head->choice);
        if ($choice->isAction()) {
            throw new EffectViolation('A compose or analysis action is pending, not a choice');
        }
        if ($choice->owner !== $player) {
            throw new EffectViolation('It is not your choice');
        }

        return [$head, $choice];
    }

    private function skippable(EffectFrame $head, ChoiceRequest $choice): bool
    {
        if ($head->kind === EffectFrame::KIND_ORDER_EFFECTS) {
            return false;
        }

        return $choice->kind === null ? false : $choice->kind->skippable();
    }

    /**
     * 確認の門を開く（使う）か、frame を捨てる（使わない）。
     */
    private function confirm(EffectFrame $head, bool $use): void
    {
        $id = $head->id ?? throw new \LogicException('frame is not stored');
        if (!$use) {
            $this->stack->complete($id);

            return;
        }
        $params = $head->params ?? [];
        unset($params['gate']);
        $params['confirmed'] = true;
        $this->stack->setParams($id, $params);
        $this->stack->markReady($id);
    }

    /**
     * `ORDER_EFFECTS`: 選んだ効果を次に解決する。残りは、まだ衝突するなら再び順序を聞く。
     */
    private function order(EffectFrame $head, mixed $index): void
    {
        $id = $head->id ?? throw new \LogicException('frame is not stored');
        $effects = array_values((array) ($head->params['effects'] ?? []));
        if (!is_int($index) || !isset($effects[$index])) {
            throw new EffectViolation('Choose one of the effects');
        }
        $chosen = $effects[$index];
        unset($effects[$index]);
        $frames = $this->planner->framesFor([$this->triggered($chosen)]);
        array_push($frames, ...$this->planner->framesFor(array_map($this->triggered(...), array_values($effects))));
        $this->stack->replace($id, $frames);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function triggered(array $entry): TriggeredEffect
    {
        $spec = $this->specs->find((string) $entry['spec']) ?? throw new \LogicException('unknown effect');
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];

        return new TriggeredEffect($spec, (string) $entry['source'], $payload);
    }

    /**
     * `PICK_DISCARD` の既定: レベル 1 を優先して手札の後ろから（006 §10）。
     *
     * @return list<int>
     */
    private function discardDefault(int $player, int $count): array
    {
        $hand = $this->view->handIds($player);
        usort($hand, function (int $a, int $b): int {
            $la = MemoryCards::get($this->view->cardType($a))->level;
            $lb = MemoryCards::get($this->view->cardType($b))->level;

            return $la <=> $lb ?: $b <=> $a;
        });

        return array_slice($hand, 0, $count);
    }
}
