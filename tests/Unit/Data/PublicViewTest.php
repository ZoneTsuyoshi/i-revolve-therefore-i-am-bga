<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Data;

use Bga\GameFramework\Db\Globals;
use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Data\SpiritRepository;
use Bga\Games\IRevolveThereforeIAm\Setup\GameSetup;
use Bga\Games\IRevolveThereforeIAm\Setup\SetupPlanner;
use IRevolveTests\Support\SeededRandom;
use IRevolveTests\Support\SqliteDatabase;
use PHPUnit\Framework\TestCase;

/**
 * getAllDatas の出し分け（007 §8、§9）: 本人・他プレイヤー・観戦者で、非公開情報が漏れない。
 */
final class PublicViewTest extends TestCase
{
    private const PLAYERS = [11, 22, 33];

    private SqliteDatabase $db;

    private MemoryCardRepository $cards;

    private EgoRepository $egos;

    private PublicView $view;

    protected function setUp(): void
    {
        $this->db = SqliteDatabase::create(self::PLAYERS);
        $globals = new GlobalValues(new Globals());
        $this->cards = new MemoryCardRepository($this->db);
        $this->egos = new EgoRepository($this->db);
        $rooms = new RoomRepository($this->db);
        $spirits = new SpiritRepository($this->db);
        $objectives = new ObjectiveRepository($this->db);
        $players = new PlayerValues($this->db);
        (new GameSetup(
            new SetupPlanner(new SeededRandom(9)),
            $this->cards,
            $rooms,
            $spirits,
            $this->egos,
            $objectives,
            $players,
            $globals,
        ))->run(self::PLAYERS, SetupPlanner::LAYOUT_FIXED);
        $this->view = new PublicView($this->cards, $rooms, $spirits, $this->egos, $objectives, $players, $globals);
    }

    /**
     * 盤面（部屋・場札・捨札・自己構成済み・手札）に出てくるカードの ID。カタログ・目標・精神カードの識別子は含めない。
     *
     * @param array<string, mixed> $data
     * @return list<mixed>
     */
    private static function cardIds(array $data): array
    {
        return self::collect(array_diff_key($data, array_flip(['catalog', 'objectives', 'myObjectiveStatus', 'spirit', 'turn'])), 'id');
    }

    /**
     * 配列の中の、キー $key の値をすべて集める。
     *
     * @return list<mixed>
     */
    private static function collect(mixed $data, string $key): array
    {
        $found = [];
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                if ($k === $key && !is_array($v)) {
                    $found[] = $v;
                }
                array_push($found, ...self::collect($v, $key));
            }
        }

        return $found;
    }

    /**
     * @return list<int>
     */
    private function handIds(int $playerId): array
    {
        return array_map(static fn($c): int => $c->id, $this->cards->inLoc(MemoryLocation::Hand, $playerId));
    }

    public function testPlayerSeesOnlyOwnHand(): void
    {
        $data = $this->view->allDatas(11);

        self::assertSame($this->handIds(11), array_map(static fn(array $c): int => $c['id'], $data['myHand']));
        foreach ($data['myHand'] as $card) {
            self::assertSame($this->cards->find($card['id'])?->type, $card['type']);
            self::assertGreaterThan(0, $card['energy']);
        }
        $ids = self::cardIds($data);
        foreach ([22, 33] as $other) {
            self::assertSame([], array_values(array_intersect($ids, $this->handIds($other))), "hand of $other leaked");
        }
        self::assertSame(2, $data['playerData'][11]['handCount']);
        self::assertSame(3, $data['playerData'][22]['handCount']);
        self::assertSame(4, $data['playerData'][33]['handCount']);
    }

    public function testSpectatorSeesNoHands(): void
    {
        $data = $this->view->allDatas(999);

        self::assertSame([], $data['myHand']);
        self::assertSame([], $data['myUnder']);
        self::assertNull($data['myPickedEgo']);
        $ids = self::cardIds($data);
        foreach (self::PLAYERS as $playerId) {
            self::assertSame([], array_values(array_intersect($ids, $this->handIds($playerId))));
        }
    }

    public function testFaceDownRoomCardsShowOnlyIdAndBack(): void
    {
        $data = $this->view->allDatas(999);

        self::assertCount(12, $data['rooms']);
        foreach ($data['rooms'] as $room) {
            $card = $room['card'];
            self::assertIsArray($card);
            self::assertSame(['id', 'back', 'faceUp'], array_keys($card));
            self::assertFalse($card['faceUp']);
            self::assertMatchesRegularExpression('/^[12][BFH]$/', $card['back']);
            self::assertSame($room['level'], (int) $card['back'][0]);
        }
    }

    public function testFaceUpRoomCardShowsType(): void
    {
        $inRoom = $this->cards->inLoc(MemoryLocation::Room, 1)[0];
        $this->cards->setFaceUp($inRoom->id, true);

        $room = $this->view->allDatas(999)['rooms'][0];

        self::assertTrue($room['card']['faceUp']);
        self::assertSame($inRoom->type, $room['card']['type']);
    }

    public function testDecksShowOnlyCountAndTopBack(): void
    {
        $data = $this->view->allDatas(11);

        foreach ([1, 2] as $level) {
            $deck = $data['decks'][$level];
            self::assertSame(['count', 'top'], array_keys($deck));
            self::assertSame($this->cards->count(MemoryLocation::deck($level)), $deck['count']);
            $topType = $this->cards->topType($level);
            self::assertNotNull($topType);
            self::assertSame(PublicView::backKey(PublicView::definition($topType)), $deck['top']);
            self::assertSame($level, (int) $deck['top'][0]);
        }
        // 山札の中のカードは ID も種類も出てこない
        $deckIds = array_map(static fn($c): int => $c->id, [
            ...$this->cards->inLoc(MemoryLocation::Deck1),
            ...$this->cards->inLoc(MemoryLocation::Deck2),
            ...$this->cards->inLoc(MemoryLocation::Out),
        ]);
        self::assertSame([], array_values(array_intersect(self::cardIds($data), $deckIds)));
    }

    public function testEnhancedEgoDeckShowsCountOnly(): void
    {
        $data = $this->view->allDatas(11);

        self::assertSame(3, $data['enhDeckCount']);
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        foreach ($this->egos->inLoc(EgoRepository::ENH_DECK) as $ego) {
            // カタログには全自我カードの識別子があるが、playerData には所有していない自我は出ない
            self::assertStringNotContainsString('"initialEgo":"' . $ego['id'] . '"', $encoded);
            self::assertStringNotContainsString('"enhancedEgo":"' . $ego['id'] . '"', $encoded);
        }
        foreach (self::PLAYERS as $playerId) {
            self::assertNull($data['playerData'][$playerId]['initialEgo']);
            self::assertNull($data['playerData'][$playerId]['enhancedEgo']);
        }
    }

    public function testPickedEgoIsPrivate(): void
    {
        $pack = $this->egos->inLoc(EgoRepository::PACK);
        $this->egos->move($pack[0]['id'], EgoRepository::PICKED, 22);

        self::assertSame($pack[0]['id'], $this->view->allDatas(22)['myPickedEgo']);
        self::assertNull($this->view->allDatas(11)['myPickedEgo']);
        self::assertNull($this->view->allDatas(999)['myPickedEgo']);
        self::assertStringNotContainsString('"' . $pack[0]['id'] . '"', json_encode([
            'playerData' => $this->view->allDatas(11)['playerData'],
            'rooms' => $this->view->allDatas(11)['rooms'],
        ], JSON_THROW_ON_ERROR));
    }

    public function testOwnedEgoIsPublic(): void
    {
        $pack = $this->egos->inLoc(EgoRepository::PACK);
        $this->egos->move($pack[0]['id'], EgoRepository::OWNED, 33);

        self::assertSame($pack[0]['id'], $this->view->allDatas(999)['playerData'][33]['initialEgo']);
    }

    public function testUnderCardIsVisibleOnlyToOwner(): void
    {
        $handCard = $this->cards->inLoc(MemoryLocation::Hand, 11)[0];
        $under = $this->cards->inLoc(MemoryLocation::Hand, 11)[1];
        $this->cards->move($handCard->id, MemoryLocation::Tableau, 11);
        $this->cards->move($under->id, MemoryLocation::UnderCard, $handCard->id);

        $mine = $this->view->allDatas(11);
        self::assertSame([['host' => $handCard->id, 'cards' => [['id' => $under->id, 'type' => $under->type]]]], $mine['myUnder']);
        self::assertSame(1, $mine['playerData'][11]['composed'][0]['underCount']);

        foreach ([22, 999] as $viewer) {
            $data = $this->view->allDatas($viewer);
            self::assertSame([], $data['myUnder']);
            self::assertNotContains($under->id, self::cardIds($data));
            self::assertSame(1, $data['playerData'][11]['composed'][0]['underCount']);
        }
    }

    public function testDerivedValues(): void
    {
        $handCard = $this->cards->inLoc(MemoryLocation::Hand, 11)[0];
        $this->cards->move($handCard->id, MemoryLocation::Tableau, 11);
        $def = PublicView::definition($handCard->type);

        $data = $this->view->allDatas(22);

        self::assertSame($def->score, $data['playerData'][11]['score']);
        self::assertSame(array_sum($data['playerData'][11]['keys']), count($def->keys));
        self::assertSame(1, $data['playerData'][11]['handCount']);
        self::assertSame(0, $data['playerData'][11]['tier']);
        self::assertSame(['light' => 1, 'wildUp' => 1, 'wildUsed' => 0, 'compose' => 0, 'analysis' => 0], $data['playerData'][11]['tokens']);
    }

    public function testPublicStructureIsJsonSerializable(): void
    {
        $data = $this->view->allDatas(11);

        self::assertSame(
            ['catalog', 'turnPlayer', 'turnNo', 'phase', 'turn', 'rooms', 'open', 'decks', 'discards', 'spirit', 'enhDeckCount',
                'objectives', 'playerData', 'myHand', 'myUnder', 'myPickedEgo', 'myObjectiveStatus', 'gameEnd'],
            array_keys($data),
        );
        self::assertCount(6, $data['open']);
        self::assertCount(7, $data['spirit']);
        self::assertCount(4, $data['objectives']);
        self::assertSame('SETUP_EGO', $data['phase']);
        self::assertNull($data['gameEnd']);
        self::assertCount(99, $data['catalog']['memory']);
        self::assertNotSame('', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function testCorridor(): void
    {
        $corridor = $this->view->corridor();

        self::assertCount(12, $corridor);
        foreach ($corridor as $room) {
            self::assertContains($room['lock'], ['WISDOM', 'WILL', 'EMOTION']);
            self::assertSame(RoomRepository::levelOfPosition($room['pos']), $room['level']);
        }
    }
}
