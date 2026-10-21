<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Data;

/**
 * 効果解決スタックの frame 1 つ（effect_frame の 1 行。仕様書 007 §6）。params / payload / choice は JSON（配列）。
 * 公開してはならない値を入れない。
 */
final class EffectFrame
{
    public const READY = 'READY';
    public const WAITING = 'WAITING';

    /** 手札上限の確認（006 §7.1）。unit の終わりに積み、70 が超過したプレイヤーを 72 へ送る。 */
    public const KIND_HAND_LIMIT_CHECK = 'HAND_LIMIT_CHECK';

    /** `EffectSpec` の操作列の実行（008 §1.2。`spec` と `step`。選択の途中経過は `params['decisions']` と `choice`）。 */
    public const KIND_SPEC_RUN = 'SPEC_RUN';

    /** 原子操作 1 つ（`op`。パラメータの無い操作だけ。OP-05 など）。 */
    public const KIND_OP = 'OP';

    /** 想起効果の発動（006 §5.2。`source` は表向きの想い出カード `M0NN`、`params` は `times`・`nth`・`groups`）。 */
    public const KIND_RECALL_ACTIVATION = 'RECALL_ACTIVATION';

    /** ターンプレイヤーが開始時の部屋のカードを獲得する（OP-26 と同じ）。 */
    public const KIND_ACQUIRE_ROOM_CARD = 'ACQUIRE_ROOM_CARD';

    /** 自己構成アクション（61 へ。`params` は `ctx`・`discount`）。 */
    public const KIND_COMPOSE = 'COMPOSE';

    /** 精神分析アクション（62 へ。`params` は `ctx`・`discount`）。 */
    public const KIND_ANALYZE = 'ANALYZE';

    /** 同時に誘発した効果の解決順の選択（`params['effects']` に残りの効果）。 */
    public const KIND_ORDER_EFFECTS = 'ORDER_EFFECTS';

    /** 勝利・全員敗北の判定の窓（現在は何もせず消える。I-08 が使う）。 */
    public const KIND_END_CHECK = 'END_CHECK';

    /** 確認の門（`params['gate'] = 'CONFIRM'`）が付いた frame は、`CONFIRM_USE` の答えが来るまで解決しない。 */
    public const GATE_CONFIRM = 'CONFIRM';

    /**
     * @param array<string, mixed>|null $params
     * @param array<string, mixed>|null $payload
     * @param array<string, mixed>|null $choice
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $owner,
        public readonly ?int $id = null,
        public readonly int $order = 0,
        public readonly ?int $parent = null,
        public readonly ?string $source = null,
        public readonly ?string $spec = null,
        public readonly ?string $op = null,
        public readonly int $step = 0,
        public readonly ?int $group = null,
        public readonly string $origin = '',
        public readonly bool $unit = false,
        public readonly ?int $cancelTo = null,
        public readonly string $status = self::READY,
        public readonly ?array $params = null,
        public readonly ?array $payload = null,
        public readonly ?array $choice = null,
    ) {
    }

    /**
     * `EffectSpec` の操作列の実行 frame。`$confirm` なら、最初に `CONFIRM_USE`（使う / 使わない）を聞く門を付ける。
     *
     * @param array<string, scalar|null> $payload 誘発したイベントの内容
     * @param array<string, mixed> $params 追加のパラメータ（`decisions` は空で始める）
     */
    public static function specRun(
        string $spec,
        int $owner,
        string $source = '',
        array $payload = [],
        bool $confirm = false,
        array $params = [],
    ): self {
        $params = ['decisions' => [], ...$params];
        $choice = null;
        if ($confirm) {
            $params['gate'] = self::GATE_CONFIRM;
            $choice = ['kind' => 'CONFIRM_USE', 'owner' => $owner, 'step' => 0, 'data' => ['source' => $source, 'spec' => $spec]];
        }

        return new self(
            self::KIND_SPEC_RUN,
            $owner,
            source: $source === '' ? null : $source,
            spec: $spec,
            status: $confirm ? self::WAITING : self::READY,
            params: $params,
            payload: $payload === [] ? null : $payload,
            choice: $choice,
        );
    }

    /**
     * 原子操作 1 つ（パラメータの無い操作）の frame。`$op` は `Op` の値（`OP-05`）。
     */
    public static function op(string $op, int $owner): self
    {
        return new self(self::KIND_OP, $owner, op: $op, params: ['decisions' => []]);
    }

    /**
     * 61・62 に入る行動の frame。`$kind` は `COMPOSE` / `ANALYZE`。`$cancelTo` は取り消したときに戻る状態 ID。
     */
    public static function action(string $kind, int $owner, string $context, int $discount, ?int $cancelTo = null): self
    {
        return new self(
            $kind,
            $owner,
            cancelTo: $cancelTo,
            params: ['ctx' => $context, 'discount' => $discount],
        );
    }

    /**
     * 手札上限の確認（単位の終わり）。
     */
    public static function handLimitCheck(int $turnPlayer): self
    {
        return new self(self::KIND_HAND_LIMIT_CHECK, $turnPlayer, unit: true);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $json = static function (mixed $v): ?array {
            if ($v === null || $v === '') {
                return null;
            }
            $decoded = json_decode((string) $v, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        };
        $nullableInt = static fn(mixed $v): ?int => $v === null ? null : (int) $v;
        $nullableString = static fn(mixed $v): ?string => $v === null ? null : (string) $v;

        return new self(
            kind: (string) $row['frame_kind'],
            owner: (int) $row['frame_owner'],
            id: (int) $row['frame_id'],
            order: (int) $row['frame_order'],
            parent: $nullableInt($row['frame_parent']),
            source: $nullableString($row['frame_source']),
            spec: $nullableString($row['frame_spec']),
            op: $nullableString($row['frame_op']),
            step: (int) $row['frame_step'],
            group: $nullableInt($row['frame_group']),
            origin: (string) $row['frame_origin'],
            unit: (int) $row['frame_unit'] === 1,
            cancelTo: $nullableInt($row['frame_cancel_to']),
            status: (string) $row['frame_status'],
            params: $json($row['frame_params']),
            payload: $json($row['frame_payload']),
            choice: $json($row['frame_choice']),
        );
    }
}
