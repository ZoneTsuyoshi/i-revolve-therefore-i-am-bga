<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Objective;

use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\SpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionContext;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ActiveSpecs;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\KeyViewQuery;
use Bga\Games\IRevolveThereforeIAm\Material\EffectCatalog;
use Bga\Games\IRevolveThereforeIAm\Material\EffectSpec;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\MemoryCards;

/**
 * 目標カード O001〜O007 の評価（008 §6.7、§7.7、T8-12）。状態を変えない純粋な関数。
 * (a) 窓の中で条件を満たすか、(b) 現在の X、(c) 現在の得点を返す。X は呼ぶたびに数え直す（Q-13）。
 */
final class ObjectiveEvaluator
{
    public function __construct(
        private readonly GameView $view,
        private readonly ConditionEvaluator $conditions,
        private readonly KeyViewQuery $keyView,
    ) {
    }

    /**
     * 本番の組み立て（鍵の読み替えは `ActiveSpecs` から引く）。
     */
    public static function forView(GameView $view, SpecSource $specs): self
    {
        $conditions = new ConditionEvaluator($view);

        return new self($view, $conditions, new KeyViewQuery(new ActiveSpecs($view, $specs, $conditions)));
    }

    /** 目標の個体 ID（1〜7）→ カード ID（`O001`〜`O007`） */
    public static function cardId(int $objectiveId): string
    {
        return sprintf('O%03d', $objectiveId);
    }

    public function evaluate(string $objectiveId, int $player, ObjectiveWindow $window): ObjectiveStatus
    {
        $spec = self::specOf($objectiveId);
        $x = $this->x($spec, $player);

        return new ObjectiveStatus($x, $this->points($spec, $x), $this->conditionMet($spec, $player, $window));
    }

    /** 現在の得点（X を数え直して得点表から引く）。獲得済みの目標のスコアに使う。 */
    public function points(EffectSpec $spec, int $x): int
    {
        $points = 0;
        $table = $spec->params['table'] ?? [];
        foreach (is_array($table) ? $table : [] as $row) {
            if (is_array($row) && $x >= (int) $row[0]) {
                $points = (int) $row[1];
            }
        }

        return $points;
    }

    public function scoreOf(string $objectiveId, int $player): int
    {
        $spec = self::specOf($objectiveId);

        return $this->points($spec, $this->x($spec, $player));
    }

    private static function specOf(string $objectiveId): EffectSpec
    {
        return EffectCatalog::forCard($objectiveId)[0] ?? throw new \OutOfBoundsException("Unknown objective {$objectiveId}");
    }

    private function x(EffectSpec $spec, int $player): int
    {
        $keys = $this->printedKeys($player);

        return match ($spec->params['x'] ?? null) {
            'WILD_MOVE_KEY_TOKENS' => $this->view->wildMoveKeyTokens($player),
            'LEVEL2_COMPOSED' => $this->level2Composed($player),
            'MOST_KEYS' => max($keys[KeyType::WISDOM->value], $keys[KeyType::WILL->value], $keys[KeyType::EMOTION->value]),
            'HAND_COUNT' => count($this->view->handIds($player)),
            'LIGHT_COUNT' => $this->view->lightCount($player),
            'SPIRIT_TIER' => $this->view->spiritTier($player),
            'KEY_SETS' => min($keys[KeyType::WISDOM->value], $keys[KeyType::WILL->value], $keys[KeyType::EMOTION->value]),
            default => throw new \LogicException("Unknown X of {$spec->id}"),
        };
    }

    private function conditionMet(EffectSpec $spec, int $player, ObjectiveWindow $window): bool
    {
        if (!$this->conditions->all($spec->conditions, new ConditionContext($player))) {
            return false;
        }
        $n = (int) ($spec->params['n'] ?? 0);

        return match ($spec->params['when'] ?? null) {
            'ROOMS_ENTERED' => true,
            'LEVEL2_COMPOSED_GE' => $this->level2Composed($player) >= $n,
            'MOST_KEYS_GE' => $this->x($spec, $player) >= $n,
            // 81 の中でだけ（クリーンアップ後、手札上限の調整後。Q-30）
            'HAND_AT_TURN_END_GE' => $window->turnEnd && count($this->view->handIds($player)) >= $n,
            'LIGHTS_GAINED_GE' => $this->view->lightsGainedThisTurn() >= $n,
            'ANALYSIS_ACTIONS_GE' => $this->view->analysisActionsDone() >= $n,
            'ALL_KEY_KINDS_WITH_WILD' => $this->allKinds($player, $window),
            default => throw new \LogicException("Unknown condition of {$spec->id}"),
        };
    }

    /**
     * O007: 知恵・意志・感情・万能の鍵が全て存在する。万能鍵は印刷された万能鍵と、窓の文脈の読み替え（M094・M096）を含む（FAQ 行 29）。
     */
    private function allKinds(int $player, ObjectiveWindow $window): bool
    {
        $keys = $this->printedKeys($player);
        foreach ([KeyType::WISDOM, KeyType::WILL, KeyType::EMOTION] as $kind) {
            if ($keys[$kind->value] < 1) {
                return false;
            }
        }
        if ($keys[KeyType::WILD->value] > 0) {
            return true;
        }
        if ($window->keyContext === null) {
            return false;
        }
        foreach (KeyType::cases() as $held) {
            if ($keys[$held->value] > 0 && in_array(KeyType::WILD, $this->keyView->viewedAs($player, $held, $window->keyContext), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 自己構成済みカードに印刷された鍵の本数（万能鍵は別に数える。読み替えは含めない。Q-68）。
     *
     * @return array<string, int> 鍵の種類の値 → 本数
     */
    private function printedKeys(int $player): array
    {
        $keys = [KeyType::WISDOM->value => 0, KeyType::WILL->value => 0, KeyType::EMOTION->value => 0, KeyType::WILD->value => 0];
        foreach ($this->view->tableauIds($player) as $cardId) {
            foreach (MemoryCards::get($this->view->cardType($cardId))->keys as $key) {
                $keys[$key->value]++;
            }
        }

        return $keys;
    }

    private function level2Composed(int $player): int
    {
        $count = 0;
        foreach ($this->view->tableauIds($player) as $cardId) {
            if (MemoryCards::get($this->view->cardType($cardId))->level === 2) {
                $count++;
            }
        }

        return $count;
    }
}
