<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops;

use Bga\Games\IRevolveThereforeIAm\Effects\Engine\UnsupportedOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Action\ActionOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board\AcquireRoomCard;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board\CollectUnderFloor;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board\MoveNextRoom;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board\RecallActivate;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board\SwapHandRoom;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board\SwapRoomFloor;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board\SwapRoomMemory;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board\Warp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain\ClaimSpiritLight;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain\DrawDeck;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain\GainLight;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain\GainOpen;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain\GainToken;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Hand\DiscardForLight;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Hand\DiscardHand;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Hand\SwapComposedHand;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Hand\SwapHandOpen;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Spirit\AdvanceTier;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Spirit\GainEnhancedEgo;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Spirit\Win;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\State\AddNextComposeDiscount;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\State\RecordFloorAlternative;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\State\SetTurnComposeDiscount;
use Bga\Games\IRevolveThereforeIAm\Material\Op;

/**
 * 原子操作 → ハンドラ。実装済み: OP-01〜OP-19、OP-21〜OP-26（008 §11.2 の順 3・4・6）。
 * OP-20 `PLACE_UNDER_FLOOR` は、精神分析の支払いを適用する 62（`AnalysisAction`）が MD-10（I04.1）で直接行う
 * （払ったカードの ID は効果の frame に載らない）ので、ハンドラを持たない。
 */
final class OpRegistry
{
    /** @var array<string, OpHandler> */
    private array $handlers = [];

    public function __construct()
    {
        $this->register(Op::DRAW_DECK, new DrawDeck());
        $this->register(Op::GAIN_OPEN, new GainOpen());
        $this->register(Op::GAIN_LIGHT, new GainLight());
        $this->register(Op::GAIN_TOKEN, new GainToken());
        $this->register(Op::CLAIM_SPIRIT_LIGHT, new ClaimSpiritLight());
        $this->register(Op::DISCARD_HAND, new DiscardHand());
        $this->register(Op::DISCARD_FOR_LIGHT, new DiscardForLight());
        $this->register(Op::SWAP_HAND_OPEN, new SwapHandOpen());
        $this->register(Op::SWAP_COMPOSED_HAND, new SwapComposedHand());
        $this->register(Op::COMPOSE, new ActionOp(Op::COMPOSE));
        $this->register(Op::ANALYZE, new ActionOp(Op::ANALYZE));
        $this->register(Op::ADVANCE_TIER, new AdvanceTier());
        $this->register(Op::GAIN_ENHANCED_EGO, new GainEnhancedEgo());
        $this->register(Op::WIN, new Win());
        $this->register(Op::MOVE_NEXT_ROOM, new MoveNextRoom());
        $this->register(Op::WARP, new Warp());
        $this->register(Op::SWAP_ROOM_MEMORY, new SwapRoomMemory());
        $this->register(Op::SWAP_ROOM_FLOOR, new SwapRoomFloor());
        $this->register(Op::SWAP_HAND_ROOM, new SwapHandRoom());
        $this->register(Op::COLLECT_UNDER_FLOOR, new CollectUnderFloor());
        $this->register(Op::RECALL_ACTIVATE, new RecallActivate());
        $this->register(Op::SET_TURN_COMPOSE_DISCOUNT, new SetTurnComposeDiscount());
        $this->register(Op::ADD_NEXT_COMPOSE_DISCOUNT, new AddNextComposeDiscount());
        $this->register(Op::RECORD_FLOOR_ALTERNATIVE, new RecordFloorAlternative());
        $this->register(Op::ACQUIRE_ROOM_CARD, new AcquireRoomCard());
    }

    public function register(Op $op, OpHandler $handler): void
    {
        $this->handlers[$op->value] = $handler;
    }

    public function has(Op $op): bool
    {
        return isset($this->handlers[$op->value]);
    }

    /**
     * @throws UnsupportedOp ハンドラが未実装の操作
     */
    public function get(Op $op): OpHandler
    {
        return $this->handlers[$op->value] ?? throw UnsupportedOp::forKind($op);
    }
}
