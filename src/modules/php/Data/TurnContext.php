<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

/**
 * ターン内の入れ物（グローバル値 turn に JSON で保存。仕様書 007 §7.3）。ターンごとに TurnStart が新規に作り、
 * NextTurn が破棄する。ここに無いキーは読み書きしない（fromArray は未知のキーを捨てる）。
 */
final class TurnContext
{
    /** ターン開始時の人格の位置。 */
    public int $startPos = 0;

    /** @var list<int> このターンに入室した部屋の位置（重複なし。入室順） */
    public array $enteredRooms = [];

    /** 入室の回数（同じ部屋への再入室も別に数える）。 */
    public int $roomsEnteredCount = 0;

    /** レベル 2 の部屋への入室回数。 */
    public int $lv2EntriesCount = 0;

    /** @var list<int> このターンにスキップしたレベル 2 の部屋の位置 */
    public array $skippedRooms = [];

    /** このターンの移動で進んだ位置の数。 */
    public int $moveSteps = 0;

    /**
     * 移動フェイズの支払いの要約: key_kinds_paid（list<string>）、wild_tokens_used（int）、light_spent（int）。
     *
     * @var array{key_kinds_paid: list<string>, wild_tokens_used: int, light_spent: int}
     */
    public array $moveSummary = ['key_kinds_paid' => [], 'wild_tokens_used' => 0, 'light_spent' => 0];

    public int $lightsGained = 0;

    public int $analysisActionsDone = 0;

    public int $composeActionsDone = 0;

    public bool $phaseComposeUsed = false;

    /** このターンに獲得した目標カードの obj_id（1 ターン 1 枚）。 */
    public ?int $objectiveClaimed = null;

    public int $turnComposeDiscount = 0;

    public int $nextComposeDiscount = 0;

    /** @var list<array<string, mixed>> 想起で得た床アクションの代替 {kind, discount, source} */
    public array $floorAlternatives = [];

    /**
     * 想起フェイズの情報: room_pos、card_id、activators（list<int>）、counts（プレイヤー ID => 発動回数）。
     *
     * @var array<string, mixed>|null
     */
    public ?array $recall = null;

    /** @var list<string> ONCE_PER_TURN の使用済みの印（"<player_id>:<spec_id>"） */
    public array $onceUsed = [];

    /** @var list<array<string, mixed>> 「このターン」限りの一時効果 {key, owner, source, params} */
    public array $tempEffects = [];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $c = new self();
        $c->startPos = (int) ($data['start_pos'] ?? 0);
        $c->enteredRooms = self::ints($data['entered_rooms'] ?? []);
        $c->roomsEnteredCount = (int) ($data['rooms_entered_count'] ?? 0);
        $c->lv2EntriesCount = (int) ($data['lv2_entries_count'] ?? 0);
        $c->skippedRooms = self::ints($data['skipped_rooms'] ?? []);
        $c->moveSteps = (int) ($data['move_steps'] ?? 0);
        $summary = is_array($data['move_summary'] ?? null) ? $data['move_summary'] : [];
        $c->moveSummary = [
            'key_kinds_paid' => array_values(array_map('strval', is_array($summary['key_kinds_paid'] ?? null) ? $summary['key_kinds_paid'] : [])),
            'wild_tokens_used' => (int) ($summary['wild_tokens_used'] ?? 0),
            'light_spent' => (int) ($summary['light_spent'] ?? 0),
        ];
        $c->lightsGained = (int) ($data['lights_gained'] ?? 0);
        $c->analysisActionsDone = (int) ($data['analysis_actions_done'] ?? 0);
        $c->composeActionsDone = (int) ($data['compose_actions_done'] ?? 0);
        $c->phaseComposeUsed = (bool) ($data['phase_compose_used'] ?? false);
        $c->objectiveClaimed = isset($data['objective_claimed']) ? (int) $data['objective_claimed'] : null;
        $c->turnComposeDiscount = (int) ($data['turn_compose_discount'] ?? 0);
        $c->nextComposeDiscount = (int) ($data['next_compose_discount'] ?? 0);
        $c->floorAlternatives = is_array($data['floor_alternatives'] ?? null) ? array_values($data['floor_alternatives']) : [];
        $c->recall = is_array($data['recall'] ?? null) ? $data['recall'] : null;
        $c->onceUsed = array_values(array_map('strval', is_array($data['once_used'] ?? null) ? $data['once_used'] : []));
        $c->tempEffects = is_array($data['temp_effects'] ?? null) ? array_values($data['temp_effects']) : [];
        return $c;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'start_pos' => $this->startPos,
            'entered_rooms' => $this->enteredRooms,
            'rooms_entered_count' => $this->roomsEnteredCount,
            'lv2_entries_count' => $this->lv2EntriesCount,
            'skipped_rooms' => $this->skippedRooms,
            'move_steps' => $this->moveSteps,
            'move_summary' => $this->moveSummary,
            'lights_gained' => $this->lightsGained,
            'analysis_actions_done' => $this->analysisActionsDone,
            'compose_actions_done' => $this->composeActionsDone,
            'phase_compose_used' => $this->phaseComposeUsed,
            'objective_claimed' => $this->objectiveClaimed,
            'turn_compose_discount' => $this->turnComposeDiscount,
            'next_compose_discount' => $this->nextComposeDiscount,
            'floor_alternatives' => $this->floorAlternatives,
            'recall' => $this->recall,
            'once_used' => $this->onceUsed,
            'temp_effects' => $this->tempEffects,
        ];
    }

    /**
     * 「持ち主 + 効果」の ONCE_PER_TURN キー。
     */
    public static function onceKey(int $playerId, string $specId): string
    {
        return "$playerId:$specId";
    }

    public function isOnceUsed(int $playerId, string $specId): bool
    {
        return in_array(self::onceKey($playerId, $specId), $this->onceUsed, true);
    }

    public function markOnceUsed(int $playerId, string $specId): void
    {
        $key = self::onceKey($playerId, $specId);
        if (!in_array($key, $this->onceUsed, true)) {
            $this->onceUsed[] = $key;
        }
    }

    /**
     * 入室を記録する（重複なしの入室順と回数）。
     */
    public function recordEntry(int $pos, bool $level2): void
    {
        if (!in_array($pos, $this->enteredRooms, true)) {
            $this->enteredRooms[] = $pos;
        }
        $this->roomsEnteredCount++;
        if ($level2) {
            $this->lv2EntriesCount++;
        }
    }

    /**
     * @return list<int>
     */
    private static function ints(mixed $value): array
    {
        return is_array($value) ? array_values(array_map('intval', $value)) : [];
    }
}
