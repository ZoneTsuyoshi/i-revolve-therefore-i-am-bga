<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Flow;

use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Flow\Notice;
use Bga\Games\IRevolveThereforeIAm\Flow\ResolveResult;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use IRevolveTests\Support\MoveFixture;
use PHPUnit\Framework\TestCase;

/**
 * 想起フェイズ（40 + 70 + 71。仕様書 006 §5.2、008 §7.3、UT-53〜UT-61）。SQLite 上の 3 人（11・22・33）のゲームで、
 * 40 が積む frame と、70・71 の往復を通して確かめる。
 */
final class RecallPhaseTest extends TestCase
{
    private MoveFixture $fx;

    protected function setUp(): void
    {
        $this->fx = new MoveFixture();
        $this->fx->place(11, 2);
    }

    /**
     * @return list<string>
     */
    private function frameKinds(): array
    {
        return array_map(
            static fn (EffectFrame $f): string => $f->kind . ':' . $f->owner,
            $this->fx->stack->all(),
        );
    }

    private function resolve(): ResolveResult
    {
        return $this->fx->kit->resolver->resolve();
    }

    /**
     * @param list<Notice> $notices
     * @return list<string>
     */
    private static function types(array $notices): array
    {
        return array_map(static fn (Notice $n): string => $n->type, $notices);
    }

    /** UT-60: 発動者は、部屋にいる全員をターンプレイヤーから時計回りに。獲得は想起の後。 */
    public function testActivatorsAreTheRoomOccupantsClockwiseFromTheTurnPlayer(): void
    {
        $fx = $this->fx;
        $fx->globals->setTurnPlayer(22);
        $fx->place(22, 2);
        $fx->place(33, 2);
        $fx->place(11, 2);
        $fx->roomCard(2, 1); // M001 DRAW_1

        $notices = $fx->kit->recall->start();

        self::assertSame(['recallStarted'], self::types($notices));
        self::assertSame([22, 33, 11], $notices[0]->args['activators']);
        self::assertSame(
            [
                'RECALL_ACTIVATION:22', 'HAND_LIMIT_CHECK:22',
                'RECALL_ACTIVATION:33', 'HAND_LIMIT_CHECK:22',
                'RECALL_ACTIVATION:11', 'HAND_LIMIT_CHECK:22',
                'ACQUIRE_ROOM_CARD:22', 'HAND_LIMIT_CHECK:22',
            ],
            $this->frameKinds(),
        );
        self::assertSame(StateId::FLOOR_ACTION_START, $fx->globals->resume());
        // 部屋のカードは表向きになり、通知に種類が載る
        self::assertTrue($fx->memory->inLoc(MemoryLocation::Room, 2)[0]->faceUp);
        self::assertSame(1, $notices[0]->args['card']['type']);
    }

    /** 発動者の列に入るのは、その部屋にいた人だけ。ほかの部屋の人は発動しない。 */
    public function testPlayersInOtherRoomsDoNotActivate(): void
    {
        $fx = $this->fx;
        $fx->place(22, 5);
        $fx->place(33, 2);
        $fx->roomCard(2, 1);

        $notices = $fx->kit->recall->start();

        self::assertSame([11, 33], $notices[0]->args['activators']);
        self::assertSame(['RECALL_ACTIVATION:11', 'RECALL_ACTIVATION:33'], array_values(array_filter(
            $this->frameKinds(),
            static fn (string $k): bool => str_starts_with($k, 'RECALL_ACTIVATION'),
        )));
    }

    /** 部屋にターンプレイヤーしかいなければ、選択の無い効果は 71 を経由せず、ターンプレイヤーが部屋のカードを獲得する（Q-04）。 */
    public function testSoloRecallResolvesWithoutAChoiceAndAcquiresTheRoomCard(): void
    {
        $fx = $this->fx;
        $roomCard = $fx->roomCard(2, 13); // M013 LIGHT_1
        $fx->kit->recall->start();

        $result = $this->resolve();

        self::assertSame(Transition::RESUME_FLOOR, $result->transition);
        self::assertSame(11, $result->activePlayer);
        self::assertSame(1, $fx->players->light(11));
        self::assertSame(MemoryLocation::Hand, $fx->memory->find($roomCard)?->location);
        self::assertSame(11, $fx->memory->find($roomCard)?->locationArg);
        self::assertSame(0, $fx->stack->count());
        self::assertContains('effectActivated', self::types($result->notices));
        self::assertContains('roomCardAcquired', self::types($result->notices));
    }

    /** 全員が自動の効果（LIGHT_1）を発動する。ターンプレイヤー以外は部屋のカードを獲得しない。 */
    public function testEveryoneInTheRoomActivatesAutomaticEffects(): void
    {
        $fx = $this->fx;
        $fx->place(22, 2);
        $fx->place(33, 2);
        $roomCard = $fx->roomCard(2, 13);
        $fx->kit->recall->start();

        $result = $this->resolve();

        self::assertSame(Transition::RESUME_FLOOR, $result->transition);
        self::assertSame([1, 1, 1], [$fx->players->light(11), $fx->players->light(22), $fx->players->light(33)]);
        self::assertSame(11, $fx->memory->find($roomCard)?->locationArg);
    }

    /** UT-56 / UT-60: OPEN_DRAW_1 は発動順に 71 で選ぶ。ターンプレイヤー → 次の人。選択者が変わるたびに activePlayer が変わる。 */
    public function testOpenDrawAsksEachActivatorInOrderAndTheyCompeteForTheField(): void
    {
        $fx = $this->fx;
        $fx->place(22, 2);
        $fx->roomCard(2, 15); // M015 OPEN_DRAW_1
        $field = array_map(
            static fn ($c): int => $c->id,
            $fx->memory->inLocAnyArg(MemoryLocation::Open),
        );
        $fx->kit->recall->start();

        $first = $this->resolve();
        self::assertSame(Transition::CHOICE, $first->transition);
        self::assertSame(11, $first->activePlayer);
        $args = $fx->kit->choices->args();
        self::assertSame('PICK_FIELD_CARD', $args['public']['choiceKind']);
        self::assertSame(11, $args['public']['chooser']);
        self::assertSame($field, $args['public']['data']['candidates']);

        $fx->kit->choices->answer(11, ['cards' => [$field[2]]]);
        $second = $this->resolve();
        self::assertSame(Transition::CHOICE, $second->transition);
        self::assertSame(22, $second->activePlayer);
        self::assertNotContains($field[2], $fx->kit->choices->args()['public']['data']['candidates']);

        $fx->kit->choices->answer(22, ['cards' => [$field[0]]]);
        $done = $this->resolve();

        self::assertSame(Transition::RESUME_FLOOR, $done->transition);
        $hand11 = array_map(static fn ($c): int => $c->id, $fx->memory->inLoc(MemoryLocation::Hand, 11));
        $hand22 = array_map(static fn ($c): int => $c->id, $fx->memory->inLoc(MemoryLocation::Hand, 22));
        self::assertContains($field[2], $hand11);
        self::assertSame([$field[0]], $hand22);
    }

    /** 選択者でない人の答えは拒否される。 */
    public function testOnlyTheChooserCanAnswer(): void
    {
        $fx = $this->fx;
        $fx->roomCard(2, 15);
        $fx->kit->recall->start();
        $this->resolve();

        $this->expectException(\Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation::class);
        $fx->kit->choices->answer(22, ['cards' => []]);
    }

    /** UT-61 / UT-54: ターンプレイヤーだけが使える想起効果（床の代替）は、ターンプレイヤー以外では frame も作らず、記録しない。 */
    public function testAlternativeEffectsAreOnlyForTheTurnPlayer(): void
    {
        $fx = $this->fx;
        $fx->place(22, 2);
        $fx->roomCard(2, 32); // M032 ALT_COMPOSE
        $fx->kit->recall->start();

        self::assertSame(
            ['RECALL_ACTIVATION:11', 'HAND_LIMIT_CHECK:11', 'ACQUIRE_ROOM_CARD:11', 'HAND_LIMIT_CHECK:11'],
            $this->frameKinds(),
        );
        $result = $this->resolve();

        self::assertSame(Transition::RESUME_FLOOR, $result->transition);
        self::assertSame([['kind' => 'COMPOSE', 'discount' => 0]], $fx->globals->turnContext()->floorAlternatives);
        self::assertContains('floorAlternativeRecorded', self::types($result->notices));
    }

    /** UT-54: 記録は種類ごとに 1 つ。割引が大きいほうが残る（M062 で 2 回発動しても 1 つ）。 */
    public function testAlternativesAreMergedPerKind(): void
    {
        $fx = $this->fx;
        $fx->giveTableau(11, [62]);
        $fx->place(22, 2);
        $fx->roomCard(2, 32);
        $fx->kit->recall->start();

        self::assertSame(2, $fx->globals->turnContext()->recall['counts'][11]);
        self::assertSame(1, $fx->globals->turnContext()->recall['counts'][22]);
        $this->resolve(); // 2 回目の確認を聞く
        $fx->kit->choices->answer(11, ['use' => true]);
        $this->resolve();

        self::assertSame([['kind' => 'COMPOSE', 'discount' => 0]], $fx->globals->turnContext()->floorAlternatives);
    }

    /** UT-53: M062 は、他の人格がいる部屋でのターンプレイヤーの発動を 2 回にする。2 回目は確認を聞く。単独の部屋では 1 回。 */
    public function testM062DoublesTheTurnPlayersActivationWithAConfirmation(): void
    {
        $fx = $this->fx;
        $fx->giveTableau(11, [62]);
        $fx->place(22, 2);
        $fx->roomCard(2, 13); // LIGHT_1
        $fx->kit->recall->start();

        $first = $this->resolve();
        self::assertSame(Transition::CHOICE, $first->transition);
        self::assertSame(1, $fx->players->light(11)); // 1 回目は自動
        $args = $fx->kit->choices->args();
        self::assertSame('CONFIRM_USE', $args['public']['choiceKind']);
        self::assertTrue($args['public']['skippable']);

        $fx->kit->choices->answer(11, ['use' => true]);
        $second = $this->resolve();

        self::assertSame(Transition::RESUME_FLOOR, $second->transition);
        self::assertSame(2, $fx->players->light(11));
        self::assertSame(1, $fx->players->light(22)); // 他の人格は 1 回
    }

    public function testM062SecondActivationCanBeDeclined(): void
    {
        $fx = $this->fx;
        $fx->giveTableau(11, [62]);
        $fx->place(22, 2);
        $fx->roomCard(2, 13);
        $fx->kit->recall->start();
        $this->resolve();

        $fx->kit->choices->skip(11);
        $done = $this->resolve();

        self::assertSame(Transition::RESUME_FLOOR, $done->transition);
        self::assertSame(1, $fx->players->light(11));
    }

    public function testM062DoesNothingInASoloRoom(): void
    {
        $fx = $this->fx;
        $fx->giveTableau(11, [62]);
        $fx->roomCard(2, 13);
        $fx->kit->recall->start();

        self::assertSame(1, $fx->globals->turnContext()->recall['counts'][11]);
        self::assertSame(Transition::RESUME_FLOOR, $this->resolve()->transition);
        self::assertSame(1, $fx->players->light(11));
    }

    /** UT-57: 想起効果の移動。次のレベル 1 の部屋へ。移動先は表向きにならず、獲得するのは開始時の部屋のカード。入室に数える。 */
    public function testMoveNextAdvancesToTheNextLevel1RoomAndAcquiresTheStartingRoomCard(): void
    {
        $fx = $this->fx;
        $fx->place(11, 2);
        $roomCard = $fx->roomCard(2, 33); // M033 MOVE_NEXT_LV1
        $field = $fx->memory->inLocAnyArg(MemoryLocation::Open);
        $fx->kit->recall->start();

        $ask = $this->resolve();
        self::assertSame(Transition::CHOICE, $ask->transition);
        self::assertSame('CONFIRM_MOVE', $fx->kit->choices->args()['public']['choiceKind']);
        $moved = $fx->kit->choices->answer(11, ['use' => true]);
        self::assertContains('personaMoved', self::types($moved));
        $pick = $this->resolve();
        self::assertSame(Transition::CHOICE, $pick->transition);
        self::assertSame('PICK_FIELD_CARD', $fx->kit->choices->args()['public']['choiceKind']);
        $fx->kit->choices->answer(11, ['cards' => [$field[0]->id]]);
        $done = $this->resolve();

        self::assertSame(Transition::RESUME_FLOOR, $done->transition);
        self::assertSame(3, $fx->players->persona(11));
        self::assertSame([3], $fx->globals->turnContext()->enteredRooms);
        self::assertSame(1, $fx->globals->turnContext()->roomsEnteredCount);
        self::assertFalse($fx->memory->inLoc(MemoryLocation::Room, 3)[0]->faceUp);
        self::assertSame(11, $fx->memory->find($roomCard)?->locationArg);
        self::assertSame(MemoryLocation::Hand, $fx->memory->find($roomCard)?->location);
        // 床アクションは移動先の部屋のもの（FAQ 行 8）
        self::assertSame(3, $fx->kit->floor->floorNumber());
    }

    /** UT-57: 通過したレベル 2 の部屋の周遊ボーナスを得る。移動を断れば何も起きない。 */
    public function testMoveNextPassesLevel2RoomsForTheirBonusAndCanBeDeclined(): void
    {
        $fx = $this->fx;
        $fx->place(11, 3);
        $fx->roomCard(3, 33);
        $fx->kit->recall->start();
        $this->resolve();
        $fx->kit->choices->answer(11, ['use' => true]); // 3 → 5（4 は F04 POSEIDON の周遊ボーナス = 心光）
        $this->resolve();
        self::assertSame(5, $fx->players->persona(11));
        self::assertSame(1, $fx->players->light(11));
        self::assertSame(1, $fx->globals->turnContext()->lightsGained);

        $fx2 = new MoveFixture();
        $fx2->place(11, 3);
        $fx2->roomCard(3, 33);
        $fx2->kit->recall->start();
        $fx2->kit->resolver->resolve();
        $fx2->kit->choices->skip(11);
        $fx2->kit->resolver->resolve();
        self::assertSame(3, $fx2->players->persona(11));
        self::assertSame(0, $fx2->players->light(11));
    }

    /** 想起効果の移動は、ターンプレイヤー以外は発動できない（Q-39）。 */
    public function testMoveEffectsAreNotAvailableToOthers(): void
    {
        $fx = $this->fx;
        $fx->place(22, 2);
        $fx->roomCard(2, 33);
        $fx->kit->recall->start();
        $this->resolve();
        $fx->kit->choices->skip(11);
        $result = $this->resolve();

        self::assertSame(Transition::RESUME_FLOOR, $result->transition);
        self::assertSame(2, $fx->players->persona(22));
    }

    /** UT-80: 選択の途中で再読み込みしても、DB から同じ選択が復元され、続きが解決できる。 */
    public function testPendingChoiceSurvivesReload(): void
    {
        $fx = $this->fx;
        $fx->roomCard(2, 15);
        $fx->kit->recall->start();
        $this->resolve();
        $before = $fx->kit->choices->args();

        $fresh = $fx->newKit();
        self::assertEquals($before, $fresh->choices->args());
        self::assertTrue($fresh->choices->isWaiting());
        $field = $fx->memory->inLocAnyArg(MemoryLocation::Open);
        $fresh->choices->answer(11, ['cards' => [$field[1]->id]]);

        self::assertSame(Transition::RESUME_FLOOR, $fresh->resolver->resolve()->transition);
    }

    /** 手札上限: 想起で 9 枚になったら、その発動者の単位の終わりに 72 へ（ターンプレイヤーを先に）。 */
    public function testHandLimitIsCheckedAtTheEndOfEachActivatorsUnit(): void
    {
        $fx = $this->fx;
        $fx->place(22, 2);
        $fx->giveHandAny(22, 8);
        $fx->roomCard(2, 1); // DRAW_1: 22 は 9 枚になる
        $fx->kit->recall->start();

        $result = $this->resolve();

        self::assertSame(Transition::HAND_LIMIT, $result->transition);
        self::assertSame(22, $result->activePlayer);
        // 22 が捨てるまで、ターンプレイヤー（11）の獲得は始まらない
        self::assertSame(1, $fx->memory->count(MemoryLocation::Hand, 11));
        self::assertSame(9, $fx->memory->count(MemoryLocation::Hand, 22));

        $discard = $fx->handLimit->zombieChoice(22);
        $fx->handLimit->discardExcess(22, 'P22', $discard);
        $next = $this->resolve();

        self::assertSame(Transition::RESUME_FLOOR, $next->transition);
        self::assertSame(2, $fx->memory->count(MemoryLocation::Hand, 11)); // 引いた 1 枚 + 部屋のカード
    }

    /** 勝利が成立したら、残りの発動は行わず終局へ（Q-09）。 */
    public function testResolvingStopsWhenTheGameEnds(): void
    {
        $fx = $this->fx;
        $fx->place(22, 2);
        $fx->roomCard(2, 13);
        $fx->kit->recall->start();
        $fx->globals->setEndReason('FRAGMENTS');
        $fx->globals->setWinnerId(11);

        $result = $this->resolve();

        self::assertSame(Transition::GAME_END, $result->transition);
        self::assertSame(0, $fx->stack->count());
        self::assertSame(0, $fx->players->light(11));
    }

    /** 13 種の想起効果が全て、frame を積んで 70 で最後まで解決できる（選択は既定の答えで）。 */
    public function testAllThirteenRecallEffectsResolveToTheFloorPhase(): void
    {
        // RC-01〜RC-13 に対応する、その想起効果を持つ想い出カードの番号
        $cards = [1 => 1, 15 => 2, 13 => 3, 14 => 4, 22 => 5, 24 => 6, 23 => 7, 31 => 8, 32 => 9, 94 => 10, 95 => 11, 33 => 12, 96 => 13];
        foreach ($cards as $type => $rc) {
            $fx = new MoveFixture();
            $fx->place(11, 2);
            $fx->giveHandAny(11, 3);
            $fx->giveTableau(11, [4]);
            $fx->roomCard(2, $type);
            $fx->kit->recall->start();
            $guard = 0;
            do {
                $result = $fx->kit->resolver->resolve();
                if ($result->transition === Transition::CHOICE) {
                    $fx->kit->choices->auto($result->activePlayer);
                }
            } while ($result->transition === Transition::CHOICE && ++$guard < 10);

            self::assertSame(Transition::RESUME_FLOOR, $result->transition, "RC-{$rc} (M{$type})");
            self::assertSame(0, $fx->stack->count(), "RC-{$rc}");
        }
    }
}
