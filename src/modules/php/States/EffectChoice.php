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
use Bga\GameFramework\UserException;
use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Flow\FreeActions;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\Transition;
use Bga\Games\IRevolveThereforeIAm\Game;

/**
 * 71: 汎用の選択（006 §7.4、008 §6.4）。選択者は保留中の frame が持つ。中身は Flow/ChoiceFlow。
 * 想起の発動者が順番に答える（ターンプレイヤー以外の発動者は 70 が changeActivePlayer して入る。フリーアクションは使えない。006 §6.2）。
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
     * 公開部分は選択の種類（`choiceKind`。床アクションの分岐は null）・選択者・出典・公開できる候補（`data`）・`skippable`。
     * 非公開の候補は `_private[選択者]`（強化自我の山の全カード `egoCandidates`、交換できる組 `pairs`）。
     *
     * @return array<string, mixed>
     */
    public function getArgs(int $activePlayerId): array
    {
        $args = $this->game->irTurn()->choices->args();

        return [
            'turnPlayer' => $this->game->irGlobals()->turnPlayer(),
            ...$args['public'],
            '_private' => [$activePlayerId => $args['private']],
        ];
    }

    /**
     * 選択に答える。`payload` は種類ごと:
     * `PICK_FIELD_CARD` `{"cards": [場札のカード ID]}`、`PICK_DISCARD` `{"cards": [手札のカード ID]}`、
     * `SELECT_DISCARD_FOR_LIGHT` `{"cards": [...]}`、`SELECT_SWAP_HAND_FIELD` `{"hand": [...], "field": [...]}`、
     * `SELECT_SWAP_SAME_COST` `{"tableau": ID, "hand": ID}`、`CONFIRM_USE` / `CONFIRM_MOVE` `{"use": true|false}`、
     * `PICK_ENHANCED_EGO` `{"ego": "E03"}`、`PICK_SPIRIT_BRANCH` `{"spirit": "R3B"}`、`ORDER_EFFECTS` `{"index": n}`、
     * 分岐 `{"branch": n}`。
     *
     * @param array<string, mixed> $payload 選択の種類ごとの回答
     */
    #[PossibleAction]
    public function actResolveChoice(#[JsonParam(alphanum: false)] array $payload, int $activePlayerId): string
    {
        try {
            $notices = $this->game->irTurn()->choices->answer($activePlayerId, $payload);
        } catch (EffectViolation $e) {
            throw new UserException($e->getMessage());
        }
        $this->game->irSend(...$notices);

        return $this->irNext();
    }

    /**
     * スキップが許される種類の選択をスキップする。必須の選択（場札の獲得、捨て札、強化自我など）は受けない。
     */
    #[PossibleAction]
    public function actSkipChoice(int $activePlayerId): string
    {
        try {
            $notices = $this->game->irTurn()->choices->skip($activePlayerId);
        } catch (EffectViolation $e) {
            throw new UserException($e->getMessage());
        }
        $this->game->irSend(...$notices);

        return $this->irNext();
    }

    /**
     * スキップ可の種類はスキップ、必須の選択は種類ごとの既定（006 §10、008 §6.4）。
     */
    public function zombie(int $playerId): string
    {
        $this->game->irSend(...$this->game->irTurn()->choices->auto($playerId));

        return $this->irNext();
    }

    private function irNext(): string
    {
        return $this->game->irGlobals()->endReason() !== null ? Transition::GAME_END : Transition::ANSWERED;
    }
}
