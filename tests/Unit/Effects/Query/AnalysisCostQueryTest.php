<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Effects\Query;

use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Material\AnalysisCards;
use IRevolveTests\Support\QueryKit;
use PHPUnit\Framework\TestCase;

/**
 * 仕様書 008 §12.2 の UT-20（精神分析コスト）。
 */
final class AnalysisCostQueryTest extends TestCase
{
    /** UT-20: E02 と ALT_ANALYSIS_DISCOUNT_1 でコストが 2 減り、どの鍵を減らすかはプレイヤーが選ぶ。 */
    public function testReductionIsChosenByThePlayer(): void
    {
        $kit = new QueryKit();
        $kit->game->giveEgos(1, ['E02']);
        $reduction = $kit->analysis->reduction(1, 1);
        self::assertSame(2, $reduction);

        $r4 = AnalysisCards::get('R4'); // 知恵 2・意志 2・感情 2
        $remaining = $kit->analysis->remaining($r4, $reduction, ['WISDOM' => 1, 'EMOTION' => 1]);
        self::assertEquals(['WISDOM' => 1, 'WILL' => 2, 'EMOTION' => 1], $remaining);

        $remaining = $kit->analysis->remaining($r4, $reduction, ['WILL' => 2]);
        self::assertEquals(['WISDOM' => 2, 'EMOTION' => 2], $remaining);
    }

    public function testCostNeverGoesBelowZero(): void
    {
        $kit = new QueryKit();
        $kit->game->giveEgos(1, ['E02']);
        $r1a = AnalysisCards::get('R1A'); // 知恵 2

        self::assertSame(['WISDOM' => 1], $kit->analysis->remaining($r1a, 1, ['WISDOM' => 1]));
        // 軽減が 5 でも、減らせるのはコストの総数（2）まで
        self::assertSame([], $kit->analysis->remaining($r1a, 5, ['WISDOM' => 2]));
        self::assertSame(0, $kit->analysis->reduction(2)); // E02 を持たない人
    }

    public function testInvalidChoicesAreRejected(): void
    {
        $kit = new QueryKit();
        $r1a = AnalysisCards::get('R1A');
        $r2 = AnalysisCards::get('R2'); // 知恵 1・感情 1・意志 1

        foreach (
            [
            [$r2, 2, ['WISDOM' => 1]],                  // 減らす数が足りない
            [$r2, 1, ['WISDOM' => 1, 'WILL' => 1]],     // 減らしすぎ
            [$r1a, 3, ['WILL' => 2]],                   // コストに無い鍵
            [$r2, 1, ['NOT_A_KEY' => 1]],               // 未知の鍵
            [$r1a, 2, ['WISDOM' => 3, 'WILL' => -1]],   // 負の数
            ] as [$card, $reduction, $removed]
        ) {
            try {
                $kit->analysis->remaining($card, $reduction, $removed);
                self::fail('Expected a violation');
            } catch (EffectViolation) {
                self::addToAssertionCount(1);
            }
        }
    }
}
