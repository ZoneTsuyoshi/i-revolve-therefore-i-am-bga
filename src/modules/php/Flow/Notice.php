<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

/**
 * 送る通知 1 件（仕様書 007 §10）。Flow/ はフレームワークを呼ばないので、通知は値として作り、Game が送る。
 */
final readonly class Notice
{
    /**
     * @param string $type 通知名（camelCase）
     * @param string $message ログ文言（clienttranslate で包んだ英語原文。ログに出さないときは空文字）
     * @param array<string, mixed> $args
     */
    public function __construct(
        public string $type,
        public string $message,
        public array $args = [],
    ) {
    }
}
