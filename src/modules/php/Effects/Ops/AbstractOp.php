<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Ops;

use Bga\Games\IRevolveThereforeIAm\Effects\EffectViolation;
use Bga\Games\IRevolveThereforeIAm\Effects\Engine\ChoiceRequest;
use Bga\Games\IRevolveThereforeIAm\Material\ChoiceKind;

/**
 * 原子操作ハンドラの共通部分。選択の無い操作の既定と、答えの検証の道具。
 */
abstract class AbstractOp implements OpHandler
{
    public function choiceKind(): ?ChoiceKind
    {
        return null;
    }

    public function request(OpContext $context): ?ChoiceRequest
    {
        return null;
    }

    /**
     * 答えの `$key` の値を、重複のない整数の配列として取り出す。
     *
     * @return list<int>
     * @throws EffectViolation
     */
    protected function intList(OpAnswer $answer, string $key): array
    {
        $value = $answer->data[$key] ?? [];
        if (!is_array($value)) {
            throw new EffectViolation("'{$key}' must be a list of ids");
        }
        $ids = [];
        foreach ($value as $item) {
            if (!is_int($item)) {
                throw new EffectViolation("'{$key}' must contain integer ids only");
            }
            $ids[] = $item;
        }
        if (count(array_unique($ids)) !== count($ids)) {
            throw new EffectViolation("'{$key}' must not contain duplicates");
        }

        return $ids;
    }

    /**
     * @param list<int> $ids
     * @param list<int> $allowed
     * @throws EffectViolation
     */
    protected function assertSubset(array $ids, array $allowed, string $what): void
    {
        foreach ($ids as $id) {
            if (!in_array($id, $allowed, true)) {
                throw new EffectViolation("Card {$id} is not in {$what}");
            }
        }
    }

    /**
     * @throws EffectViolation
     */
    protected function assertCount(int $actual, int $expected, string $what): void
    {
        if ($actual !== $expected) {
            throw new EffectViolation("Expected {$expected} {$what}, got {$actual}");
        }
    }
}
