<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Effects\Query;

use Bga\Games\IRevolveThereforeIAm\Material\ActionContext;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use IRevolveTests\Support\QueryKit;
use PHPUnit\Framework\TestCase;

/**
 * 仕様書 008 §12.2 の UT-21・UT-22（鍵の読み替え）。UT-23（目標 O007）は目標カードの実装で有効化する。
 */
final class KeyViewQueryTest extends TestCase
{
    /** UT-21: M094 は精神分析で知恵鍵を万能鍵として使い、感情のコストを払える。移動では払えないが、元の種類としては使える。 */
    public function testM094ViewsWisdomAsWildInAnalysisOnly(): void
    {
        $kit = new QueryKit();
        self::assertFalse($kit->keyView->canServeAs(1, KeyType::WISDOM, KeyType::EMOTION, ActionContext::ANALYSIS));

        $kit->game->giveTableau(1, ['M094']);
        self::assertTrue($kit->keyView->canServeAs(1, KeyType::WISDOM, KeyType::EMOTION, ActionContext::ANALYSIS));
        self::assertFalse($kit->keyView->canServeAs(1, KeyType::WISDOM, KeyType::EMOTION, ActionContext::MOVE));
        self::assertFalse($kit->keyView->canServeAs(1, KeyType::WISDOM, KeyType::EMOTION, ActionContext::COMPOSE));
        self::assertTrue($kit->keyView->canServeAs(1, KeyType::WISDOM, KeyType::WISDOM, ActionContext::MOVE)); // 元の種類
        // 知恵鍵だけが読み替えの対象。意志鍵は読み替えない
        self::assertFalse($kit->keyView->canServeAs(1, KeyType::WILL, KeyType::EMOTION, ActionContext::ANALYSIS));
        self::assertSame([KeyType::WISDOM, KeyType::WILD], $kit->keyView->viewedAs(1, KeyType::WISDOM, ActionContext::ANALYSIS));
        self::assertSame([KeyType::WISDOM], $kit->keyView->viewedAs(1, KeyType::WISDOM, ActionContext::MOVE));
    }

    /** UT-22: M096 は移動で意志鍵を万能鍵として使い、知恵の錠前を払える。精神分析では払えない。 */
    public function testM096ViewsWillAsWildInMoveOnly(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(1, ['M096']);

        self::assertTrue($kit->move->canPayLock(1, KeyType::WISDOM, KeyType::WILL));
        self::assertTrue($kit->move->canPayLock(1, KeyType::WILL, KeyType::WILL));
        self::assertFalse($kit->keyView->canServeAs(1, KeyType::WILL, KeyType::WISDOM, ActionContext::ANALYSIS));
        self::assertFalse($kit->move->canPayLock(1, KeyType::WISDOM, KeyType::EMOTION)); // 感情鍵は読み替えない
    }

    public function testWildKeysServeAnythingAndOthersDoNotShareViews(): void
    {
        $kit = new QueryKit();
        $kit->game->giveTableau(2, ['M096']);

        foreach (KeyType::cases() as $needed) {
            self::assertTrue($kit->keyView->canServeAs(1, KeyType::WILD, $needed, ActionContext::MOVE));
        }
        self::assertFalse($kit->move->canPayLock(1, KeyType::WISDOM, KeyType::WILL)); // M096 は player 2 のもの
        self::assertTrue($kit->move->canPayLock(2, KeyType::WISDOM, KeyType::WILL));
    }

    /** UT-23: M096 / M094 を持つとき、O007 は 31 / 62 の中だけで獲得できる。目標カードの実装で有効化する。 */
    public function testObjectiveSevenUsesTheViewOfTheCurrentContext(): void
    {
        self::markTestSkipped('UT-23: 目標カード（ObjectiveEvaluator。008 §11.2 の順 7）の実装で有効化する');
    }
}
