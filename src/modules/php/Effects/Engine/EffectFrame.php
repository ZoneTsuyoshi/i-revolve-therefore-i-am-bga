<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Engine;

/**
 * 効果の解決スタックの frame 1 つ（`SPEC_RUN`。006 §7.1、007 §6、008 §10.1）のメモリ上の姿。
 * `Data/` が `effect_frame` の行と相互に変換する（`toArray` / `fromArray` は JSON 列の中身）。
 */
final class EffectFrame
{
    /**
     * @param string $specId 実行する `EffectSpec` の ID（`frame_spec`）
     * @param int $owner 効果の持ち主（選択者。想起効果では発動者）
     * @param string $source 出典のカード ID（想起効果では表向きにしたカード。`frame_source`）
     * @param int $step 次に実行する節（先行順の位置。`frame_step`）
     * @param array<int, int> $decisions 節の位置 → 決定。`OPTIONAL` は 1（行う）/ 0（行わない）、`CHOOSE_ONE` は選んだ分岐の番号
     * @param array<string, scalar|null> $payload 誘発したイベントの内容（`frame_payload`）
     */
    public function __construct(
        public readonly string $specId,
        public readonly int $owner,
        public readonly string $source = '',
        public int $step = 0,
        public array $decisions = [],
        public ?ChoiceRequest $pending = null,
        public readonly array $payload = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'spec' => $this->specId,
            'owner' => $this->owner,
            'source' => $this->source,
            'step' => $this->step,
            'decisions' => $this->decisions,
            'pending' => $this->pending?->toArray(),
            'payload' => $this->payload,
        ];
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function fromArray(array $a): self
    {
        $decisions = [];
        foreach (is_array($a['decisions'] ?? null) ? $a['decisions'] : [] as $step => $decision) {
            $decisions[(int) $step] = (int) $decision;
        }
        $pending = $a['pending'] ?? null;

        return new self(
            (string) $a['spec'],
            (int) $a['owner'],
            (string) ($a['source'] ?? ''),
            (int) ($a['step'] ?? 0),
            $decisions,
            is_array($pending) ? ChoiceRequest::fromArray($pending) : null,
            is_array($a['payload'] ?? null) ? $a['payload'] : [],
        );
    }
}
