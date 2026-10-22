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
 * 想い出カード 99 種（144 枚）の定義（002 §6）。
 */
final class MemoryCards
{
    /** 行の並びは MemoryCardDefinition のコンストラクタ引数（`id` の次から）と同じ。 */
    private const ROWS = [
        'M001' => [
            '捨てられたイデア', 'Abandoned Idea', 'abandoned_idea',
            1, 1, 2, [KeyType::WISDOM], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M002' => [
            '既視感', 'Deja Vu', 'deja_vu',
            1, 1, 2, [KeyType::EMOTION], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M003' => [
            '荒廃', 'Desolation', 'desolation',
            1, 1, 2, [KeyType::WILL], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M004' => [
            '解かれぬ構造', 'Unsolved Structure', 'unsolved_structure',
            1, 1, 2, [KeyType::WISDOM], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M005' => [
            '肖像', 'Portrait', 'portrait',
            1, 1, 2, [KeyType::EMOTION], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M006' => [
            '自由の代償', 'Price of Freedom', 'price_of_freedom',
            1, 1, 2, [KeyType::WILL], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M007' => [
            '色褪せた写真', 'Faded Photograph', 'faded_photograph',
            1, 1, 2, [KeyType::WISDOM], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M008' => [
            '予知夢', 'Prophetic Dream', 'prophetic_dream',
            1, 1, 2, [KeyType::EMOTION], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M009' => [
            '届かなかった想い', 'Unreached Feelings', 'unreached_feelings',
            1, 1, 2, [KeyType::WILL], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M010' => [
            '閉ざされた論理', 'Closed Logic', 'closed_logic',
            1, 1, 2, [KeyType::WISDOM], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M011' => [
            '涙の輝き', 'Glitter of Tears', 'glitter_of_tears',
            1, 1, 2, [KeyType::EMOTION], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M012' => [
            '虚ろな情報', 'Hollow Information', 'hollow_information',
            1, 1, 2, [KeyType::WILL], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M013' => [
            '問い', 'The Question', 'the_question',
            1, 1, 2, [KeyType::WISDOM], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::LIGHT_1,
        ],
        'M014' => [
            '叡智の末路', 'Fate of Wisdom', 'fate_of_wisdom',
            1, 1, 2, [KeyType::EMOTION], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::NEXT_COMPOSE_DISCOUNT_1,
        ],
        'M015' => [
            '意識の縁', 'Edge of Consciousness', 'edge_of_consciousness',
            1, 1, 2, [KeyType::WILL], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::OPEN_DRAW_1,
        ],
        'M016' => [
            '想い出の忘却', 'Forgetting Memories', 'forgetting_memories',
            1, 1, 2, [KeyType::WISDOM], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::LIGHT_1,
        ],
        'M017' => [
            '重圧', 'Pressure', 'pressure',
            1, 1, 2, [KeyType::EMOTION], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::NEXT_COMPOSE_DISCOUNT_1,
        ],
        'M018' => [
            '強大な障壁', 'Mighty Barrier', 'mighty_barrier',
            1, 1, 2, [KeyType::WILL], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::OPEN_DRAW_1,
        ],
        'M019' => [
            '理解の限界', 'Limit of Understanding', 'limit_of_understanding',
            1, 1, 2, [KeyType::WISDOM], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::LIGHT_1,
        ],
        'M020' => [
            '幼き日の恐怖', 'Childhood Fear', 'childhood_fear',
            1, 1, 2, [KeyType::EMOTION], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::NEXT_COMPOSE_DISCOUNT_1,
        ],
        'M021' => [
            '自己の数式', 'Formula of the Self', 'formula_of_the_self',
            1, 1, 2, [KeyType::WILL], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::OPEN_DRAW_1,
        ],
        'M022' => [
            '理性の自壊', 'Collapse of Reason', 'collapse_of_reason',
            1, 1, 2, [KeyType::WISDOM], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DISCARD_FOR_LIGHT,
        ],
        'M023' => [
            '憂鬱', 'Melancholy', 'melancholy',
            1, 1, 2, [KeyType::EMOTION], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::SWAP_SAME_COST,
        ],
        'M024' => [
            '正義の重み', 'Weight of Justice', 'weight_of_justice',
            1, 1, 2, [KeyType::WILL], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::SWAP_HAND_FIELD,
        ],
        'M025' => [
            '連続性の崩壊', 'Broken Continuity', 'broken_continuity',
            1, 1, 2, [KeyType::WISDOM], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DISCARD_FOR_LIGHT,
        ],
        'M026' => [
            '忘れられた約束', 'Forgotten Promise', 'forgotten_promise',
            1, 1, 2, [KeyType::EMOTION], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::SWAP_SAME_COST,
        ],
        'M027' => [
            '別れの背中', 'Back of Parting', 'back_of_parting',
            1, 1, 2, [KeyType::WILL], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::SWAP_HAND_FIELD,
        ],
        'M028' => [
            '歪曲する記録', 'Distorted Record', 'distorted_record',
            1, 1, 2, [KeyType::WISDOM], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DISCARD_FOR_LIGHT,
        ],
        'M029' => [
            '古代の美学', 'Ancient Aesthetics', 'ancient_aesthetics',
            1, 1, 2, [KeyType::EMOTION], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::SWAP_SAME_COST,
        ],
        'M030' => [
            '矛盾を抱いた決断', 'Contradictory Decision', 'contradictory_decision',
            1, 1, 2, [KeyType::WILL], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::SWAP_HAND_FIELD,
        ],
        'M031' => [
            '曖昧な記憶', 'Vague Memory', 'vague_memory',
            1, 1, 2, [KeyType::WISDOM], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::ALT_ANALYSIS,
        ],
        'M032' => [
            '宙吊りの罪悪感', 'Suspended Guilt', 'suspended_guilt',
            1, 1, 2, [KeyType::EMOTION], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::ALT_COMPOSE,
        ],
        'M033' => [
            '零れ落ちる星屑', 'Spilling Stardust', 'spilling_stardust',
            1, 1, 2, [KeyType::WILL], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::MOVE_NEXT_LV1,
        ],
        'M034' => [
            '思考の鍵', 'Key of Thought', 'key_of_thought',
            1, 1, 2, [KeyType::WISDOM], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::ALT_ANALYSIS,
        ],
        'M035' => [
            '巣立ち', 'Leaving the Nest', 'leaving_the_nest',
            1, 1, 2, [KeyType::EMOTION], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::ALT_COMPOSE,
        ],
        'M036' => [
            '反復する日常', 'Repeating Routine', 'repeating_routine',
            1, 1, 2, [KeyType::WILL], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::MOVE_NEXT_LV1,
        ],
        'M037' => [
            'こだまする問い', 'Echoing Question', 'echoing_question',
            1, 1, 2, [KeyType::WISDOM], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::ALT_ANALYSIS,
        ],
        'M038' => [
            '虚無の囁き', 'Whisper of the Void', 'whisper_of_the_void',
            1, 1, 2, [KeyType::EMOTION], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::ALT_COMPOSE,
        ],
        'M039' => [
            '錯覚の牢獄', 'Prison of Illusion', 'prison_of_illusion',
            1, 1, 2, [KeyType::WILL], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::MOVE_NEXT_LV1,
        ],
        'M040' => [
            '日記', 'Diary', 'diary',
            1, 1, 3, [KeyType::WILD], CardBack::Rev1B, 2,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M041' => [
            '裏切りの果て', 'End of Betrayal', 'end_of_betrayal',
            1, 1, 3, [KeyType::WILD], CardBack::Rev1H, 2,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M042' => [
            '意見の錯綜', 'Tangled Opinions', 'tangled_opinions',
            1, 1, 3, [KeyType::WILD], CardBack::Rev1F, 2,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M043' => [
            'アーカイブ', 'Archive', 'archive',
            1, 1, 3, [KeyType::WILD], CardBack::Rev1B, 2,
            EffectKind::NONE,
            '',
            RecallEffect::LIGHT_1,
        ],
        'M044' => [
            '嫉妬の茨', 'Thorns of Jealousy', 'thorns_of_jealousy',
            1, 1, 3, [KeyType::WILD], CardBack::Rev1H, 2,
            EffectKind::NONE,
            '',
            RecallEffect::NEXT_COMPOSE_DISCOUNT_1,
        ],
        'M045' => [
            '革新の予感', 'Premonition of Innovation', 'premonition_of_innovation',
            1, 1, 3, [KeyType::WILD], CardBack::Rev1F, 2,
            EffectKind::NONE,
            '',
            RecallEffect::OPEN_DRAW_1,
        ],
        'M046' => [
            '迷宮の起点', 'Origin of the Labyrinth', 'origin_of_the_labyrinth',
            1, 1, 3, [KeyType::WILD], CardBack::Rev1B, 2,
            EffectKind::NONE,
            '',
            RecallEffect::DISCARD_FOR_LIGHT,
        ],
        'M047' => [
            '消えた日常', 'Vanished Routine', 'vanished_routine',
            1, 1, 3, [KeyType::WILD], CardBack::Rev1H, 2,
            EffectKind::NONE,
            '',
            RecallEffect::SWAP_SAME_COST,
        ],
        'M048' => [
            '存在の彼岸', 'Beyond Existence', 'beyond_existence',
            1, 1, 3, [KeyType::WILD], CardBack::Rev1F, 2,
            EffectKind::NONE,
            '',
            RecallEffect::SWAP_HAND_FIELD,
        ],
        'M049' => [
            'エイドスの残響', 'Echo of Eidos', 'echo_of_eidos',
            1, 1, 3, [KeyType::WILD], CardBack::Rev1B, 2,
            EffectKind::NONE,
            '',
            RecallEffect::ALT_ANALYSIS,
        ],
        'M050' => [
            '涙の味', 'Taste of Tears', 'taste_of_tears',
            1, 1, 3, [KeyType::WILD], CardBack::Rev1H, 2,
            EffectKind::NONE,
            '',
            RecallEffect::ALT_COMPOSE,
        ],
        'M051' => [
            '螺旋の指南', 'Spiral Guidance', 'spiral_guidance',
            1, 1, 3, [KeyType::WILD], CardBack::Rev1F, 2,
            EffectKind::NONE,
            '',
            RecallEffect::MOVE_NEXT_LV1,
        ],
        'M052' => [
            '選択と未来', 'Choice and Future', 'choice_and_future',
            1, 1, 3, [KeyType::WISDOM], CardBack::Rev1B, 2,
            EffectKind::TURN_START,
            'ターン開始時、回廊上の2箇所の同レベルの想い出カードを入れ替えてもよい',
            RecallEffect::ALT_ANALYSIS,
        ],
        'M053' => [
            '主観と客観', 'Subjective and Objective', 'subjective_and_objective',
            1, 1, 3, [KeyType::EMOTION], CardBack::Rev1H, 2,
            EffectKind::TURN_START,
            'ターン開始時、回廊上の2箇所の同レベルの想い出カードを入れ替えてもよい',
            RecallEffect::ALT_COMPOSE,
        ],
        'M054' => [
            '自己像', 'Self-Image', 'self_image',
            1, 1, 3, [KeyType::WILL], CardBack::Rev1F, 2,
            EffectKind::TURN_START,
            'ターン開始時、回廊上の2箇所の同レベルの想い出カードを入れ替えてもよい',
            RecallEffect::MOVE_NEXT_LV1,
        ],
        'M055' => [
            '記憶の目録', 'Catalog of Memories', 'catalog_of_memories',
            1, 1, 3, [KeyType::WISDOM], CardBack::Rev1B, 2,
            EffectKind::TURN_START,
            'ターン開始時、回廊上の2箇所のレベル1の[床]を入れ替えてもよい',
            RecallEffect::ALT_ANALYSIS,
        ],
        'M056' => [
            '正義の境界線', 'Boundary of Justice', 'boundary_of_justice',
            1, 1, 3, [KeyType::EMOTION], CardBack::Rev1H, 2,
            EffectKind::TURN_START,
            'ターン開始時、回廊上の2箇所のレベル1の[床]を入れ替えてもよい',
            RecallEffect::ALT_COMPOSE,
        ],
        'M057' => [
            '封印された選択肢', 'Sealed Choice', 'sealed_choice',
            1, 1, 3, [KeyType::WILL], CardBack::Rev1F, 2,
            EffectKind::TURN_START,
            'ターン開始時、回廊上の2箇所のレベル1の[床]を入れ替えてもよい',
            RecallEffect::MOVE_NEXT_LV1,
        ],
        'M058' => [
            '観測前夜', 'Eve of Observation', 'eve_of_observation',
            2, 1, 3, [KeyType::WISDOM], CardBack::Rev1B, 2,
            EffectKind::ACTIVATED,
            '[心光]が配置されていない[精神]を精神分析したとき、[心光]+1',
            RecallEffect::LIGHT_1,
        ],
        'M059' => [
            '矛盾した公理', 'Contradictory Axiom', 'contradictory_axiom',
            2, 1, 3, [KeyType::WISDOM], CardBack::Rev1B, 2,
            EffectKind::CONSTANT,
            '精神分析時、1/2/3/4/5回目の心光の行使では[心光]を1/1/2/2/3個支払えばよい',
            RecallEffect::LIGHT_1,
        ],
        'M060' => [
            'あの日の残像', 'Afterimage of That Day', 'afterimage_of_that_day',
            2, 1, 3, [KeyType::EMOTION], CardBack::Rev1H, 2,
            EffectKind::CONSTANT,
            'レベル2の想い出カードを自己構成するとき、[エネルギー]-1',
            RecallEffect::NEXT_COMPOSE_DISCOUNT_1,
        ],
        'M061' => [
            '没頭する刻', 'Moment of Immersion', 'moment_of_immersion',
            2, 1, 3, [KeyType::EMOTION], CardBack::Rev1H, 2,
            EffectKind::ACTIVATED,
            '移動フェイズで移動コストとして3種類全ての鍵([意志鍵]、[知恵鍵]、[感情鍵])を使用していれば、移動フェイズの終了時に[山札]+1',
            RecallEffect::NEXT_COMPOSE_DISCOUNT_1,
        ],
        'M062' => [
            '自己矛盾', 'Self-Contradiction', 'self_contradiction',
            2, 1, 3, [KeyType::WILL], CardBack::Rev1F, 2,
            EffectKind::ACTIVATED,
            '他の人格がいる部屋で移動フェイズを終えたとき、想起効果を2回発動できる',
            RecallEffect::OPEN_DRAW_1,
        ],
        'M063' => [
            '閉じたアルバム', 'Closed Album', 'closed_album',
            2, 1, 3, [KeyType::WILL], CardBack::Rev1F, 2,
            EffectKind::CONSTANT,
            '移動フェイズで、1/2/3/4/5回目の心光の行使は[心光]を1/1/2/2/3個支払えばよい',
            RecallEffect::OPEN_DRAW_1,
        ],
        'M064' => [
            '瞬間の保存', 'Preserved Moment', 'preserved_moment',
            1, 1, 3, [KeyType::WISDOM], CardBack::Rev1B, 2,
            EffectKind::ACTIVATED,
            '移動フェイズで、このターンに初めてレベル2の部屋に入る場合、[移動コスト]-1',
            RecallEffect::ALT_ANALYSIS,
        ],
        'M065' => [
            '思惟の渦中', 'Amid Contemplation', 'amid_contemplation',
            1, 1, 3, [KeyType::EMOTION], CardBack::Rev1H, 2,
            EffectKind::ACTIVATED,
            '移動フェイズで、このターンに初めてレベル2の部屋に入る場合、[移動コスト]-1',
            RecallEffect::ALT_COMPOSE,
        ],
        'M066' => [
            '偉大なる指導者', 'Great Leader', 'great_leader',
            1, 1, 3, [KeyType::WILL], CardBack::Rev1F, 2,
            EffectKind::ACTIVATED,
            '移動フェイズで、このターンに初めてレベル2の部屋に入る場合、[移動コスト]-1',
            RecallEffect::MOVE_NEXT_LV1,
        ],
        'M067' => [
            '偉人のひらめき', 'Great Mind\'s Inspiration', 'great_minds_inspiration',
            3, 1, 3, [KeyType::WISDOM], CardBack::Rev1B, 2,
            EffectKind::ACTIVATED,
            '1ターンに4部屋以上移動したら[心光]+1',
            RecallEffect::ALT_ANALYSIS,
        ],
        'M068' => [
            '夏至の午後', 'Summer Solstice Afternoon', 'summer_solstice_afternoon',
            3, 1, 3, [KeyType::EMOTION], CardBack::Rev1H, 2,
            EffectKind::ACTIVATED,
            '1ターンに4部屋以上移動していれば、そのターン内で最初の自己構成時の[エネルギー]-1',
            RecallEffect::ALT_COMPOSE,
        ],
        'M069' => [
            '希望の篝火', 'Bonfire of Hope', 'bonfire_of_hope',
            3, 1, 3, [KeyType::WILL], CardBack::Rev1F, 2,
            EffectKind::ACTIVATED,
            '1ターンに4歩以上移動したら[場札]+1',
            RecallEffect::MOVE_NEXT_LV1,
        ],
        'M070' => [
            '終端の構造', 'Structure of the Terminus', 'structure_of_the_terminus',
            2, 1, 4, [KeyType::WISDOM], CardBack::Rev1B, 2,
            EffectKind::CONSTANT,
            '自己構成時、1/2/3/4/5回目の心光の行使は[心光]を1/1/2/2/3支払えばよい',
            RecallEffect::DISCARD_FOR_LIGHT,
        ],
        'M071' => [
            '子守唄', 'Lullaby', 'lullaby',
            2, 1, 4, [KeyType::EMOTION], CardBack::Rev1H, 2,
            EffectKind::ACTIVATED,
            '自己構成時、自己構成したカードの想起効果を使用してもよい',
            RecallEffect::SWAP_SAME_COST,
        ],
        'M072' => [
            '鏡の向こう', 'Beyond the Mirror', 'beyond_the_mirror',
            2, 1, 4, [KeyType::WILL], CardBack::Rev1F, 2,
            EffectKind::TURN_START,
            'ターン開始時、好きな部屋にワープして入室する。ワープではその部屋に「入室済み」とはみなされない',
            RecallEffect::SWAP_HAND_FIELD,
        ],
        'M073' => [
            '断絶した思考の輪', 'Severed Ring of Thought', 'severed_ring_of_thought',
            2, 1, 4, [KeyType::WISDOM], CardBack::Rev1B, 2,
            EffectKind::ACTIVATED,
            '[知恵鍵]を持つ想い出カードを自己構成するたび、[山札]+1',
            RecallEffect::DRAW_1,
        ],
        'M074' => [
            '心の修復', 'Mending the Heart', 'mending_the_heart',
            2, 1, 4, [KeyType::EMOTION], CardBack::Rev1H, 2,
            EffectKind::ACTIVATED,
            '[感情鍵]を持つ想い出カードを自己構成するたび、[山札]+1',
            RecallEffect::DRAW_1,
        ],
        'M075' => [
            '友情の証', 'Proof of Friendship', 'proof_of_friendship',
            2, 1, 4, [KeyType::WILL], CardBack::Rev1F, 2,
            EffectKind::ACTIVATED,
            '[意志鍵]を持つ想い出カードを自己構成するたび、[山札]+1',
            RecallEffect::DRAW_1,
        ],
        'M076' => [
            '昇華', 'Sublimation', 'sublimation',
            4, 1, 4, [KeyType::WISDOM], CardBack::Rev1B, 2,
            EffectKind::ACTIVATED,
            '[知恵鍵]を持つ想い出カードを自己構成するたび、[心光]+1',
            RecallEffect::LIGHT_1,
        ],
        'M077' => [
            '眠りの瞬間', 'Moment of Sleep', 'moment_of_sleep',
            4, 1, 4, [KeyType::EMOTION], CardBack::Rev1H, 2,
            EffectKind::CONSTANT,
            '[感情鍵]を持つ想い出カードを自己構成するとき、[エネルギー]-1',
            RecallEffect::NEXT_COMPOSE_DISCOUNT_1,
        ],
        'M078' => [
            '夕暮れの約束', 'Twilight Promise', 'twilight_promise',
            4, 1, 4, [KeyType::WILL], CardBack::Rev1F, 2,
            EffectKind::ACTIVATED,
            '[意志鍵]を持つ想い出カードを自己構成するたび、[場札]+1',
            RecallEffect::OPEN_DRAW_1,
        ],
        'M079' => [
            '過去への扉', 'Door to the Past', 'door_to_the_past',
            3, 1, 4, [KeyType::WISDOM], CardBack::Rev1B, 2,
            EffectKind::ACTIVATED,
            '精神分析階層を上げたとき、[心光]+1',
            RecallEffect::DISCARD_FOR_LIGHT,
        ],
        'M080' => [
            '赦しの言葉', 'Words of Forgiveness', 'words_of_forgiveness',
            3, 1, 4, [KeyType::EMOTION], CardBack::Rev1H, 2,
            EffectKind::ACTIVATED,
            '精神分析階層を上げたとき、そのターン内の次の自己構成における[エネルギー]-1',
            RecallEffect::SWAP_SAME_COST,
        ],
        'M081' => [
            '無名の憧れ', 'Nameless Longing', 'nameless_longing',
            3, 1, 4, [KeyType::WILL], CardBack::Rev1F, 2,
            EffectKind::ACTIVATED,
            '精神分析階層を上げたとき、[場札]+1',
            RecallEffect::SWAP_HAND_FIELD,
        ],
        'M082' => [
            '有限と無限の間', 'Between Finite and Infinite', 'between_finite_and_infinite',
            2, 1, 4, [KeyType::WISDOM, KeyType::EMOTION], CardBack::Rev1B, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M083' => [
            '愛の誓い', 'Vow of Love', 'vow_of_love',
            2, 1, 4, [KeyType::EMOTION, KeyType::WILL], CardBack::Rev1H, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M084' => [
            '走り続けた脚', 'Legs That Kept Running', 'legs_that_kept_running',
            2, 1, 4, [KeyType::WILL, KeyType::WISDOM], CardBack::Rev1F, 1,
            EffectKind::NONE,
            '',
            RecallEffect::DRAW_1,
        ],
        'M085' => [
            '照らすもの', 'That Which Illuminates', 'that_which_illuminates',
            2, 2, 5, [KeyType::WISDOM, KeyType::EMOTION], CardBack::Rev2B, 3,
            EffectKind::ACTIVATED,
            '[床]の効果で[心光]獲得時、追加で[心光]+1',
            RecallEffect::DISCARD_FOR_LIGHT,
        ],
        'M086' => [
            '旋律のしぶき', 'Spray of Melody', 'spray_of_melody',
            2, 2, 5, [KeyType::EMOTION, KeyType::WILL], CardBack::Rev2H, 3,
            EffectKind::ACTIVATED,
            '[床]の効果で想い出の山札からカードを獲得するとき、追加で[山札]+1',
            RecallEffect::SWAP_SAME_COST,
        ],
        'M087' => [
            '勇気の残り香', 'Lingering Scent of Courage', 'lingering_scent_of_courage',
            2, 2, 5, [KeyType::WILL, KeyType::WISDOM], CardBack::Rev2F, 3,
            EffectKind::ACTIVATED,
            '[床]の効果で想い出の場札からカードを獲得するとき、追加で[場札]+1',
            RecallEffect::SWAP_HAND_FIELD,
        ],
        'M088' => [
            '知識の方舟', 'Ark of Knowledge', 'ark_of_knowledge',
            2, 2, 5, [KeyType::WISDOM, KeyType::WILL], CardBack::Rev2B, 3,
            EffectKind::INSTANT,
            '精神分析階層を1つ上げる。ただし、この効果で第五階層に上げることはできない',
            RecallEffect::LIGHT_1,
        ],
        'M089' => [
            '好きぴ', 'Crush', 'crush',
            2, 2, 6, [KeyType::EMOTION, KeyType::WISDOM], CardBack::Rev2H, 2,
            EffectKind::INSTANT,
            '自己構成エネルギーを支払わずにレベル1想い出カードを1枚自己構成してもよい',
            RecallEffect::NEXT_COMPOSE_DISCOUNT_1,
        ],
        'M090' => [
            '絆の糸', 'Thread of Bonds', 'thread_of_bonds',
            2, 2, 6, [KeyType::WILL, KeyType::EMOTION], CardBack::Rev2F, 3,
            EffectKind::INSTANT,
            '自己構成済みカード3枚につき[万能移動鍵]+1',
            RecallEffect::OPEN_DRAW_1,
        ],
        'M091' => [
            '無限回廊の地図', 'Map of the Infinite Cloister', 'map_of_the_infinite_cloister',
            1, 2, 5, [KeyType::WILD, KeyType::WILD], CardBack::Rev2B, 3,
            EffectKind::NONE,
            '',
            RecallEffect::LIGHT_1,
        ],
        'M092' => [
            '勝利の瞬間', 'Moment of Victory', 'moment_of_victory',
            1, 2, 5, [KeyType::WILD, KeyType::WILD], CardBack::Rev2H, 3,
            EffectKind::NONE,
            '',
            RecallEffect::NEXT_COMPOSE_DISCOUNT_1,
        ],
        'M093' => [
            '彼方への越境', 'Crossing Beyond', 'crossing_beyond',
            1, 2, 5, [KeyType::WILD, KeyType::WILD], CardBack::Rev2F, 3,
            EffectKind::NONE,
            '',
            RecallEffect::OPEN_DRAW_1,
        ],
        'M094' => [
            '知識の迷宮', 'Labyrinth of Knowledge', 'labyrinth_of_knowledge',
            2, 2, 5, [KeyType::WISDOM, KeyType::WISDOM], CardBack::Rev2B, 3,
            EffectKind::CONSTANT,
            '精神分析時、[知恵鍵]は全て[万能鍵]として扱う',
            RecallEffect::ALT_ANALYSIS_DISCOUNT_1,
        ],
        'M095' => [
            '歓喜の残滓', 'Remnants of Joy', 'remnants_of_joy',
            2, 2, 6, [KeyType::EMOTION, KeyType::EMOTION], CardBack::Rev2H, 3,
            EffectKind::CONSTANT,
            '自己構成時、[感情鍵]を持つこのターン未使用の自己構成済みカードを好きな枚数横向きにする。X枚横向きにしたら[エネルギー]-X',
            RecallEffect::ALT_COMPOSE_DISCOUNT_1,
        ],
        'M096' => [
            '存在の多面性', 'Many Facets of Existence', 'many_facets_of_existence',
            2, 2, 6, [KeyType::WILL, KeyType::WILL], CardBack::Rev2F, 3,
            EffectKind::CONSTANT,
            '移動フェイズ中、[意志鍵]は全て[万能鍵]として扱う',
            RecallEffect::MOVE_NEXT_LV2,
        ],
        'M097' => [
            '文字の恩恵', 'Blessing of Letters', 'blessing_of_letters',
            1, 2, 7, [KeyType::WISDOM, KeyType::WISDOM], CardBack::Rev2B, 4,
            EffectKind::INSTANT,
            '自己構成済みの[知恵鍵]1つにつき、[心光]+1',
            RecallEffect::DRAW_1,
        ],
        'M098' => [
            '母の手の温もり', 'Warmth of Mother\'s Hand', 'warmth_of_mothers_hand',
            1, 2, 7, [KeyType::EMOTION, KeyType::EMOTION], CardBack::Rev2H, 4,
            EffectKind::HELD,
            '自己構成済みの[感情鍵]1つにつき、このカードの[エネルギー]-1',
            RecallEffect::DRAW_1,
        ],
        'M099' => [
            '繋いできた願い', 'Wishes Passed Down', 'wishes_passed_down',
            1, 2, 7, [KeyType::WILL, KeyType::WILL], CardBack::Rev2F, 4,
            EffectKind::INSTANT,
            '自己構成済みの[意志鍵]1つにつき、[場札]+1',
            RecallEffect::DRAW_1,
        ],
    ];

    /** @var array<string, MemoryCardDefinition>|null */
    private static ?array $all = null;

    /**
     * @return array<string, MemoryCardDefinition> ID をキーとする定義（定義順）
     */
    public static function all(): array
    {
        if (self::$all === null) {
            $all = [];
            foreach (self::ROWS as $id => $row) {
                $all[$id] = new MemoryCardDefinition($id, ...$row);
            }
            self::$all = $all;
        }

        return self::$all;
    }

    /**
     * 想い出カードの定義を返す（`M001`〜`M099`）。
     *
     * @throws \OutOfBoundsException 未知の ID
     */
    public static function get(string $id): MemoryCardDefinition
    {
        return self::all()[$id] ?? throw new \OutOfBoundsException("Unknown card id: {$id}");
    }
}
