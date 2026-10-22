<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Stats;

use Bga\Games\IRevolveThereforeIAm\Flow\Notification;
use Bga\Games\IRevolveThereforeIAm\Stats\StatsRecorder;
use IRevolveTests\Support\ArrayStatStore;
use PHPUnit\Framework\TestCase;

/**
 * 統計（仕様書 007 §14、`src/stats.jsonc`）。
 */
final class StatsRecorderTest extends TestCase
{
    private const P = 11;

    private ArrayStatStore $store;

    private StatsRecorder $stats;

    protected function setUp(): void
    {
        $this->store = new ArrayStatStore([self::P, 22]);
        $this->stats = new StatsRecorder($this->store);
        $this->stats->initialize();
    }

    /** `stats.jsonc` の項目と、初期化する項目は同じ（片方だけ増減すると BGA で実行時エラーになる）。 */
    public function testInitializesExactlyTheStatsOfStatsJsonc(): void
    {
        $json = (string) file_get_contents(dirname(__DIR__, 3) . '/src/stats.jsonc');
        $decoded = json_decode((string) preg_replace('#^\s*//.*$#m', '', $json), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        self::assertSame(array_keys($decoded['table']), array_keys($this->store->table));
        self::assertSame(array_keys($decoded['player']), array_keys($this->store->player));
        self::assertSame(StatsRecorder::TABLE_STATS, array_keys($this->store->table));
        self::assertSame(StatsRecorder::PLAYER_STATS, array_keys($this->store->player));
        foreach ($this->store->player as $values) {
            self::assertSame([self::P => 0, 22 => 0], $values);
        }
    }

    public function testMovementCounters(): void
    {
        $payment = ['tapped' => [], 'discarded' => [], 'light' => 0, 'wild_tokens' => 0, 'lock_reduction' => 0];
        $this->stats->record(Notification::roomEntered(self::P, 'P', 1, 2, 'HERMES', 1, $payment, 1));
        $this->stats->record(Notification::roomSkipped(self::P, 'P', 4, 'ARES'));
        $this->stats->record(Notification::personaMoved(self::P, 'P', 2, 5, 'ATHENA', 'RECALL', true));
        $this->stats->record(Notification::personaMoved(self::P, 'P', 5, 7, 'ATHENA', 'WARP', false));

        self::assertSame(2, $this->store->player['rooms_entered'][self::P]);
        self::assertSame(1, $this->store->player['rooms_skipped'][self::P]);
        self::assertSame(2, $this->stats->roomsEntered(self::P));
        self::assertSame(0, $this->stats->roomsEntered(22));
    }

    public function testTokenCounters(): void
    {
        $this->stats->record(Notification::tokensChanged(self::P, 'P', ['light' => 2], ['light' => 3], 'FLOOR'));
        $this->stats->record(Notification::tokensChanged(self::P, 'P', ['light' => -1], ['light' => 2], 'PAYMENT'));
        $this->stats->record(Notification::tokensChanged(self::P, 'P', ['wild_up' => -1, 'wild_used' => 1], ['wild_up' => 0, 'wild_used' => 1], 'PAYMENT'));
        $this->stats->record(Notification::tokensChanged(self::P, 'P', ['wild_used' => -1, 'wild_up' => 1], ['wild_up' => 1, 'wild_used' => 0], 'CLEANUP'));
        $this->stats->record(Notification::tokensChanged(self::P, 'P', ['compose' => -1], ['compose' => 0], 'PAYMENT'));
        $this->stats->record(Notification::tokensChanged(self::P, 'P', ['analysis' => -1], ['analysis' => 0], 'PAYMENT'));
        $this->stats->record(Notification::tokensChanged(self::P, 'P', ['analysis' => 1], ['analysis' => 1], 'FLOOR'));

        self::assertSame(2, $this->store->player['light_gained'][self::P]);
        self::assertSame(1, $this->store->player['light_spent'][self::P]);
        self::assertSame(1, $this->store->player['wild_tokens_used'][self::P], 'クリーンアップで表に戻すのは使用に数えない');
        self::assertSame(1, $this->store->player['compose_tokens_used'][self::P]);
        self::assertSame(1, $this->store->player['analysis_tokens_used'][self::P]);
    }

    public function testCardAndEffectCounters(): void
    {
        $card = ['id' => 5, 'type' => 1];
        $this->stats->record(Notification::cardsDrawn(self::P, 'P', [$card, $card], 'FLOOR', [], 3));
        $this->stats->record(Notification::cardsDiscarded(self::P, 'P', [$card], 'LIGHT', [1 => 0, 2 => 0], 2));
        $this->stats->record(Notification::cardsDiscarded(self::P, 'P', [$card, $card], 'HAND_LIMIT', [1 => 0, 2 => 0], 8));
        $this->stats->record(Notification::cardsDiscarded(self::P, 'P', [$card], 'COST', [1 => 0, 2 => 0], 8));
        $this->stats->record(Notification::cardComposed(self::P, 'P', $card, 'Abandoned Idea', 'PHASE', ['discarded' => [], 'light' => 0, 'energy' => 0], 1, 2));
        $this->stats->record(Notification::spiritAdvanced(self::P, 'P', null, 'R1A', 1, 'ACTION', true, null, 1));
        $this->stats->record(Notification::spiritAdvanced(self::P, 'P', 'R1A', 'R2', 2, 'EFFECT', false, null, 1));
        $this->stats->record(Notification::objectiveClaimed(self::P, 'P', 1, 'Hand Goal', 2));
        $this->stats->record(Notification::effectActivated(self::P, 'P', 'M001', 'Abandoned Idea', 'RC-01'));
        $this->stats->record(Notification::effectActivated(self::P, 'P', 'M073', 'Card', 'M073.1'));

        $p = static fn (array $values): int => $values[self::P];
        self::assertSame(2, $p($this->store->player['cards_drawn']));
        self::assertSame(1, $p($this->store->player['cards_discarded_for_light']));
        self::assertSame(2, $p($this->store->player['cards_discarded_limit']));
        self::assertSame(1, $p($this->store->player['cards_composed']));
        self::assertSame(1, $p($this->store->player['analysis_done']), '効果による上昇は精神分析に数えない');
        self::assertSame(1, $p($this->store->player['goals_claimed']));
        self::assertSame(1, $p($this->store->player['recall_effects']));
        self::assertSame(1, $p($this->store->player['activated_effects']));
    }

    public function testNoticesWithoutAPlayerAreIgnored(): void
    {
        $before = $this->store->player;
        $this->stats->record(Notification::deckOut(1));
        $this->stats->record(Notification::scoreUpdated([]));

        self::assertSame($before, $this->store->player);
    }

    public function testTurnCounters(): void
    {
        $this->stats->turnStarted(self::P);
        $this->stats->turnFinished(5);
        $this->stats->turnStarted(22);
        $this->stats->turnFinished(3);

        self::assertSame(2, $this->store->table['turns_played']);
        self::assertSame(1, $this->store->player['turns_taken'][self::P]);
        self::assertSame(1, $this->store->player['turns_taken'][22]);
        self::assertSame(5, $this->store->table['max_rooms_in_turn'], '最大値を保つ');
    }

    public function testFinalizeWritesTheResults(): void
    {
        $results = [
            ['player_id' => self::P, 'memory_score' => 15, 'objective_score' => 3, 'attached_score' => 2, 'total' => 20, 'tier' => 3, 'rooms_entered' => 0],
            ['player_id' => 22, 'memory_score' => 4, 'objective_score' => 0, 'attached_score' => 0, 'total' => 4, 'tier' => 1, 'rooms_entered' => 0],
        ];
        $keys = [self::P => ['WISDOM' => 3, 'WILL' => 2, 'EMOTION' => 5, 'WILD' => 1]];

        $this->stats->finalize('APEX_OF_PSYCHE', $results, $keys);

        self::assertSame(3, $this->store->table['end_reason']);
        self::assertSame(20, $this->store->player['final_score'][self::P]);
        self::assertSame(17, $this->store->player['memory_score'][self::P], '想い出カード + I08 の下');
        self::assertSame(3, $this->store->player['objective_score'][self::P]);
        self::assertSame(3, $this->store->player['final_tier'][self::P]);
        self::assertSame([3, 2, 5, 1], [
            $this->store->player['key_wisdom'][self::P],
            $this->store->player['key_will'][self::P],
            $this->store->player['key_emotion'][self::P],
            $this->store->player['key_wild'][self::P],
        ]);
        self::assertSame(0, $this->store->player['key_emotion'][22]);
    }

    /** @return iterable<string, array{?string, int}> */
    public static function endReasons(): iterable
    {
        yield 'fragments' => ['FRAGMENTS', 1];
        yield 'instant memory' => ['INSTANT_MEMORY', 2];
        yield 'apex' => ['APEX_OF_PSYCHE', 3];
        yield 'deck out' => ['DECK_OUT', 4];
        yield 'none' => [null, 0];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('endReasons')]
    public function testEndReasonValues(?string $reason, int $expected): void
    {
        $this->stats->finalize($reason, [], []);

        self::assertSame($expected, $this->store->table['end_reason']);
    }
}
