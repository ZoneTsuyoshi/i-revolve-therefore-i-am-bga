<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Flow;

use Bga\Games\IRevolveThereforeIAm\Effects\Query\KeyPayment;
use Bga\Games\IRevolveThereforeIAm\Material\FloorBonus;

/**
 * 検証済みの移動の 1 回の入室（直前にスキップした部屋があればその周遊ボーナスを含む）。適用はこの順に行う（008 §6.2 の 5）。
 */
final readonly class MoveStep
{
    /**
     * @param int|null $skipped この入室の直前にスキップしたレベル 2 の部屋の位置
     * @param list<int> $tapped 初めて使うために横向きにする自己構成済みカード
     * @param list<int> $discarded 初めて使うために捨てる手札
     * @param list<int> $effectCards 万能鍵（カード効果。MD-06）を与えるカード
     * @param int $lightUses 心光の行使の回数
     * @param int $lightsPaid 払う心光の個数（逓増と軽減を含む）
     * @param list<KeyPayment> $payments この入室の支払い（錠前 1 個につき 1 つ）
     */
    public function __construct(
        public int $from,
        public int $pos,
        public int $level,
        public ?int $skipped,
        public FloorBonus $skipBonus,
        public FloorBonus $entryBonus,
        public array $tapped,
        public array $discarded,
        public array $effectCards,
        public int $lightUses,
        public int $lightsPaid,
        public int $tokens,
        public array $payments,
    ) {
    }
}
