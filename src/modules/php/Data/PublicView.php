<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\CatalogSpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Objective\ObjectiveWindow;
use Bga\Games\IRevolveThereforeIAm\Flow\GameScoreCalculator;
use Bga\Games\IRevolveThereforeIAm\Flow\ObjectiveBoard;
use Bga\Games\IRevolveThereforeIAm\Material\AnalysisCards;
use Bga\Games\IRevolveThereforeIAm\Material\EgoCards;
use Bga\Games\IRevolveThereforeIAm\Material\FloorCards;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCardDefinition;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;
use Bga\Games\IRevolveThereforeIAm\Material\ObjectiveCards;

/**
 * `getAllDatas` と `getArgs` の公開部分を作る唯一の入口（仕様書 007 §8、§12.2）。視点（本人・他プレイヤー・観戦者）ごとに出し分ける。
 *
 * 非公開のカードの `card_id` と `card_type` を同時に外へ出さない（007 §9）。
 * - 手札は本人にだけ ID と種類。他人・観戦者には枚数のみ。
 * - 部屋の裏向きのカードは ID と裏面のキー（1B など）だけ。想起で表向きの間は種類も出す。
 * - 山札は枚数と一番上の裏面のキーだけ（二枚目以降は出さない。Q-44）。除外したカード（OUT）は枚数も出さない。
 * - 強化自我の山は枚数のみ。ドラフトの山と選択中の初期自我は本人にだけ（公開前）。
 * - I08 の下のカード（UNDER_CARD）は本人にだけ ID と種類、他人には枚数のみ。
 */
final class PublicView
{
    public function __construct(
        private readonly MemoryCardRepository $memoryCards,
        private readonly RoomRepository $rooms,
        private readonly SpiritRepository $spirits,
        private readonly EgoRepository $egos,
        private readonly ObjectiveRepository $objectives,
        private readonly PlayerValues $players,
        private readonly GlobalValues $globals,
        private readonly ?\Closure $heldEnergy = null,
        private readonly ?\Closure $roomsEntered = null,
    ) {
    }

    /**
     * `getAllDatas` の戻り値（キー `players` はフレームワーク用なので含めない。独自の値は `playerData` に入れる。007 §8.2）。
     *
     * @param int $viewerId 閲覧者。卓のプレイヤーでなければ観戦者として扱う
     * @return array<string, mixed>
     */
    public function allDatas(int $viewerId): array
    {
        $seats = $this->players->playerIds();
        $isPlayer = in_array($viewerId, $seats, true);

        $hand = $this->memoryCards->inLocAnyArg(MemoryLocation::Hand);
        $tableau = $this->memoryCards->inLocAnyArg(MemoryLocation::Tableau);
        $underCard = $this->memoryCards->inLocAnyArg(MemoryLocation::UnderCard);

        $result = [
            'catalog' => self::catalog(),
            'turnPlayer' => $this->globals->turnPlayer(),
            'turnNo' => $this->globals->turnNo(),
            'phase' => $this->globals->phase()->value,
            'turn' => $this->turn(),
            'rooms' => $this->roomsView(),
            'open' => $this->openView(),
            'decks' => $this->decks(),
            'discards' => [
                '1' => $this->faceUpList(MemoryLocation::Discard1),
                '2' => $this->faceUpList(MemoryLocation::Discard2),
            ],
            'spirit' => $this->spirits->all(),
            'enhDeckCount' => $this->egos->count(EgoRepository::ENH_DECK),
            'objectives' => $this->objectivesView(),
            'playerData' => $this->playerData($seats, $hand, $tableau, $underCard),
            'myHand' => [],
            'myUnder' => [],
            'myPickedEgo' => null,
            'myObjectiveStatus' => [],
            'gameEnd' => $this->gameEnd($seats),
        ];

        if ($isPlayer) {
            $result['myHand'] = $this->myHand($viewerId, $hand);
            $result['myUnder'] = $this->myUnder($viewerId, $tableau, $underCard);
            $result['myPickedEgo'] = $this->myPickedEgo($viewerId);
            $result['myObjectiveStatus'] = $this->myObjectiveStatus($viewerId);
        }

        return $result;
    }

    /**
     * 静的なカタログ（識別子と数値のみ。秘密情報ではない。効果文は含めない。007 §8.2）。
     * card_id と card_type の対応は含まれない。
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function catalog(): array
    {
        $memory = [];
        foreach (MemoryCards::all() as $def) {
            $memory[] = [
                'type' => self::typeNumber($def->id),
                'id' => $def->id,
                'nameEn' => $def->nameEn,
                'nameId' => $def->nameId,
                'count' => $def->count,
                'level' => $def->level,
                'energy' => $def->energy,
                'keys' => array_map(static fn(KeyType $k): string => $k->value, $def->keys),
                'back' => self::backKey($def),
                'score' => $def->score,
                'effectKind' => $def->effectKind->value,
                'recall' => $def->recall->value,
            ];
        }
        $floor = [];
        foreach (FloorCards::all() as $def) {
            $floor[] = [
                'id' => self::typeNumber($def->id),
                'code' => $def->id,
                'roomName' => $def->roomName,
                'nameId' => $def->nameId,
                'level' => $def->level,
                'bonus' => $def->bonus->value,
                'defaultPosition' => $def->defaultPosition,
            ];
        }
        $spirit = [];
        foreach (AnalysisCards::all() as $def) {
            $spirit[] = [
                'id' => $def->id,
                'tier' => $def->floor,
                'branch' => $def->branch,
                'cost' => $def->cost,
                'hasLight' => $def->hasLight,
            ];
        }
        $ego = [];
        foreach (EgoCards::all() as $def) {
            $ego[] = [
                'id' => $def->id,
                'kind' => $def->kind->value,
                'nameEn' => $def->nameEn,
                'nameId' => $def->nameId,
                'effectKind' => $def->effectKind?->value,
            ];
        }
        $objective = [];
        foreach (ObjectiveCards::all() as $def) {
            $objective[] = [
                'id' => self::typeNumber($def->id),
                'code' => $def->id,
                'nameId' => $def->nameId,
                'scoreTable' => $def->scoreTable,
            ];
        }

        return ['memory' => $memory, 'floor' => $floor, 'spirit' => $spirit, 'ego' => $ego, 'objective' => $objective];
    }

    /**
     * 想い出カードの裏面のキー（`Rev1B` → `1B`）。
     */
    public static function backKey(MemoryCardDefinition $def): string
    {
        return substr($def->back->value, 3);
    }

    /**
     * 種類（card_type = M 番号の数値部分）から定義を引く。
     */
    public static function definition(int $type): MemoryCardDefinition
    {
        return MemoryCards::get(sprintf('M%03d', $type));
    }

    /**
     * 想い出の山の一番上の裏面のキー（空なら null）。山の一番上の種類は返さない。
     */
    public function topBack(int $level): ?string
    {
        $type = $this->memoryCards->topType($level);

        return $type === null ? null : self::backKey(self::definition($type));
    }

    /**
     * プレイヤーごとの自己構成スコアの内訳。想い出カード（自己構成済み）、I08 の下のカード、目標カード。
     * `Game::irScoreCalculator()`（勝利判定）と同じ計算器を使う。
     *
     * @param list<int> $seats
     * @return array<int, array{memory: int, attached: int, objective: int, total: int}>
     */
    public function scores(array $seats): array
    {
        return (new GameScoreCalculator($this->memoryCards, $this->objectives, $this->evaluator()))->scores($seats);
    }

    private function evaluator(): ObjectiveEvaluator
    {
        return ObjectiveEvaluator::forView(
            new StoredGameView($this->memoryCards, $this->egos, $this->players, $this->globals, $this->rooms, $this->spirits),
            new CatalogSpecSource(),
        );
    }

    /**
     * 本人から見た、場に残っている目標の現在の X・得点・獲得できるか。窓はフェイズから分かる範囲（31 と 81。62 は状態が決める）。
     *
     * @return list<array{id: int, x: int, points: int, claimable: bool}>
     */
    private function myObjectiveStatus(int $viewerId): array
    {
        $window = match ($this->globals->phase()) {
            GamePhase::Move => ObjectiveWindow::move(),
            GamePhase::TurnEnd => ObjectiveWindow::turnEnd(),
            default => ObjectiveWindow::none(),
        };
        $board = new ObjectiveBoard($this->objectives, $this->evaluator(), $this->globals);

        return array_map(
            static fn(array $s): array => ['id' => $s['obj_id'], 'x' => $s['x'], 'points' => $s['points'], 'claimable' => $s['claimable']],
            $board->statuses($viewerId, $window),
        );
    }

    /**
     * 終局の内訳（N31 `gameEnded` の results）。
     *
     * @param list<int> $seats
     * @return list<array<string, int>> player_id、memory_score、objective_score、attached_score、total、tier、rooms_entered（統計と同じ値）
     */
    public function results(array $seats): array
    {
        $rows = [];
        foreach ($this->scores($seats) as $playerId => $s) {
            $rows[] = [
                'player_id' => $playerId,
                'memory_score' => $s['memory'],
                'objective_score' => $s['objective'],
                'attached_score' => $s['attached'],
                'total' => $s['total'],
                'tier' => $this->tier($playerId),
                'rooms_entered' => $this->roomsEntered === null ? 0 : (int) ($this->roomsEntered)($playerId),
            ];
        }

        return $rows;
    }

    /**
     * 精神階層（player_spirit が NULL なら 0）。
     */
    public function tier(int $playerId): int
    {
        $spirit = $this->players->spirit($playerId);

        return $spirit === null ? 0 : $this->spirits->tierOf($spirit);
    }

    /**
     * 移動の計画に使う、各部屋のレベルと錠前（裏面。公開情報）。カードの無い部屋（補充の途中）は lock が null。
     *
     * @return list<array{pos: int, level: int, lock: ?string}>
     */
    public function corridor(): array
    {
        $locks = [];
        foreach ($this->memoryCards->inLocAnyArg(MemoryLocation::Room) as $card) {
            $locks[$card->locationArg] = self::definition($card->type)->back->lock()->value;
        }
        $out = [];
        foreach (array_keys($this->rooms->all()) as $pos) {
            $out[] = ['pos' => $pos, 'level' => RoomRepository::levelOfPosition($pos), 'lock' => $locks[$pos] ?? null];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function turn(): array
    {
        $t = $this->globals->turnContext();

        return [
            'enteredRooms' => $t->enteredRooms,
            'roomsEnteredCount' => $t->roomsEnteredCount,
            'skippedRooms' => $t->skippedRooms,
            'objectiveClaimed' => $t->objectiveClaimed,
            'lightsGained' => $t->lightsGained,
            'analysisActionsDone' => $t->analysisActionsDone,
            'turnComposeDiscount' => $t->turnComposeDiscount,
            'nextComposeDiscount' => $t->nextComposeDiscount,
            'recall' => $t->recall,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roomsView(): array
    {
        $cards = [];
        foreach ($this->memoryCards->inLocAnyArg(MemoryLocation::Room) as $card) {
            $cards[$card->locationArg] = $card;
        }
        $under = [];
        foreach ($this->memoryCards->inLocAnyArg(MemoryLocation::UnderFloor) as $card) {
            $under[$card->locationArg][] = ['id' => $card->id, 'type' => $card->type];
        }

        $out = [];
        foreach ($this->rooms->all() as $pos => $floor) {
            $card = null;
            if (isset($cards[$pos])) {
                $c = $cards[$pos];
                $card = ['id' => $c->id, 'back' => self::backKey(self::definition($c->type)), 'faceUp' => $c->faceUp];
                if ($c->faceUp) {
                    $card['type'] = $c->type;
                }
            }
            $out[] = [
                'pos' => $pos,
                'floor' => $floor,
                'level' => RoomRepository::levelOfPosition($pos),
                'card' => $card,
                'under' => $under[$pos] ?? [],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{slot: int, id: int, type: int}>
     */
    private function openView(): array
    {
        return array_map(
            static fn(MemoryCard $c): array => ['slot' => $c->locationArg, 'id' => $c->id, 'type' => $c->type],
            $this->memoryCards->inLocAnyArg(MemoryLocation::Open),
        );
    }

    /**
     * @return array{1: array{count: int, top: ?string}, 2: array{count: int, top: ?string}}
     */
    private function decks(): array
    {
        $deck = fn(int $level): array => [
            'count' => $this->memoryCards->count(MemoryLocation::deck($level)),
            'top' => $this->topBack($level),
        ];

        return [1 => $deck(1), 2 => $deck(2)];
    }

    /**
     * 表向きの所在（捨札）の一覧。捨てた順。
     *
     * @return list<array{id: int, type: int}>
     */
    private function faceUpList(MemoryLocation $loc): array
    {
        return array_map(
            static fn(MemoryCard $c): array => ['id' => $c->id, 'type' => $c->type],
            $this->memoryCards->inLoc($loc),
        );
    }

    /**
     * 使う目標カード（OUT は出さない）。獲得済みも枠を保ったまま持ち主付きで返す。
     *
     * @return list<array{id: int, slot: ?int, owner: ?int}>
     */
    private function objectivesView(): array
    {
        $out = [];
        foreach ($this->objectives->all() as $o) {
            if ($o['state'] === ObjectiveRepository::OUT) {
                continue;
            }
            $out[] = ['id' => $o['id'], 'slot' => $o['slot'], 'owner' => $o['owner']];
        }

        return $out;
    }

    /**
     * @param list<int> $seats
     * @param list<MemoryCard> $hand
     * @param list<MemoryCard> $tableau
     * @param list<MemoryCard> $underCard
     * @return array<int, array<string, mixed>>
     */
    private function playerData(array $seats, array $hand, array $tableau, array $underCard): array
    {
        $scores = $this->scores($seats);
        $owned = $this->egos->inLoc(EgoRepository::OWNED);
        $objectives = $this->objectives->all();

        $out = [];
        foreach ($seats as $playerId) {
            $keys = [KeyType::WISDOM->value => 0, KeyType::WILL->value => 0, KeyType::EMOTION->value => 0, KeyType::WILD->value => 0];
            $composed = [];
            foreach ($tableau as $card) {
                if ($card->locationArg !== $playerId) {
                    continue;
                }
                foreach (self::definition($card->type)->keys as $key) {
                    $keys[$key->value]++;
                }
                $composed[] = [
                    'id' => $card->id,
                    'type' => $card->type,
                    'tapped' => $card->tapped,
                    'underCount' => count(array_filter($underCard, static fn(MemoryCard $u): bool => $u->locationArg === $card->id)),
                ];
            }
            $initialEgo = null;
            $enhancedEgo = null;
            foreach ($owned as $ego) {
                if ($ego['owner'] !== $playerId) {
                    continue;
                }
                if (str_starts_with($ego['id'], 'I')) {
                    $initialEgo = $ego['id'];
                } else {
                    $enhancedEgo = $ego['id'];
                }
            }
            $out[$playerId] = [
                'persona' => $this->players->persona($playerId),
                'spirit' => $this->players->spirit($playerId),
                'tier' => $this->tier($playerId),
                'tokens' => [
                    'light' => $this->players->light($playerId),
                    'wildUp' => $this->players->wildUp($playerId),
                    'wildUsed' => $this->players->wildUsed($playerId),
                    'compose' => $this->players->composeTokens($playerId),
                    'analysis' => $this->players->analysisTokens($playerId),
                ],
                'handCount' => count(array_filter($hand, static fn(MemoryCard $c): bool => $c->locationArg === $playerId)),
                'score' => $scores[$playerId]['total'],
                'keys' => $keys,
                'composed' => $composed,
                'initialEgo' => $initialEgo,
                'enhancedEgo' => $enhancedEgo,
                'objectives' => array_values(array_map(
                    static fn(array $o): int => $o['id'],
                    array_filter($objectives, static fn(array $o): bool => $o['owner'] === $playerId),
                )),
            ];
        }

        return $out;
    }

    /**
     * 本人の手札。`energy` は現在の自己構成エネルギー（M098 込み）。
     *
     * @param list<MemoryCard> $hand
     * @return list<array{id: int, type: int, energy: int}>
     */
    private function myHand(int $viewerId, array $hand): array
    {
        $out = [];
        foreach ($hand as $card) {
            if ($card->locationArg === $viewerId) {
                // M098 などの保有時の効果を含めた現在のエネルギー（008 MD-02）。計算の口が無いときは印刷の値
                $energy = $this->heldEnergy === null
                    ? self::definition($card->type)->energy
                    : (int) ($this->heldEnergy)($viewerId, $card->id);
                $out[] = ['id' => $card->id, 'type' => $card->type, 'energy' => $energy];
            }
        }

        return $out;
    }

    /**
     * 本人の自己構成済みカードの下のカード（I08）。
     *
     * @param list<MemoryCard> $tableau
     * @param list<MemoryCard> $underCard
     * @return list<array{host: int, cards: list<array{id: int, type: int}>}>
     */
    private function myUnder(int $viewerId, array $tableau, array $underCard): array
    {
        $out = [];
        foreach ($tableau as $host) {
            if ($host->locationArg !== $viewerId) {
                continue;
            }
            $cards = [];
            foreach ($underCard as $u) {
                if ($u->locationArg === $host->id) {
                    $cards[] = ['id' => $u->id, 'type' => $u->type];
                }
            }
            if ($cards !== []) {
                $out[] = ['host' => $host->id, 'cards' => $cards];
            }
        }

        return $out;
    }

    /**
     * 本人がドラフトで選んだ、まだ公開されていない初期自我。
     */
    private function myPickedEgo(int $viewerId): ?string
    {
        $picked = $this->egos->inLoc(EgoRepository::PICKED, $viewerId);

        return $picked === [] ? null : $picked[0]['id'];
    }

    /**
     * 終局後だけ（end_reason が決まっているとき）。
     *
     * @param list<int> $seats
     * @return array<string, mixed>|null
     */
    private function gameEnd(array $seats): ?array
    {
        $reason = $this->globals->endReason();
        if ($reason === null) {
            return null;
        }

        return ['reason' => $reason, 'winnerId' => $this->globals->winnerId(), 'results' => $this->results($seats)];
    }

    private static function typeNumber(string $id): int
    {
        return (int) substr($id, 1);
    }
}
