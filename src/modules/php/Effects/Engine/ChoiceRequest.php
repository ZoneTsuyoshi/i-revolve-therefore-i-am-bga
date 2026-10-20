<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Engine;

use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;

/**
 * 効果の解決が待っている選択（`effect_frame.frame_choice` の中身。008 §6.4）。
 *
 * `kind` が null のときは `CHOOSE_ONE`（床アクションの分岐。51 `actChooseFloorAction` で聞く）で、`data['branches']` に分岐の数が入る。
 */
final readonly class ChoiceRequest
{
    /**
     * @param int $owner 選択者
     * @param int $step 待っている節（先行順の位置）
     * @param array<string, mixed> $data 候補など（JSON にできる値。手札の中身は含めない）
     */
    public function __construct(
        public ?ChoiceKind $kind,
        public int $owner,
        public int $step,
        public array $data = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind?->value, 'owner' => $this->owner, 'step' => $this->step, 'data' => $this->data];
    }

    /**
     * @param array<string, mixed> $a
     */
    public static function fromArray(array $a): self
    {
        $kind = $a['kind'] ?? null;

        return new self(
            is_string($kind) ? ChoiceKind::from($kind) : null,
            (int) ($a['owner'] ?? 0),
            (int) ($a['step'] ?? 0),
            is_array($a['data'] ?? null) ? $a['data'] : [],
        );
    }
}
