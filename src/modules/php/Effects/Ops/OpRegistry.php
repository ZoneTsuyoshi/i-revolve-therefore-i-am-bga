<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops;

use Bga\Games\IRevolveThereforeIAm\Effects\Engine\UnsupportedOp;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Board\AcquireRoomCard;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain\ClaimSpiritLight;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain\DrawDeck;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain\GainLight;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain\GainOpen;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Gain\GainToken;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Hand\DiscardForLight;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Hand\DiscardHand;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Hand\SwapComposedHand;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\Hand\SwapHandOpen;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\State\AddNextComposeDiscount;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\State\RecordFloorAlternative;
use Bga\Games\IRevolveThereforeIAm\Effects\Ops\State\SetTurnComposeDiscount;
use Bga\Games\IRevolveThereforeIAm\Material\Op;

/**
 * 原子操作 → ハンドラ。実装済み: OP-01〜OP-09、OP-22〜OP-24、OP-26（008 §11.2 の順 3）。
 * 残り（OP-10〜OP-21、OP-25）は後続のタスクで足す。
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
