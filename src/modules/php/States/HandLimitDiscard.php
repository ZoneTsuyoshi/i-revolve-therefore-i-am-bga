<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\States;

use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Flow\FreeActions;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 72: 手札上限（8 枚）を超えたプレイヤーが、超過分を捨てる（006 §8）。骨格では 70 が入れないので入らない。
 */
class HandLimitDiscard extends GameState
{
    use FreeActions;

    /** 手札の上限（001 §12）。 */
    public const HAND_LIMIT = 8;

    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::HAND_LIMIT_DISCARD,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must discard down to the hand limit'),
            descriptionMyTurn: clienttranslate('${you} must discard down to the hand limit'),
            transitions: [
                Transition::DISCARDED => StateId::EFFECT_RESOLVE,
                Transition::GAME_END => StateId::GAME_END,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getArgs(int $activePlayerId): array
    {
        $count = $this->game->irMemoryCards()->count(MemoryLocation::Hand, $activePlayerId);

        return [
            'turnPlayer' => $this->game->irGlobals()->turnPlayer(),
            'targetPlayerId' => $activePlayerId,
            'limit' => self::HAND_LIMIT,
            'excess' => max(0, $count - self::HAND_LIMIT),
        ];
    }

    /**
     * 超過分ちょうどを捨てる。
     *
     * @param list<int> $cardIds
     */
    #[PossibleAction]
    public function actDiscardExcess(#[IntArrayParam] array $cardIds, int $activePlayerId): string
    {
        // TODO(I-07): 枚数が超過分ちょうどで、すべて本人の手札であることを検査し、各レベルの捨札へ移す。N15 cardsDiscarded を送る
        return Transition::DISCARDED;
    }

    /**
     * 超過枚数分、レベル 1 を優先して手札の後ろから捨てる（006 §10）。
     */
    public function zombie(int $playerId): string
    {
        // TODO(I-07): 捨てるカードを決めて actDiscardExcess と同じ処理を行う
        return $this->actDiscardExcess([], $playerId);
    }
}
