<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\States;

use Bga\GameFramework\Actions\Types\JsonParam;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\IRevolveThereforeIAm\Data\EffectFrame;
use Bga\Games\IRevolveThereforeIAm\Flow\FreeActions;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 71: 汎用の選択（006 §7.4）。選択者は保留中の frame が持つ。骨格では 70 が入れないので入らない。
 */
class EffectChoice extends GameState
{
    use FreeActions;

    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::EFFECT_CHOICE,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must make a choice'),
            descriptionMyTurn: clienttranslate('${you} must make a choice'),
            transitions: [
                Transition::ANSWERED => StateId::EFFECT_RESOLVE,
                Transition::GAME_END => StateId::GAME_END,
            ],
        );
    }

    /**
     * 公開部分は選択の種類・選択者・出典・公開できる候補。非公開の候補は _private[選択者]（強化自我の山の全カードなど）。
     *
     * @return array<string, mixed>
     */
    public function getArgs(int $activePlayerId): array
    {
        $head = $this->game->irEffectStack()->peek();
        $frame = $head !== null && $head->status === EffectFrame::WAITING ? $head : null;

        return [
            'turnPlayer' => $this->game->irGlobals()->turnPlayer(),
            'chooser' => $frame === null ? $activePlayerId : $frame->owner,
            'source' => $frame?->source,
            'choice' => $frame?->choice, // TODO(I-07/I-09): choice_kind、公開できる候補、skippable
            '_private' => [$activePlayerId => []], // TODO(I-09): 本人にだけ見せる候補（PICK_ENHANCED_EGO など）
        ];
    }

    /**
     * @param array<string, mixed> $payload 選択の種類ごとの回答
     */
    #[PossibleAction]
    public function actResolveChoice(#[JsonParam(alphanum: false)] array $payload, int $activePlayerId): string
    {
        // TODO(I-07/I-09): 保留中の frame に回答を適用して進める。勝利判定を行う
        return Transition::ANSWERED;
    }

    /**
     * スキップが許される種類の選択をスキップする。
     */
    #[PossibleAction]
    public function actSkipChoice(int $activePlayerId): string
    {
        // TODO(I-07/I-09): スキップが許されない種類（必須の捨て札など）は受けない
        return Transition::ANSWERED;
    }

    /**
     * スキップ可の種類はスキップ、必須の選択は種類ごとの既定（006 §10）。
     */
    public function zombie(int $playerId): string
    {
        return $this->actSkipChoice($playerId);
    }
}
