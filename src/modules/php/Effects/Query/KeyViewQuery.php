<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Effects\Query;

use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionContext;
use Bga\Games\IRevolveThereforeIAm\Material\ActionContext;
use Bga\Games\IRevolveThereforeIAm\Material\KeyType;
use Bga\Games\IRevolveThereforeIAm\Material\Modifier;

/**
 * 鍵の読み替え（008 §6.5.4。MD-05）。「元の種類としても万能鍵としても見てよい」（FAQ 行 20）。
 * M094 は精神分析（`ANALYSIS`）で知恵鍵を、M096 は移動（`MOVE`）で意志鍵を万能鍵として見られる。他の状態では読み替えが無い。
 */
final class KeyViewQuery
{
    public function __construct(private readonly ActiveSpecs $active)
    {
    }

    /**
     * 持っている鍵 `$held` を、この文脈で見られる種類（元の種類を先頭に、読み替えがあれば万能鍵を足す）。
     *
     * @return list<KeyType>
     */
    public function viewedAs(int $player, KeyType $held, ActionContext $context): array
    {
        $kinds = [$held];
        foreach ($this->active->modifiers($player, Modifier::KEY_VIEW, new ConditionContext($player)) as $spec) {
            $params = $spec->params;
            if (($params['context'] ?? null) === $context && ($params['from'] ?? null) === $held) {
                $to = $params['to'] ?? null;
                if ($to instanceof KeyType && !in_array($to, $kinds, true)) {
                    $kinds[] = $to;
                }
            }
        }

        return $kinds;
    }

    /**
     * 鍵 `$held` で、`$needed` の錠前・コストを払えるか。万能鍵は何にでも合う。
     */
    public function canServeAs(int $player, KeyType $held, KeyType $needed, ActionContext $context): bool
    {
        if ($held === KeyType::WILD || $held === $needed) {
            return true;
        }

        return in_array(KeyType::WILD, $this->viewedAs($player, $held, $context), true);
    }
}
