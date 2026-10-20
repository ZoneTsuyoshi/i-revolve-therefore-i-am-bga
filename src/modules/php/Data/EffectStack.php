<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

/**
 * effect_frame テーブルの積み方・取り出し・保留（仕様書 007 §6）。実行順は frame_order の昇順（小さいほうが先）。
 */
final class EffectStack
{
    /** 暴走の検出（仕様書 007 §6 の安全弁）。 */
    public const MAX_FRAMES = 200;

    private const COLUMNS = '`frame_id`, `frame_order`, `frame_parent`, `frame_kind`, `frame_owner`, `frame_source`, `frame_spec`,'
        . ' `frame_op`, `frame_step`, `frame_group`, `frame_origin`, `frame_unit`, `frame_cancel_to`, `frame_status`,'
        . ' `frame_params`, `frame_payload`, `frame_choice`';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * 実行順の先頭に挿入する。与えた順に先頭から実行される（現在の最小 order を m、個数を n として i 番目に m - n + i）。
     *
     * @param list<EffectFrame> $frames
     */
    public function pushFront(array $frames): void
    {
        $n = count($frames);
        if ($n === 0) {
            return;
        }
        $min = $this->db->value('SELECT MIN(`frame_order`) FROM `effect_frame`');
        $m = ($min === null || $min === false) ? 0 : (int) $min;
        $start = ($min === null || $min === false) ? 0 : $m - $n;
        foreach ($frames as $i => $frame) {
            $this->insert($frame, $start + $i);
        }
        $this->assertNotRunaway();
    }

    /**
     * スタックの底に積む（ターン開始時などの初期投入だけ）。
     *
     * @param list<EffectFrame> $frames
     */
    public function pushBack(array $frames): void
    {
        $max = $this->db->value('SELECT MAX(`frame_order`) FROM `effect_frame`');
        $next = ($max === null || $max === false) ? 0 : (int) $max + 1;
        foreach ($frames as $i => $frame) {
            $this->insert($frame, $next + $i);
        }
        $this->assertNotRunaway();
    }

    /**
     * 実行順が先頭の frame（空なら null）。
     */
    public function peek(): ?EffectFrame
    {
        $rows = $this->db->rows('SELECT ' . self::COLUMNS . ' FROM `effect_frame` ORDER BY `frame_order` ASC LIMIT 1');
        return $rows === [] ? null : EffectFrame::fromRow($rows[0]);
    }

    /**
     * @return list<EffectFrame> 実行順
     */
    public function all(): array
    {
        $rows = $this->db->rows('SELECT ' . self::COLUMNS . ' FROM `effect_frame` ORDER BY `frame_order` ASC');
        return array_map(EffectFrame::fromRow(...), $rows);
    }

    public function count(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM `effect_frame`');
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * frame を完了する（行の削除）。
     */
    public function complete(int $frameId): void
    {
        $this->db->execute("DELETE FROM `effect_frame` WHERE `frame_id` = $frameId");
    }

    /**
     * frame を、その位置に新しい列で置き換える（親が子に展開するとき）。
     *
     * @param list<EffectFrame> $frames
     */
    public function replace(int $frameId, array $frames): void
    {
        $order = $this->db->value("SELECT `frame_order` FROM `effect_frame` WHERE `frame_id` = $frameId");
        if ($order === null || $order === false) {
            throw new \InvalidArgumentException("unknown frame: $frameId");
        }
        $order = (int) $order;
        $n = count($frames);
        // 置き換える frame より後ろを n - 1 個ぶん後ろへずらし、同じ位置に n 個入れる。
        $this->db->execute("DELETE FROM `effect_frame` WHERE `frame_id` = $frameId");
        if ($n > 1) {
            $this->db->execute('UPDATE `effect_frame` SET `frame_order` = `frame_order` + ' . ($n - 1) . " WHERE `frame_order` > $order");
        }
        foreach ($frames as $i => $frame) {
            $this->insert($frame, $order + $i);
        }
        $this->assertNotRunaway();
    }

    /**
     * 保留中（プレイヤーの入力待ち）にする。WAITING にできるのは実行順が先頭の frame だけ。
     *
     * @param array<string, mixed>|null $choice 選択の定義（公開できる候補だけ）
     */
    public function markWaiting(int $frameId, ?array $choice = null): void
    {
        $head = $this->peek();
        if ($head === null || $head->id !== $frameId) {
            throw new \LogicException("frame $frameId is not at the head of the stack");
        }
        $this->db->execute(
            "UPDATE `effect_frame` SET `frame_status` = 'WAITING', `frame_choice` = " . $this->json($choice)
            . " WHERE `frame_id` = $frameId",
        );
    }

    /**
     * 保留を解く（READY に戻し、選択の定義を消す）。
     */
    public function markReady(int $frameId): void
    {
        $this->db->execute(
            "UPDATE `effect_frame` SET `frame_status` = 'READY', `frame_choice` = NULL WHERE `frame_id` = $frameId",
        );
    }

    /**
     * 全削除（終局時）。
     */
    public function clear(): void
    {
        $this->db->execute('DELETE FROM `effect_frame`');
    }

    private function insert(EffectFrame $f, int $order): void
    {
        $null = static fn(?string $v): string => $v === null ? 'NULL' : "'" . $v . "'";
        $nullInt = static fn(?int $v): string => $v === null ? 'NULL' : (string) $v;
        $e = $this->db->escape(...);
        $this->db->execute(
            'INSERT INTO `effect_frame` (`frame_order`, `frame_parent`, `frame_kind`, `frame_owner`, `frame_source`,'
            . ' `frame_spec`, `frame_op`, `frame_step`, `frame_group`, `frame_origin`, `frame_unit`, `frame_cancel_to`,'
            . ' `frame_status`, `frame_params`, `frame_payload`, `frame_choice`) VALUES ('
            . implode(', ', [
                (string) $order,
                $nullInt($f->parent),
                "'" . $e($f->kind) . "'",
                (string) $f->owner,
                $null($f->source === null ? null : $e($f->source)),
                $null($f->spec === null ? null : $e($f->spec)),
                $null($f->op === null ? null : $e($f->op)),
                (string) $f->step,
                $nullInt($f->group),
                "'" . $e($f->origin) . "'",
                $f->unit ? '1' : '0',
                $nullInt($f->cancelTo),
                "'" . $e($f->status) . "'",
                $this->json($f->params),
                $this->json($f->payload),
                $this->json($f->choice),
            ]) . ')',
        );
    }

    /**
     * @param array<string, mixed>|null $value
     */
    private function json(?array $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        return "'" . $this->db->escape(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) . "'";
    }

    private function assertNotRunaway(): void
    {
        if ($this->count() > self::MAX_FRAMES) {
            throw new \LogicException('effect stack overflow (runaway effects)');
        }
    }
}
