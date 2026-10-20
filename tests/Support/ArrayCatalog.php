<?php

declare(strict_types=1);

namespace IRevolveTests\Support;

use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\SpecSource;
use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;

/**
 * 任意の効果の定義を差し込める `SpecSource`。想い出カード・自我カードの本番の定義（後続のタスク）が `EffectCatalog` に入るまでは、
 * `TestSpecs`（008 §7.2・§7.6 の写し）と `EffectCatalog` を合わせて使う。
 */
final class ArrayCatalog implements SpecSource
{
    /** @var array<string, EffectSpec> */
    private array $specs = [];

    /**
     * @param list<EffectSpec> $specs
     */
    public function __construct(array $specs)
    {
        foreach ($specs as $spec) {
            $this->specs[$spec->id] = $spec;
        }
    }

    /** `EffectCatalog` の全定義 + テスト用のカード効果 */
    public static function withTestSpecs(): self
    {
        return new self([...array_values(EffectCatalog::all()), ...TestSpecs::all()]);
    }

    public function forCard(string $cardId): array
    {
        return array_values(array_filter($this->specs, static fn (EffectSpec $s): bool => $s->source === $cardId));
    }

    public function find(string $effectId): ?EffectSpec
    {
        return $this->specs[$effectId] ?? null;
    }
}
