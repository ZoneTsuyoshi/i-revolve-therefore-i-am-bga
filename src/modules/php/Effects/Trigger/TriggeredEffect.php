<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Trigger;

use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;

/**
 * イベントで誘発した効果 1 つ（008 §4.2）。持ち主は常にターンプレイヤー（§4.1）。
 */
final readonly class TriggeredEffect
{
    /**
     * @param string $source 出典のカード ID（自己構成済みカード `M0NN`、自我カード `I01` など。精神カードのボーナスでは `R2` など）
     * @param array<string, scalar|null> $payload 誘発したイベントの内容（解決の開始時の条件評価に使う）
     */
    public function __construct(
        public EffectSpec $spec,
        public string $source,
        public array $payload = [],
    ) {
    }
}
