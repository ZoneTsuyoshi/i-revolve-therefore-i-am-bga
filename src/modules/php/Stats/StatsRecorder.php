<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Stats;

use Bga\Games\IRevolveThereforeIAm\Flow\Notice;

/**
 * 統計の記録（仕様書 007 §14、`src/stats.jsonc`）。通知（{@see Notice}）を送る場所（`Game::irSend`）で 1 件ずつ見て加算するので、
 * 通知を作る側は統計を意識しない。ターンの数え上げと終局の確定は状態クラスが呼ぶ。
 */
final class StatsRecorder
{
    /** `end_reason` の値（0 は終了条件が成立しなかった場合）。`stats.jsonc` の `value_labels` と同じ並び。 */
    private const END_REASONS = ['FRAGMENTS' => 1, 'INSTANT_MEMORY' => 2, 'APEX_OF_PSYCHE' => 3, 'DECK_OUT' => 4];

    /** @var list<string> */
    public const TABLE_STATS = ['turns_played', 'end_reason', 'max_rooms_in_turn'];

    /** @var list<string> */
    public const PLAYER_STATS = [
        'final_score', 'memory_score', 'objective_score', 'final_tier', 'turns_taken', 'rooms_entered', 'rooms_skipped',
        'cards_drawn', 'cards_composed', 'analysis_done', 'light_gained', 'light_spent', 'cards_discarded_for_light',
        'cards_discarded_limit', 'wild_tokens_used', 'compose_tokens_used', 'analysis_tokens_used', 'goals_claimed',
        'key_wisdom', 'key_will', 'key_emotion', 'key_wild', 'recall_effects', 'activated_effects',
    ];

    public function __construct(private readonly StatStore $store)
    {
    }

    /**
     * 全ての統計を 0 にする（`setupNewGame`）。
     */
    public function initialize(): void
    {
        foreach (self::TABLE_STATS as $name) {
            $this->store->initTable($name, 0);
        }
        foreach (self::PLAYER_STATS as $name) {
            $this->store->initPlayer($name, 0);
        }
    }

    /**
     * 送る通知から加算する。統計に関係しない通知は何もしない。
     */
    public function record(Notice $notice): void
    {
        $args = $notice->args;
        $player = isset($args['player_id']) && is_int($args['player_id']) ? $args['player_id'] : null;
        if ($player === null) {
            return;
        }
        switch ($notice->type) {
            case 'roomEntered':
                $this->inc('rooms_entered', 1, $player);
                break;
            case 'personaMoved':
                if (($args['counts_as_entry'] ?? false) === true) {
                    $this->inc('rooms_entered', 1, $player);
                }
                break;
            case 'roomSkipped':
                $this->inc('rooms_skipped', 1, $player);
                break;
            case 'cardsDrawn':
                if (($args['reason'] ?? '') !== 'SETUP') {
                    $this->inc('cards_drawn', $this->int($args, 'count'), $player);
                }
                break;
            case 'cardComposed':
                $this->inc('cards_composed', 1, $player);
                break;
            case 'spiritAdvanced':
                if (($args['via'] ?? '') === 'ACTION') {
                    $this->inc('analysis_done', 1, $player);
                }
                break;
            case 'objectiveClaimed':
                $this->inc('goals_claimed', 1, $player);
                break;
            case 'effectActivated':
                $this->recordEffect($args, $player);
                break;
            case 'cardsDiscarded':
                $this->recordDiscard($args, $player);
                break;
            case 'tokensChanged':
                $this->recordTokens($args, $player);
                break;
        }
    }

    /**
     * ターンの開始（30）。`turns_played` は始まったターンの数（終局の途中のターンも数える）。
     */
    public function turnStarted(int $playerId): void
    {
        $this->store->incTable('turns_played', 1);
        $this->inc('turns_taken', 1, $playerId);
    }

    /**
     * ターンの終わり（82、または終局）。そのターンの入室数（想起による移動を含む）で最大値を更新する。
     */
    public function turnFinished(int $roomsEntered): void
    {
        if ($roomsEntered > $this->store->getTable('max_rooms_in_turn')) {
            $this->store->setTable('max_rooms_in_turn', $roomsEntered);
        }
    }

    /**
     * 終局（95）で確定する値。
     *
     * @param list<array<string, int>> $results {@see \Bga\Games\IRevolveThereforeIAm\Data\PublicView::results()} の行
     * @param array<int, array<string, int>> $keys プレイヤー ID → 鍵の種類（`WISDOM` / `WILL` / `EMOTION` / `WILD`）ごとの自己構成済みの本数
     */
    public function finalize(?string $reason, array $results, array $keys): void
    {
        $this->store->setTable('end_reason', self::END_REASONS[$reason ?? ''] ?? 0);
        foreach ($results as $row) {
            $player = $row['player_id'];
            $this->store->setPlayer('final_score', $row['total'], $player);
            $this->store->setPlayer('memory_score', $row['memory_score'] + $row['attached_score'], $player);
            $this->store->setPlayer('objective_score', $row['objective_score'], $player);
            $this->store->setPlayer('final_tier', $row['tier'], $player);
            $held = $keys[$player] ?? [];
            $this->store->setPlayer('key_wisdom', $held['WISDOM'] ?? 0, $player);
            $this->store->setPlayer('key_will', $held['WILL'] ?? 0, $player);
            $this->store->setPlayer('key_emotion', $held['EMOTION'] ?? 0, $player);
            $this->store->setPlayer('key_wild', $held['WILD'] ?? 0, $player);
        }
    }

    /**
     * プレイヤーの入室数の累計（N31 `gameEnded` の `rooms_entered`。統計と同じ値）。
     */
    public function roomsEntered(int $playerId): int
    {
        return $this->store->getPlayer('rooms_entered', $playerId);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function recordEffect(array $args, int $player): void
    {
        $recall = is_string($args['spec_id'] ?? null) && str_starts_with($args['spec_id'], 'RC-');
        $this->inc($recall ? 'recall_effects' : 'activated_effects', 1, $player);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function recordDiscard(array $args, int $player): void
    {
        $count = $this->int($args, 'count');
        $reason = $args['reason'] ?? '';
        if ($reason === 'LIGHT') {
            $this->inc('cards_discarded_for_light', $count, $player);
        } elseif ($reason === 'HAND_LIMIT') {
            $this->inc('cards_discarded_limit', $count, $player);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    private function recordTokens(array $args, int $player): void
    {
        $delta = is_array($args['delta'] ?? null) ? $args['delta'] : [];
        $light = $this->int($delta, 'light');
        if ($light > 0) {
            $this->inc('light_gained', $light, $player);
        } elseif ($light < 0) {
            $this->inc('light_spent', -$light, $player);
        }
        // 万能移動鍵は表 → 裏（`wild_used` が増える）ときだけ使用として数える。クリーンアップで表に戻るのは数えない
        $this->inc('wild_tokens_used', max(0, $this->int($delta, 'wild_used')), $player);
        $this->inc('compose_tokens_used', max(0, -$this->int($delta, 'compose')), $player);
        $this->inc('analysis_tokens_used', max(0, -$this->int($delta, 'analysis')), $player);
    }

    private function inc(string $name, int $delta, int $playerId): void
    {
        if ($delta !== 0) {
            $this->store->incPlayer($name, $delta, $playerId);
        }
    }

    /**
     * @param array<mixed> $args
     */
    private function int(array $args, string $key): int
    {
        $value = $args[$key] ?? 0;

        return is_int($value) ? $value : 0;
    }
}
