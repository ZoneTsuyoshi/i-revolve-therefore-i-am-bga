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
 * 61: 自己構成アクションの実行。コンテキスト（フェイズ・床・トークン・ボーナス・代替）は DB の frame が持つ（006 §5.5）。
 * 検証と確定は Flow/ComposeAction（支払いの検証は Flow/ComposePlanner）。
 */
class Compose extends GameState
{
    use FreeActions;

    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: StateId::COMPOSE,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must compose a memory card'),
            descriptionMyTurn: clienttranslate('${you} must compose a memory card'),
            transitions: [
                Transition::DONE => StateId::EFFECT_RESOLVE,
                Transition::GAME_END => StateId::GAME_END,
            ],
        );
    }

    /**
     * 公開部分は文脈（`context`: PHASE / TOKEN / FLOOR / BONUS_LV1 / BONUS_ANY / EFFECT_FREE_LV1）、割引、心光の逓増、
     * `tapCandidates`（M095 で横向きにできる自己構成済みカードの ID）、`extraMax`（I08 の追加支払いの上限）も公開。
     * 手札の必要エネルギーは `_private[本人]`（`hand`: id / type / level / need / composable）と、
     * `architectPairs`（I03 で入れ替えられる `[自己構成済みの ID, 手札の ID]` の組）。
     *
     * @return array<string, mixed>
     */
    public function getArgs(int $activePlayerId): array
    {
        $args = $this->game->irTurn()->compose->args($activePlayerId);

        return [
            'turnPlayer' => $this->game->irGlobals()->turnPlayer(),
            ...$args['public'],
            '_private' => [$activePlayerId => $args['private']],
        ];
    }

    /**
     * 自己構成する。`payment`: `{"discard": [手札のカード ID...], "light": 心光の行使の回数, "tap": [自己構成済みのカード ID...]}`
     * （エネルギーを払わない文脈では空）。
     *
     * @param array<string, mixed> $payment エネルギーの支払い（手札を捨てる、心光の行使など）
     */
    #[PossibleAction]
    public function actCompose(int $cardId, #[JsonParam(alphanum: false)] array $payment, int $activePlayerId): string
    {
        try {
            $notices = $this->game->irTurn()->compose->compose($activePlayerId, $cardId, $payment);
        } catch (EffectViolation $e) {
            throw new UserException($e->getMessage());
        }
        $this->game->irSend(...$notices);

        return $this->irNext();
    }

    /**
     * I03（建築家）: 自己構成する代わりに、自己構成済みのカードと手札の新しいカードを入れ替える。
     */
    #[PossibleAction]
    public function actComposeArchitect(int $tableauCardId, int $newCardId, int $activePlayerId): string
    {
        try {
            $notices = $this->game->irTurn()->compose->architect($activePlayerId, $tableauCardId, $newCardId);
        } catch (EffectViolation $e) {
            throw new UserException($e->getMessage());
        }
        $this->game->irSend(...$notices);

        return $this->irNext();
    }

    /**
     * 自己構成を行わずに戻る（何も消費しない）。床アクションの代替なら 50 に戻って別の選択肢を選べる。
     */
    #[PossibleAction]
    public function actCancel(int $activePlayerId): string
    {
        try {
            $notices = $this->game->irTurn()->compose->cancel($activePlayerId);
        } catch (EffectViolation $e) {
            throw new UserException($e->getMessage());
        }
        $this->game->irSend(...$notices);

        return Transition::DONE;
    }

    /**
     * 行わない（006 §10）。
     */
    public function zombie(int $playerId): string
    {
        return $this->actCancel($playerId);
    }

    private function irNext(): string
    {
        return $this->game->irGlobals()->endReason() !== null ? Transition::GAME_END : Transition::DONE;
    }
}
