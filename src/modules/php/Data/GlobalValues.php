<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

use Bga\GameFramework\Db\Globals;

/**
 * ゲーム全体の値（$this->bga->globals。仕様書 007 §7.2）のアクセサ。キー名と初期値は 007 §7.2 の表に合わせる。
 * 値が未設定のときは初期値を返す。
 */
final class GlobalValues
{
    public function __construct(private readonly Globals $globals)
    {
    }

    /**
     * 007 §7.2 の初期値を書く（setupNewGame 用）。deck1_initial・deck2_initial はセットアップが決めて別に書く。
     */
    public function initialize(): void
    {
        $this->setTurnPlayer(0);
        $this->setTurnNo(0);
        $this->setPhase(GamePhase::SetupEgo);
        $this->setResume(0);
        $this->setSetupStep(0);
        $this->setTurnContext(new TurnContext());
        $this->setEndReason(null);
        $this->setWinnerId(null);
        $this->setDeckOut([]);
        $this->setProgressHighWater(0);
    }

    public function turnPlayer(): int
    {
        return (int) $this->globals->get('turn_player', 0);
    }

    public function setTurnPlayer(int $playerId): void
    {
        $this->globals->set('turn_player', $playerId);
    }

    public function turnNo(): int
    {
        return (int) $this->globals->get('turn_no', 0);
    }

    public function setTurnNo(int $value): void
    {
        $this->globals->set('turn_no', $value);
    }

    public function phase(): GamePhase
    {
        return GamePhase::tryFrom((string) $this->globals->get('phase', '')) ?? GamePhase::SetupEgo;
    }

    public function setPhase(GamePhase $phase): void
    {
        $this->globals->set('phase', $phase->value);
    }

    /**
     * 効果解決スタックが空になったときの戻り先の状態 ID（0 = 未設定）。
     */
    public function resume(): int
    {
        return (int) $this->globals->get('resume', 0);
    }

    public function setResume(int $stateId): void
    {
        $this->globals->set('resume', $stateId);
    }

    public function setupStep(): int
    {
        return (int) $this->globals->get('setup_step', 0);
    }

    public function setSetupStep(int $value): void
    {
        $this->globals->set('setup_step', $value);
    }

    public function turnContext(): TurnContext
    {
        $raw = $this->globals->get('turn', []);
        return TurnContext::fromArray(is_array($raw) ? $raw : []);
    }

    public function setTurnContext(TurnContext $context): void
    {
        $this->globals->set('turn', $context->toArray());
    }

    /**
     * ターン終了で TurnContext を破棄する（空に戻す）。
     */
    public function resetTurnContext(): void
    {
        $this->setTurnContext(new TurnContext());
    }

    /**
     * FRAGMENTS / INSTANT_MEMORY / APEX_OF_PSYCHE / DECK_OUT。未確定は null。
     */
    public function endReason(): ?string
    {
        $v = $this->globals->get('end_reason', null);
        return $v === null ? null : (string) $v;
    }

    public function setEndReason(?string $reason): void
    {
        $this->globals->set('end_reason', $reason);
    }

    /**
     * 勝者のプレイヤー ID。全員敗北なら null。
     */
    public function winnerId(): ?int
    {
        $v = $this->globals->get('winner_id', null);
        return $v === null ? null : (int) $v;
    }

    public function setWinnerId(?int $playerId): void
    {
        $this->globals->set('winner_id', $playerId);
    }

    /**
     * 山札切れが成立したレベル（1 または 2）。
     *
     * @return list<int>
     */
    public function deckOut(): array
    {
        $v = $this->globals->get('deck_out', []);
        return is_array($v) ? array_values(array_map('intval', $v)) : [];
    }

    /**
     * @param list<int> $levels
     */
    public function setDeckOut(array $levels): void
    {
        $this->globals->set('deck_out', array_values($levels));
    }

    /**
     * セットアップ終了時の山札の枚数（レベル 1 または 2）。進行度の式の分母。
     */
    public function deckInitial(int $level): int
    {
        return (int) $this->globals->get($this->deckInitialKey($level), 0);
    }

    public function setDeckInitial(int $level, int $count): void
    {
        $this->globals->set($this->deckInitialKey($level), $count);
    }

    public function progressHighWater(): int
    {
        return (int) $this->globals->get('progress_high_water', 0);
    }

    public function setProgressHighWater(int $value): void
    {
        $this->globals->set('progress_high_water', $value);
    }

    private function deckInitialKey(int $level): string
    {
        if ($level !== 1 && $level !== 2) {
            throw new \InvalidArgumentException("invalid level: $level");
        }
        return "deck{$level}_initial";
    }
}
