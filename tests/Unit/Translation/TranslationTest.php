<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Translation;

use Bga\Games\IRevolveThereforeIAm\Material\EgoCards;
use Bga\Games\IRevolveThereforeIAm\Material\FloorCards;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use Bga\Games\IRevolveThereforeIAm\Material\TranslatableNames;
use PHPUnit\Framework\TestCase;

/**
 * 翻訳（仕様書 003 §7.1）。BGA の抽出は `clienttranslate('...')` のリテラルしか拾わないので、
 * ログの `i18n` 引数で送る名前と、ユーザーに見える例外のメッセージがリテラルで現れていることを確かめる。
 */
final class TranslationTest extends TestCase
{
    public function testEveryNameSentAsAnI18nArgumentIsExtractable(): void
    {
        $names = TranslatableNames::all();

        foreach (MemoryCards::all() as $card) {
            self::assertContains($card->nameEn, $names);
        }
        foreach (FloorCards::all() as $floor) {
            self::assertContains($floor->roomName, $names);
        }
        foreach (EgoCards::all() as $ego) {
            self::assertContains($ego->nameEn, $names);
        }
        self::assertSame(array_unique($names), $names);
    }

    /** `EffectViolation` のメッセージは `UserException` として画面に出る。英語のリテラルを `clienttranslate` で包む（変数は渡さない）。 */
    public function testUserFacingMessagesAreTranslatable(): void
    {
        $root = dirname(__DIR__, 3) . '/src/modules/php';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $bad = [];
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            foreach (file($file->getPathname()) ?: [] as $no => $line) {
                foreach (['new EffectViolation(', 'new UserException('] as $needle) {
                    $at = strpos($line, $needle);
                    if ($at === false || str_contains($line, 'class ')) {
                        continue;
                    }
                    $rest = substr($line, $at + strlen($needle));
                    $literal = str_starts_with($rest, "clienttranslate('") && !str_contains(explode("')", $rest)[0], '$');
                    // 例外から取り出して渡し直す書き方（`$e->getMessage()`）と、定数の対応表（`$this->irMessage(...)`）は翻訳済みの文字列を運ぶ
                    $forwarded = str_starts_with($rest, '$e->getMessage()') || str_starts_with($rest, '$this->irMessage(');
                    if (!$literal && !$forwarded) {
                        $bad[] = basename($file->getPathname()) . ':' . ($no + 1);
                    }
                }
            }
        }

        self::assertSame([], $bad);
    }
}
