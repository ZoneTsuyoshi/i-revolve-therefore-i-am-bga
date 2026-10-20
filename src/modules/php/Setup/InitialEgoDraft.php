<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Setup;

use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Flow\SeatOrder;

/**
 * 初期自我の秘密ドラフト（001 §4 手順 13、006 の 20〜22）。
 * 山（PACK）は 1 つで、選ぶたびに減る。「残りを次に回す」は、次の選択者が同じ PACK を見ることで表す。
 * 進み具合は setup_step（選び終えた人数）。全員が選び終えると step = 人数になる。
 */
final class InitialEgoDraft
{
    private readonly \Closure $random;

    /**
     * @param callable(int, int): int $random 両端を含む乱数（zombie の選択用）
     */
    public function __construct(
        private readonly EgoRepository $egos,
        private readonly GlobalValues $globals,
        callable $random,
    ) {
        $this->random = \Closure::fromCallable($random);
    }

    /**
     * 次に選ぶプレイヤー（手番順の逆。全員選び終えたら null）。
     *
     * @param list<int> $seats 席順（手番順）
     */
    public function nextPicker(array $seats): ?int
    {
        $order = SeatOrder::reversed($seats);
        $step = $this->globals->setupStep();

        return $order[$step] ?? null;
    }

    /**
     * 今の山の候補（表示順）。
     *
     * @return list<string>
     */
    public function candidates(): array
    {
        return array_map(static fn(array $e): string => $e['id'], $this->egos->inLoc(EgoRepository::PACK));
    }

    /**
     * 乱数で選んだ候補（zombie 用）。
     */
    public function randomCandidate(): string
    {
        $candidates = $this->candidates();
        if ($candidates === []) {
            throw new \LogicException('the initial ego pack is empty');
        }

        return $candidates[($this->random)(0, count($candidates) - 1)];
    }

    /**
     * 1 枚選ぶ（PACK → PICKED）。
     *
     * @param list<int> $seats
     * @return int 選んだ後の山の枚数
     */
    public function pick(array $seats, int $playerId, string $egoId): int
    {
        if ($this->nextPicker($seats) !== $playerId) {
            throw new \LogicException("it is not player $playerId's turn to pick");
        }
        if (!in_array($egoId, $this->candidates(), true)) {
            throw new \InvalidArgumentException("not a candidate: $egoId");
        }
        $this->egos->move($egoId, EgoRepository::PICKED, $playerId);
        $this->globals->setSetupStep($this->globals->setupStep() + 1);

        return count($this->candidates());
    }

    /**
     * 全員の選択を公開する（PICKED → OWNED）。最後に残った 1 枚は除外する（PACK → OUT。Q-20）。
     *
     * @param list<int> $seats
     * @return list<array{player_id: int, ego_id: string}> 席順
     */
    public function reveal(array $seats): array
    {
        $revealed = [];
        foreach ($seats as $playerId) {
            foreach ($this->egos->inLoc(EgoRepository::PICKED, $playerId) as $ego) {
                $this->egos->move($ego['id'], EgoRepository::OWNED, $playerId);
                $revealed[] = ['player_id' => $playerId, 'ego_id' => $ego['id']];
            }
        }
        foreach ($this->egos->inLoc(EgoRepository::PACK) as $ego) {
            $this->egos->move($ego['id'], EgoRepository::OUT);
        }

        return $revealed;
    }
}
