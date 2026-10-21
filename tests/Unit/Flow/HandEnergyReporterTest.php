<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Flow\HandEnergyReporter;
use IRevolveTests\Support\QueryKit;
use PHPUnit\Framework\TestCase;

/**
 * N35 `handEnergyUpdated`（仕様書 007 §10、008 MD-02）。M098 を手札に持つ本人にだけ、手札の現在のエネルギーを送る。
 */
final class HandEnergyReporterTest extends TestCase
{
    private QueryKit $kit;

    private HandEnergyReporter $reporter;

    protected function setUp(): void
    {
        $this->kit = new QueryKit();
        $this->reporter = new HandEnergyReporter($this->kit->compose, $this->kit->game, static fn (): array => [1, 2]);
    }

    public function testSilentWithoutM098(): void
    {
        $this->kit->game->giveHand(1, ['M001']);
        $this->reporter->prime();
        $this->kit->game->giveTableau(1, ['M002']);

        self::assertSame([], $this->reporter->report());
    }

    public function testReportsTheCurrentEnergiesOfTheOwnerWhenTheKeysChange(): void
    {
        [$m098, $other] = $this->kit->game->giveHand(1, ['M098', 'M001']);
        $this->reporter->prime();
        $this->kit->game->giveTableau(1, ['M002', 'M002', 'M002']); // 感情鍵 3 本 → M098 は 4

        $notices = $this->reporter->report();

        self::assertCount(1, $notices);
        self::assertSame('handEnergyUpdated', $notices[0]->type);
        self::assertSame(1, $notices[0]->recipient, '本人にだけ');
        self::assertSame('', $notices[0]->message);
        self::assertSame(
            [['card_id' => $m098, 'energy' => 4], ['card_id' => $other, 'energy' => 2]],
            $notices[0]->args['energies'],
        );
        self::assertSame([], $this->reporter->report(), '変わらなければ送らない');
    }

    public function testReportsWhenM098LeavesTheHandSoThatStaleBadgesAreCleared(): void
    {
        [$m098, $other] = $this->kit->game->giveHand(1, ['M098', 'M001']);
        $this->kit->game->giveTableau(1, ['M002']);
        $this->reporter->prime();
        $this->kit->game->hand[1] = [$other];

        $notices = $this->reporter->report();

        self::assertCount(1, $notices);
        self::assertSame([['card_id' => $other, 'energy' => 2]], $notices[0]->args['energies']);
        self::assertNotContains($m098, array_column($notices[0]->args['energies'], 'card_id'));
    }

    public function testEnergiesOfReturnsThePrintedValueWithoutModifiers(): void
    {
        [$card] = $this->kit->game->giveHand(2, ['M001']);

        self::assertSame([$card => 2], $this->reporter->energiesOf(2));
    }
}
