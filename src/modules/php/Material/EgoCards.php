<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm\Material;

// 自動生成。仕様書 002 の表から scripts/build-card-data.mjs が生成した。手で編集しない。
// 変更は仕様書 002（または card.csv）を直して、npm run build:cards で再生成する。

/**
 * 初期自我 8 枚・強化自我 5 枚の定義（002 §9）。初期自我の効果の種類は印刷にも card.csv にも無く、null（008 が割り当てる）。
 */
final class EgoCards
{
    /** 行の並びは EgoCardDefinition のコンストラクタ引数（`id` の次から）と同じ。 */
    private const ROWS = [
        'I01' => [
            '巨匠', 'Maestro', 'maestro', EgoKind::INITIAL,
            null,
            '自己構成済みカードが1/3/5/7/9枚目になったとき、想い出の山札を1枚引いて手札に加える',
        ],
        'I02' => [
            '領事', 'Consul', 'consul', EgoKind::INITIAL,
            null,
            '精神分析トラックで心光を獲得したとき、追加で心光を1つ獲得する',
        ],
        'I03' => [
            '建築家', 'Architect', 'architect', EgoKind::INITIAL,
            null,
            '自己構成アクション時、自己構成をする代わりに以下を行っても良い：自己構成済みカードを1枚捨札にし、捨てたカードの自己構成' .
                'エネルギーより2まで大きい想い出カードを、エネルギーを支払わずに自己構成する',
        ],
        'I04' => [
            '論理学者', 'Logician', 'logician', EgoKind::INITIAL,
            null,
            '精神分析コストとして想い出カードを支払った場合、現在自分がいる部屋から反時計回りに床カードの下に1枚ずつコストとして支払' .
                'った想い出カードを置く。床カードの下に想い出カードが置かれている部屋に自分が止まった場合、その想い出カードを自分の手札に' .
                '加える',
        ],
        'I05' => [
            '起業家', 'Entrepreneur', 'entrepreneur', EgoKind::INITIAL,
            null,
            'ターン開始時、どの人格もいない回廊上の想い出カードと手札の同レベルの想い出カードを1枚入れ替えてもよい',
        ],
        'I06' => [
            '擁護者', 'Advocate', 'advocate', EgoKind::INITIAL,
            null,
            '移動フェイズで万能移動鍵トークンを1つも使わなかった場合、移動フェイズ終了時に想い出の山札からカードを1枚引いて手札に加える',
        ],
        'I07' => [
            '冒険家', 'Adventurer', 'adventurer', EgoKind::INITIAL,
            null,
            '各ターンに初めて他の人格がいる部屋を通過したとき、想い出の山札から1枚引いて手札に加える',
        ],
        'I08' => [
            '主人公', 'Protagonist', 'protagonist', EgoKind::INITIAL,
            null,
            '自己構成をするとき、想い出カードを2枚まで追加で支払ってもよい。そうした場合、追加で支払った想い出カードを自己構成したカ' .
                'ードの下に裏向きで置く。それらの想い出カード1枚につき自己構成スコア+1',
        ],
        'E01' => [
            '頑張り屋な', 'Diligent', 'diligent', EgoKind::ENHANCEMENT,
            EffectKind::CONSTANT,
            '自己構成時、自己構成エネルギーを1軽減する。',
        ],
        'E02' => [
            '素直な', 'Candid', 'candid', EgoKind::ENHANCEMENT,
            EffectKind::CONSTANT,
            '精神分析を行うとき、精神分析コストを1軽減する。',
        ],
        'E03' => [
            '照れ屋な', 'Shy', 'shy', EgoKind::ENHANCEMENT,
            EffectKind::TURN_START,
            'ターン開始時、想い出の山札から1枚引いて手札に加える。',
        ],
        'E04' => [
            '気まぐれな', 'Capricious', 'capricious', EgoKind::ENHANCEMENT,
            EffectKind::TURN_START,
            'ターン開始時、心光を獲得する。',
        ],
        'E05' => [
            '真面目な', 'Earnest', 'earnest', EgoKind::ENHANCEMENT,
            EffectKind::TURN_START,
            'ターン開始時、万能移動鍵トークンを獲得する。',
        ],
    ];

    /** @var array<string, EgoCardDefinition>|null */
    private static ?array $all = null;

    /**
     * @return array<string, EgoCardDefinition> ID をキーとする定義（定義順）
     */
    public static function all(): array
    {
        if (self::$all === null) {
            $all = [];
            foreach (self::ROWS as $id => $row) {
                $all[$id] = new EgoCardDefinition($id, ...$row);
            }
            self::$all = $all;
        }

        return self::$all;
    }

    /**
     * 自我カードの定義を返す（`I01`〜`I08`、`E01`〜`E05`）。
     *
     * @throws \OutOfBoundsException 未知の ID
     */
    public static function get(string $id): EgoCardDefinition
    {
        return self::all()[$id] ?? throw new \OutOfBoundsException("Unknown card id: {$id}");
    }
}
