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
