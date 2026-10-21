<?php

declare(strict_types=1);

namespace IRevolveTests\Unit\Cards;

use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Effects\Trigger\ConflictResolver;
use Bga\Games\IRevolveThereforeIAm\Effects\Trigger\TriggeredEffect;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\Event;
use IRevolveTests\Support\CardBench;
use PHPUnit\Framework\TestCase;

/**
 * 自己構成済みカード・自我カードのトリガー効果（008 §7.2・§7.6。UT-28〜UT-35、UT-41〜UT-43、UT-47・UT-48、UT-50・UT-51）。
 * 手番は 11。イベントを起こし、解決スタックを自動で進めて結果を確かめる。
 */
final class TriggerCardsTest extends TestCase
{
    private CardBench $b;

    protected function setUp(): void
    {
        $this->b = new CardBench();
    }

    // ---- 精神階層（EV-06） ----

    /** UT-28: M058 は精神分析アクションで、心光の無い精神カードに進んだときだけ心光 +1（origin = EFFECT）。 */
    public function testM058GainsLightOnlyWhenTheDestinationHadNone(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [58]);
        self::assertTrue($this->b->emit(Event::TIER_UP, ['via' => 'ACTION', 'dest_had_light' => false]));
        $this->b->drive();
        self::assertSame(1, $this->b->light());

        $this->b->emit(Event::TIER_UP, ['via' => 'ACTION', 'dest_had_light' => true]);
        $this->b->drive();
        self::assertSame(1, $this->b->light(), '心光のある精神カードでは働かない');
    }

    /** UT-28・UT-43: I02 は精神カード上の心光に追加 +1。M058 の心光（origin = EFFECT）には追加が無い。 */
    public function testI02OnlyReactsToSpiritCardLight(): void
    {
        $this->b->ego('I02');
        $this->b->fx->giveTableau(CardBench::P, [58]);
        $this->b->emit(Event::TIER_UP, ['via' => 'ACTION', 'dest_had_light' => false]);
        $this->b->drive();
        self::assertSame(1, $this->b->light(), 'M058 の心光に I02 の追加は無い');

        foreach (['EFFECT', 'FLOOR', 'RECALL', 'TOUR', 'EXTRA'] as $origin) {
            self::assertFalse($this->b->emit(Event::LIGHT_GAINED, ['count' => 1, 'origin' => $origin]), $origin);
        }
        self::assertTrue($this->b->emit(Event::LIGHT_GAINED, ['count' => 1, 'origin' => 'SPIRIT_CARD']));
        $this->b->drive();
        self::assertSame(2, $this->b->light());
    }

    /** UT-29: M058 は M088 による上昇（via = EFFECT）に反応しない。M079〜M081 は反応する。 */
    public function testM058IgnoresEffectTierUpButM079ToM081React(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [58, 79, 80, 81]);
        $before = $this->b->hand();
        $this->b->emit(Event::TIER_UP, ['via' => 'EFFECT', 'dest_had_light' => false]);
        $this->b->drive();

        self::assertSame(1, $this->b->light(), 'M079 の 1 つだけ（M058 は不発）');
        self::assertSame(1, $this->b->fx->globals->turnContext()->nextComposeDiscount, 'M080');
        self::assertSame($before + 1, $this->b->hand(), 'M081（場札）');
    }

    /** UT-36: 場札を取る M081 と、手札を使う第二階層のボーナス（R2）は衝突して順序を聞く。M079・M080 と第一階層のボーナスは衝突しない。 */
    public function testConflictsBetweenTierUpTriggersAndBonuses(): void
    {
        $resolver = new ConflictResolver();
        $fx = $this->b->fx;
        $of = static fn (string $id, string $source): TriggeredEffect => new TriggeredEffect(EffectCatalog::get($id), $source);

        self::assertTrue($resolver->needsOrder([$of('R2', 'R2'), $of('M081.1', 'M081')]));
        self::assertFalse($resolver->needsOrder([$of('R1A', 'R1A'), $of('M079.1', 'M079'), $of('M080.1', 'M080'), $of('M081.1', 'M081')]));
        self::assertFalse($resolver->needsOrder([$of('R2', 'R2'), $of('M079.1', 'M079'), $of('M080.1', 'M080')]), 'ボーナスの自己構成は ND を消費しない');
        self::assertNotNull($fx);
    }

    // ---- 移動の終わり（EV-02・EV-03・EV-04） ----

    /** UT-30: M067 は 4 部屋で心光 +1。EV-02 と EV-03 で同時に誘発しても 1 回だけ。8 部屋でも 1 回。 */
    public function testM067FiresOncePerTurn(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [67]);
        $this->b->turn(static function ($c): void {
            $c->roomsEnteredCount = 3;
        });
        self::assertFalse($this->b->emit(Event::MOVE_ENDED));

        $this->b->turn(static function ($c): void {
            $c->roomsEnteredCount = 8;
        });
        $this->b->fx->kit->triggers->emit([
            new \Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent(Event::MOVE_ENDED, CardBench::P),
            new \Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent(Event::PERSONA_STOPPED, CardBench::P, ['room' => 5]),
        ]);
        $this->b->drive();
        self::assertSame(1, $this->b->light());

        self::assertFalse($this->b->emit(Event::PERSONA_STOPPED, ['room' => 5, 'via' => 'RECALL_MOVE']), '使用済み');
    }

    /** UT-30: 想起効果の移動（EV-03）で 4 部屋に達してもよい。 */
    public function testM067ReactsToARecallMove(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [67]);
        $this->b->turn(static function ($c): void {
            $c->roomsEnteredCount = 4;
        });
        self::assertTrue($this->b->emit(Event::PERSONA_STOPPED, ['room' => 6, 'via' => 'RECALL_MOVE']));
        $this->b->drive();
        self::assertSame(1, $this->b->light());
    }

    /** UT-32: M069 は 4 部屋以上で場札を 1 枚。場札が無ければ何も起きない。 */
    public function testM069TakesAnOpenCardOrNothing(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [69]);
        $this->b->turn(static function ($c): void {
            $c->roomsEnteredCount = 4;
        });
        $this->b->emit(Event::MOVE_ENDED);
        // 場札 6 枚から 1 枚を選ぶ
        $field = array_map(static fn ($c): int => $c->id, $this->b->fx->memory->inLocAnyArg(MemoryLocation::Open));
        self::assertSame(Transition::CHOICE, $this->b->fx->kit->resolver->resolve()->transition);
        $this->b->fx->kit->choices->answer(CardBench::P, ['cards' => [$field[2]]]);
        $this->b->drive();
        self::assertSame(1, $this->b->hand());

        // 場札が 0 枚なら無視する（FAQ 行 6）
        $this->b->fx->db->execute("UPDATE `memory_card` SET `card_loc` = 'OUT' WHERE `card_loc` = 'OPEN'");
        $this->b->turn(static function ($c): void {
            $c->onceUsed = [];
        });
        $this->b->emit(Event::MOVE_ENDED);
        $this->b->drive();
        self::assertSame(1, $this->b->hand());
    }

    /** UT-33: M068（修飾）と M069（トリガー）が同時に条件を満たしても、順序は聞かない。 */
    public function testM068AndM069DoNotAskForAnOrder(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [68, 69, 67]);
        $this->b->turn(static function ($c): void {
            $c->roomsEnteredCount = 4;
        });
        $this->b->emit(Event::MOVE_ENDED);
        $kinds = [];
        $head = $this->b->fx->stack->all();
        foreach ($head as $frame) {
            $kinds[] = $frame->kind;
        }
        self::assertNotContains('ORDER_EFFECTS', $kinds, 'M067 と M069 は触る資源が違うので固定順');
    }

    /** UT-47: I06 は万能移動鍵トークンを使わなかった移動の終わりに 1 枚引く。1 つでも使えば引かない。 */
    public function testI06DrawsWhenNoWildTokenWasUsed(): void
    {
        $this->b->ego('I06');
        $this->b->emit(Event::MOVE_ENDED);
        $this->b->drive();
        self::assertSame(1, $this->b->hand());

        $b = new CardBench();
        $b->ego('I06');
        $b->turn(static function ($c): void {
            $c->moveSummary['wild_tokens_used'] = 1;
        });
        self::assertFalse($b->emit(Event::MOVE_ENDED));
    }

    /** UT-48: I07 は経路の途中の部屋に他の人格がいたとき 1 枚（1 ターンに 1 回）。 */
    public function testI07DrawsOncePerTurn(): void
    {
        $this->b->ego('I07');
        $fx = $this->b->fx;
        $fx->kit->triggers->emit([
            new \Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent(Event::PERSONA_PASSED_OTHER, CardBench::P, ['room' => 5]),
            new \Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent(Event::PERSONA_PASSED_OTHER, CardBench::P, ['room' => 6]),
        ]);
        $this->b->drive();
        self::assertSame(1, $this->b->hand());
        self::assertFalse($this->b->emit(Event::PERSONA_PASSED_OTHER, ['room' => 7]));
    }

    // ---- 自己構成（EV-05） ----

    /**
     * 自己構成済みに `$type` を加えて、EV-05 を起こす。
     *
     * @return int 自己構成したカード ID
     */
    private function composed(int $type): int
    {
        $id = $this->b->fx->giveTableau(CardBench::P, [$type])[0];
        $count = $this->b->fx->memory->count(MemoryLocation::Tableau, CardBench::P);
        $this->b->emit(Event::COMPOSED, [
            'card_type' => sprintf('M%03d', $type),
            'ctx' => 'PHASE',
            'composed_count' => $count,
        ]);

        return $id;
    }

    /** UT-34: 知恵・感情の 2 本を持つカード（M082）で M073 と M074 の両方が引く。同じ鍵が 2 本のカード（M094）では 1 回。 */
    public function testKeyTriggersCountEachKindOnce(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [73, 74, 75]);
        $this->composed(82);
        $this->b->drive();
        self::assertSame(2, $this->b->hand(), 'M073（知恵）と M074（感情）');

        $b = new CardBench();
        $b->fx->giveTableau(CardBench::P, [73]);
        $id = $b->fx->giveTableau(CardBench::P, [94])[0];
        $b->emit(Event::COMPOSED, ['card_type' => 'M094', 'ctx' => 'PHASE', 'composed_count' => 2]);
        $b->drive();
        self::assertSame(1, $b->hand(), '知恵鍵が 2 本でも 1 回');
        self::assertNotSame(0, $id);
    }

    /** UT-34: M076 は知恵鍵で心光、M078 は意志鍵で場札。 */
    public function testM076AndM078(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [76, 78]);
        $this->composed(1);
        $this->b->drive();
        self::assertSame(1, $this->b->light());

        $field = array_map(static fn ($c): int => $c->id, $this->b->fx->memory->inLocAnyArg(MemoryLocation::Open));
        $this->composed(3);
        $this->b->fx->kit->resolver->resolve();
        $this->b->fx->kit->choices->answer(CardBench::P, ['cards' => [$field[0]]]);
        $this->b->drive();
        self::assertSame(1, $this->b->hand());
    }

    /** UT-35（R8-3）: M073〜M078 は自身の自己構成には反応しない。 */
    public function testKeyTriggersIgnoreTheirOwnComposition(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [73]);
        $this->composed(76);
        $this->b->drive();
        self::assertSame(1, $this->b->hand(), 'M073 は M076 の自己構成に反応する');
        self::assertSame(0, $this->b->light(), 'M076 自身の心光は無い');
    }

    /** UT-41（R8-4）: M090 は自身を含む枚数 3 枚につき万能移動鍵トークン 1 つ。 */
    public function testM090CountsItselfInThrees(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [1, 2, 3, 4, 5, 6, 7, 8]);
        $this->composed(90);
        $this->b->drive();
        self::assertSame(3, $this->b->fx->players->wildUp(CardBench::P), '9 枚 → 3 つ');

        $b = new CardBench();
        $b->fx->giveTableau(CardBench::P, [1]);
        $b->emit(Event::COMPOSED, ['card_type' => 'M090', 'ctx' => 'PHASE', 'composed_count' => 2]);
        $b->fx->giveTableau(CardBench::P, [90]);
        // 2 枚（自身を含む）→ 0
        $b->drive();
        self::assertSame(0, $b->fx->players->wildUp(CardBench::P));
    }

    /** UT-41（R8-4）: M097 は自身の知恵鍵 2 本を含めて心光。M099 は意志鍵の本数だけ場札（足りなければ取れるだけ）。 */
    public function testM097AndM099CountPrintedKeys(): void
    {
        $this->b->fx->giveTableau(CardBench::P, [1, 4]);
        $this->composed(97);
        $this->b->drive();
        self::assertSame(4, $this->b->light(), '知恵鍵 1 + 1 + 2');

        // M099: 意志鍵 3 本 + 自身の 2 本 = 5 枚。場札が 3 枚しかなければ取れるだけ取る（FAQ 行 6 に準ずる）
        $b = new CardBench();
        $b->fx->giveTableau(CardBench::P, [3, 6, 9]);
        $b->fx->db->execute("UPDATE `memory_card` SET `card_loc` = 'OUT' WHERE `card_loc` = 'OPEN' AND `card_loc_arg` > 2");
        $b->fx->giveTableau(CardBench::P, [99]);
        $b->emit(Event::COMPOSED, ['card_type' => 'M099', 'ctx' => 'PHASE', 'composed_count' => 4]);
        $b->drive();
        self::assertSame(3, $b->hand());
    }

    /** UT-42: I01 は自己構成済みが 1・3・5・7・9 枚目になったとき 1 枚引く。2・4・6 枚目では引かない。 */
    public function testI01OnlyOnOddCounts(): void
    {
        $this->b->ego('I01');
        foreach ([1, 3, 5, 7, 9] as $count) {
            self::assertTrue($this->b->emit(Event::COMPOSED, ['card_type' => 'M001', 'composed_count' => $count]), (string) $count);
            $this->b->drive();
        }
        foreach ([2, 4, 6] as $count) {
            self::assertFalse($this->b->emit(Event::COMPOSED, ['card_type' => 'M001', 'composed_count' => $count]), (string) $count);
        }
        self::assertSame(5, $this->b->hand());
    }

    // ---- ターン開始（EV-01） ----

    /** UT-50・UT-51: E03・E04・E05 はターン開始時に引く / 心光 / 万能移動鍵トークン。衝突しないので順序は聞かない。 */
    public function testTurnStartEgosResolveInAFixedOrder(): void
    {
        $this->b->ego('E03', 'E04', 'E05');
        self::assertTrue($this->b->emit(Event::TURN_START));
        $transition = $this->b->drive();

        self::assertSame(Transition::RESUME_MOVE, $transition);
        self::assertSame([], $this->b->choices, '順序の質問なし');
        self::assertSame(1, $this->b->hand());
        self::assertSame(1, $this->b->light());
        self::assertSame(1, $this->b->fx->players->wildUp(CardBench::P));
    }

    /** UT-51: E03 と I05 は手札を触るので衝突し、ORDER_EFFECTS を聞く。 */
    public function testE03AndI05AskForAnOrder(): void
    {
        $this->b->ego('E03', 'I05');
        $this->b->emit(Event::TURN_START);
        $first = $this->b->fx->kit->resolver->resolve();
        self::assertSame(Transition::CHOICE, $first->transition);
        self::assertSame('ORDER_EFFECTS', $this->b->fx->kit->choices->args()['public']['choiceKind']);
    }

    /** UT-51: M052 と M055 は触る資源が違うので固定順。M052 と I05 は衝突する。 */
    public function testSwapCardsEffectsConflictWithTheEntrepreneur(): void
    {
        $resolver = new ConflictResolver();
        $of = static fn (string $id, string $source): TriggeredEffect => new TriggeredEffect(EffectCatalog::get($id), $source);

        self::assertFalse($resolver->needsOrder([$of('M052.1', 'M052'), $of('M055.1', 'M055')]));
        self::assertTrue($resolver->needsOrder([$of('M052.1', 'M052'), $of('I05.1', 'I05')]));
        self::assertTrue($resolver->needsOrder([$of('M072.1', 'M072'), $of('M052.1', 'M052')]));
        self::assertTrue($resolver->needsOrder([$of('M072.1', 'M072'), $of('M055.1', 'M055')]));
        self::assertFalse($resolver->needsOrder([$of('M052.1', 'M052'), $of('M053.1', 'M053'), $of('M054.1', 'M054')]), '同一の定義');
    }
}
