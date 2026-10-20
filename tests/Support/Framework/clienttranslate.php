<?php

declare(strict_types=1);

// BGA の翻訳マーカー。PHPUnit では何もしない（PHPUnit 専用のスタブ。本番の src/ には入れない）
if (!function_exists('clienttranslate')) {
    function clienttranslate(string $text): string
    {
        return $text;
    }
}
