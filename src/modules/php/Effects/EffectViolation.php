<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects;

/**
 * 入力がカード効果の規則に反するときの例外（コスト計算・選択の検証）。状態クラスは `act` の入力エラーとして扱う。
 */
final class EffectViolation extends \DomainException
{
}
