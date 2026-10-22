/**
 * カードの英語テキスト表（仕様書 009 §8）。ID をキーにした文字列だけを持つ。数値データ（エネルギー・スコア・鍵・錠前・効果の種類・
 * 想起効果の識別子）は PHP の素材から gamedatas の `catalog` で届くので、ここには置かない（二重管理を避ける）。
 *
 * - `_()` は setup 以降に呼ぶ（モジュールの読み込み時に呼ばない。003 §7.1）。だから表は関数 `buildCardTexts()` の中で作る。
 * - 文字列は `_('...')` のリテラルにする（翻訳の抽出ツールがリテラルしか拾わない）。文を断片から組み立てない。
 * - アイコンは波括弧の記法（`{light}` など。名前は `ui/icons.js`）。描画は `renderText`。
 * - 用語は docs/publishing/game-description.md の英訳案（Memory card、construct、Psychoanalysis、Psychic Light、Mind Track、
 *   Omni-Move Key token など）と、仕様書 002 §14 の英語識別子に合わせる。
 * - 原文は仕様書 002 §6.4・§7〜§10 の日本語（印刷どおり）。作者の確認用の対照表は docs/publishing/card-texts-review.md。
 *
 * 返す形:
 *   cards:   ID → { name, effect? | condition?/x?, action?/bonus? }
 *            M001〜M099（効果のある 42 種だけ `effect`）、F01〜F12（`name` は部屋名、`action` は床アクション）、
 *            R1A〜R5（`bonus`）、I01〜I08・E01〜E05（`effect`）、O001〜O007（`condition` と `x`）
 *   recall:  想起効果の識別子（RecallEffect）→ 英語文
 *   kinds:   効果の種類の識別子（EffectKind）→ 英語名
 * © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 */

/**
 * @typedef {object} CardText
 * @property {string} name
 * @property {string} [effect] 効果文（想い出カード・自我カード）
 * @property {string} [condition] 目標カードの CONDITION
 * @property {string} [x] 目標カードの X の定義
 * @property {string} [action] 床カードの床アクション
 * @property {string} [bonus] 精神カードの精神分析ボーナス
 */

/**
 * @typedef {object} CardTexts
 * @property {Record<string, CardText>} cards
 * @property {Record<string, string>} recall
 * @property {Record<string, string>} kinds
 */

/**
 * 英語テキスト表を作る。`_()` を呼ぶので、setup 以降に呼ぶこと。
 * @returns {CardTexts}
 */
export function buildCardTexts() {
    return {
        cards: {
            // ---- 想い出カード（M001〜M099）。効果のある 42 種は `effect` つき（002 §6.4） ----
            M001: { name: _('Abandoned Idea') },
            M002: { name: _('Deja Vu') },
            M003: { name: _('Desolation') },
            M004: { name: _('Unsolved Structure') },
            M005: { name: _('Portrait') },
            M006: { name: _('Price of Freedom') },
            M007: { name: _('Faded Photograph') },
            M008: { name: _('Prophetic Dream') },
            M009: { name: _('Unreached Feelings') },
            M010: { name: _('Closed Logic') },
            M011: { name: _('Glitter of Tears') },
            M012: { name: _('Hollow Information') },
            M013: { name: _('The Question') },
            M014: { name: _('Fate of Wisdom') },
            M015: { name: _('Edge of Consciousness') },
            M016: { name: _('Forgetting Memories') },
            M017: { name: _('Pressure') },
            M018: { name: _('Mighty Barrier') },
            M019: { name: _('Limit of Understanding') },
            M020: { name: _('Childhood Fear') },
            M021: { name: _('Formula of the Self') },
            M022: { name: _('Collapse of Reason') },
            M023: { name: _('Melancholy') },
            M024: { name: _('Weight of Justice') },
            M025: { name: _('Broken Continuity') },
            M026: { name: _('Forgotten Promise') },
            M027: { name: _('Back of Parting') },
            M028: { name: _('Distorted Record') },
            M029: { name: _('Ancient Aesthetics') },
            M030: { name: _('Contradictory Decision') },
            M031: { name: _('Vague Memory') },
            M032: { name: _('Suspended Guilt') },
            M033: { name: _('Spilling Stardust') },
            M034: { name: _('Key of Thought') },
            M035: { name: _('Leaving the Nest') },
            M036: { name: _('Repeating Routine') },
            M037: { name: _('Echoing Question') },
            M038: { name: _('Whisper of the Void') },
            M039: { name: _('Prison of Illusion') },
            M040: { name: _('Diary') },
            M041: { name: _('End of Betrayal') },
            M042: { name: _('Tangled Opinions') },
            M043: { name: _('Archive') },
            M044: { name: _('Thorns of Jealousy') },
            M045: { name: _('Premonition of Innovation') },
            M046: { name: _('Origin of the Labyrinth') },
            M047: { name: _('Vanished Routine') },
            M048: { name: _('Beyond Existence') },
            M049: { name: _('Echo of Eidos') },
            M050: { name: _('Taste of Tears') },
            M051: { name: _('Spiral Guidance') },
            M052: {
                name: _('Choice and Future'),
                effect: _('At the start of your turn, you may swap 2 Memory cards of the same level on the cloister'),
            },
            M053: {
                name: _('Subjective and Objective'),
                effect: _('At the start of your turn, you may swap 2 Memory cards of the same level on the cloister'),
            },
            M054: {
                name: _('Self-Image'),
                effect: _('At the start of your turn, you may swap 2 Memory cards of the same level on the cloister'),
            },
            M055: {
                name: _('Catalog of Memories'),
                effect: _('At the start of your turn, you may swap 2 level 1 floor cards {floor} on the cloister'),
            },
            M056: {
                name: _('Boundary of Justice'),
                effect: _('At the start of your turn, you may swap 2 level 1 floor cards {floor} on the cloister'),
            },
            M057: {
                name: _('Sealed Choice'),
                effect: _('At the start of your turn, you may swap 2 level 1 floor cards {floor} on the cloister'),
            },
            M058: {
                name: _('Eve of Observation'),
                effect: _('When you Psychoanalyze a Mind Track card {spirit} that has no Psychic Light {light} on it, gain 1 Psychic Light {light}'),
            },
            M059: {
                name: _('Contradictory Axiom'),
                effect: _('When you Psychoanalyze, the 1st/2nd/3rd/4th/5th use of Psychic Light {light} costs only 1/1/2/2/3 {light}'),
            },
            M060: {
                name: _('Afterimage of That Day'),
                effect: _('When you construct a level 2 Memory card, reduce its energy {energy} by 1'),
            },
            M061: {
                name: _('Moment of Immersion'),
                effect: _('If you paid move costs with all 3 types of key (Will {will}, Wisdom {wisdom}, Emotion {emotion}) in the Move phase, draw 1 Memory card {draw} at the end of the Move phase'),
            },
            M062: {
                name: _('Self-Contradiction'),
                effect: _('When you end the Move phase in a room with another personality, you can activate the recall effect twice'),
            },
            M063: {
                name: _('Closed Album'),
                effect: _('In the Move phase, the 1st/2nd/3rd/4th/5th use of Psychic Light {light} costs only 1/1/2/2/3 {light}'),
            },
            M064: {
                name: _('Preserved Moment'),
                effect: _('In the Move phase, the first time you enter a level 2 room this turn, the move cost of that room is reduced by 1'),
            },
            M065: {
                name: _('Amid Contemplation'),
                effect: _('In the Move phase, the first time you enter a level 2 room this turn, the move cost of that room is reduced by 1'),
            },
            M066: {
                name: _('Great Leader'),
                effect: _('In the Move phase, the first time you enter a level 2 room this turn, the move cost of that room is reduced by 1'),
            },
            M067: {
                name: _('Great Mind\'s Inspiration'),
                effect: _('When you have moved 4 or more rooms in a turn, gain 1 Psychic Light {light}'),
            },
            M068: {
                name: _('Summer Solstice Afternoon'),
                effect: _('If you have moved 4 or more rooms in a turn, reduce energy {energy} by 1 the first time you construct that turn'),
            },
            M069: {
                name: _('Bonfire of Hope'),
                effect: _('When you have moved 4 or more rooms in a turn, take 1 open card {open}'),
            },
            M070: {
                name: _('Structure of the Terminus'),
                effect: _('When you construct, the 1st/2nd/3rd/4th/5th use of Psychic Light {light} costs only 1/1/2/2/3 {light}'),
            },
            M071: {
                name: _('Lullaby'),
                effect: _('When you construct, you may use the recall effect of the card you constructed'),
            },
            M072: {
                name: _('Beyond the Mirror'),
                effect: _('At the start of your turn, warp into any room. A room you warp into is not counted as entered'),
            },
            M073: {
                name: _('Severed Ring of Thought'),
                effect: _('Each time you construct a Memory card with a Wisdom key {wisdom}, draw 1 Memory card {draw}'),
            },
            M074: {
                name: _('Mending the Heart'),
                effect: _('Each time you construct a Memory card with an Emotion key {emotion}, draw 1 Memory card {draw}'),
            },
            M075: {
                name: _('Proof of Friendship'),
                effect: _('Each time you construct a Memory card with a Will key {will}, draw 1 Memory card {draw}'),
            },
            M076: {
                name: _('Sublimation'),
                effect: _('Each time you construct a Memory card with a Wisdom key {wisdom}, gain 1 Psychic Light {light}'),
            },
            M077: {
                name: _('Moment of Sleep'),
                effect: _('When you construct a Memory card with an Emotion key {emotion}, reduce energy {energy} by 1'),
            },
            M078: {
                name: _('Twilight Promise'),
                effect: _('Each time you construct a Memory card with a Will key {will}, take 1 open card {open}'),
            },
            M079: {
                name: _('Door to the Past'),
                effect: _('When you move up a Mind Track tier, gain 1 Psychic Light {light}'),
            },
            M080: {
                name: _('Words of Forgiveness'),
                effect: _('When you move up a Mind Track tier, reduce energy {energy} by 1 on your next construction this turn'),
            },
            M081: {
                name: _('Nameless Longing'),
                effect: _('When you move up a Mind Track tier, take 1 open card {open}'),
            },
            M082: { name: _('Between Finite and Infinite') },
            M083: { name: _('Vow of Love') },
            M084: { name: _('Legs That Kept Running') },
            M085: {
                name: _('That Which Illuminates'),
                effect: _('When you gain Psychic Light {light} from a floor card\'s {floor} effect, gain 1 additional Psychic Light {light}'),
            },
            M086: {
                name: _('Spray of Melody'),
                effect: _('When you draw Memory cards from the deck by a floor card\'s {floor} effect, draw 1 additional Memory card {draw}'),
            },
            M087: {
                name: _('Lingering Scent of Courage'),
                effect: _('When you take open cards by a floor card\'s {floor} effect, take 1 additional open card {open}'),
            },
            M088: {
                name: _('Ark of Knowledge'),
                effect: _('Move up 1 Mind Track tier. This effect cannot take you to tier 5'),
            },
            M089: {
                name: _('Crush'),
                effect: _('You may construct 1 level 1 Memory card without paying energy {energy}'),
            },
            M090: {
                name: _('Thread of Bonds'),
                effect: _('For every 3 constructed cards, gain 1 Omni-Move Key token {wildkey}'),
            },
            M091: { name: _('Map of the Infinite Cloister') },
            M092: { name: _('Moment of Victory') },
            M093: { name: _('Crossing Beyond') },
            M094: {
                name: _('Labyrinth of Knowledge'),
                effect: _('When you Psychoanalyze, treat all Wisdom keys {wisdom} as Wild keys {wild}'),
            },
            M095: {
                name: _('Remnants of Joy'),
                effect: _('When you construct, turn sideways any number of your constructed cards that have an Emotion key {emotion} and have not been used this turn. If you turn X cards, reduce energy {energy} by X'),
            },
            M096: {
                name: _('Many Facets of Existence'),
                effect: _('During the Move phase, treat all Will keys {will} as Wild keys {wild}'),
            },
            M097: {
                name: _('Blessing of Letters'),
                effect: _('For each Wisdom key {wisdom} on your constructed cards, gain 1 Psychic Light {light}'),
            },
            M098: {
                name: _('Warmth of Mother\'s Hand'),
                effect: _('For each Emotion key {emotion} on your constructed cards, reduce this card\'s energy {energy} by 1'),
            },
            M099: {
                name: _('Wishes Passed Down'),
                effect: _('For each Will key {will} on your constructed cards, take 1 open card {open}'),
            },

            // ---- 床カード（F01〜F12。002 §7）。`name` は部屋名（印刷が英語）、`action` は床アクション ----
            F01: { name: 'DEMETER', action: _('Draw 3 Memory cards {draw}') },
            F02: { name: 'HERMES', action: _('Take a Psychoanalysis action') },
            F03: { name: 'ATHENA', action: _('Draw 3 Memory cards {draw}, then discard 1 card from your hand') },
            F04: { name: 'POSEIDON', action: _('Take a Construction action. This turn, energy {energy} is always reduced by 1 when you construct') },
            F05: { name: 'ARES', action: _('Take 1 open card {open} and gain 1 Psychic Light {light}') },
            F06: { name: 'DIONYSUS', action: _('Take a Psychoanalysis action') },
            F07: { name: 'HESTIA', action: _('Take 1 open card {open}, draw 1 Memory card {draw}, and gain 1 Psychic Light {light}') },
            F08: { name: 'APOLLO', action: _('Draw 3 Memory cards {draw}, then discard 1 card from your hand') },
            F09: { name: 'APHRODITE', action: _('Take 2 open cards {open} or gain 2 Psychic Light {light} (choose one)') },
            F10: { name: 'HADES', action: _('Take a Psychoanalysis action and gain 1 Psychic Light {light}') },
            F11: { name: 'ARTEMIS', action: _('Take 1 open card {open} and gain 1 Psychic Light {light}') },
            F12: { name: 'HEPHAESTUS', action: _('Take a Construction action with energy {energy} -1, or draw 2 Memory cards {draw} (choose one)') },

            // ---- 精神カード（002 §8）。`name` は階層（分岐 a・b・c は表記しない）、`bonus` は精神分析ボーナス ----
            R1A: { name: _('Tier 1'), bonus: _('Draw 1 Memory card {draw}') },
            R1B: { name: _('Tier 1'), bonus: _('Draw 1 Memory card {draw}') },
            R1C: { name: _('Tier 1'), bonus: _('Draw 1 Memory card {draw}') },
            R2: { name: _('Tier 2'), bonus: _('You may construct 1 level 1 Memory card without paying energy {energy}') },
            R3A: { name: _('Tier 3'), bonus: _('Enhanced Ego: secretly look at the Enhanced Ego cards and take 1') },
            R3B: { name: _('Tier 3'), bonus: _('Enhanced Ego: secretly look at the Enhanced Ego cards and take 1') },
            R3C: { name: _('Tier 3'), bonus: _('Enhanced Ego: secretly look at the Enhanced Ego cards and take 1') },
            R4: { name: _('Tier 4'), bonus: _('You may construct 1 Memory card without paying energy {energy}') },
            R5: { name: _('Tier 5'), bonus: _('Pinnacle of the Mind: you win the game immediately') },

            // ---- 初期自我 8 枚（I01〜I08）と強化自我 5 枚（E01〜E05）。002 §9 ----
            I01: {
                name: _('Maestro'),
                effect: _('When you reach 1/3/5/7/9 constructed cards, draw 1 Memory card from the deck {draw} and add it to your hand'),
            },
            I02: {
                name: _('Consul'),
                effect: _('When you gain Psychic Light {light} on the Mind Track, gain 1 additional Psychic Light {light}'),
            },
            I03: {
                name: _('Architect'),
                effect: _('During a Construction action, instead of constructing, you may do the following: discard 1 constructed card, then construct a Memory card whose energy {energy} is up to 2 higher than the discarded card\'s energy, without paying energy'),
            },
            I04: {
                name: _('Logician'),
                effect: _('When you pay Memory cards as a Psychoanalysis cost, place each paid Memory card under the floor card {floor} of a room, one per room, starting from the room you are in and going counterclockwise. When you stop in a room that has Memory cards under its floor card {floor}, add those Memory cards to your hand'),
            },
            I05: {
                name: _('Entrepreneur'),
                effect: _('At the start of your turn, you may swap 1 Memory card in your hand with a Memory card of the same level on the cloister in a room where no personality is present'),
            },
            I06: {
                name: _('Advocate'),
                effect: _('If you did not use any Omni-Move Key token {wildkey} in the Move phase, draw 1 Memory card from the deck {draw} at the end of the Move phase'),
            },
            I07: {
                name: _('Adventurer'),
                effect: _('The first time each turn you pass through a room with another personality, draw 1 Memory card from the deck {draw}'),
            },
            I08: {
                name: _('Protagonist'),
                effect: _('When you construct, you may pay up to 2 additional Memory cards. If you do, place the additionally paid Memory cards face down under the constructed card. Each of those Memory cards gives +1 Construction Score'),
            },
            E01: { name: _('Diligent'), effect: _('When you construct, reduce energy {energy} by 1') },
            E02: { name: _('Candid'), effect: _('When you Psychoanalyze, reduce the Psychoanalysis cost by 1') },
            E03: { name: _('Shy'), effect: _('At the start of your turn, draw 1 Memory card from the deck {draw}') },
            E04: { name: _('Capricious'), effect: _('At the start of your turn, gain 1 Psychic Light {light}') },
            E05: { name: _('Earnest'), effect: _('At the start of your turn, gain 1 Omni-Move Key token {wildkey}') },

            // ---- 目標カード 7 枚（O001〜O007。002 §10）。得点表は catalog の scoreTable から組む ----
            O001: {
                name: _('Wild Move Key Goal'),
                condition: _('Enter 6 or more rooms in a single turn'),
                x: _('Number of Omni-Move Key tokens {wildkey}'),
            },
            O002: {
                name: _('Level 2 Composed Goal'),
                condition: _('Have constructed 2 or more level 2 Memory cards'),
                x: _('Number of level 2 Memory cards you have constructed'),
            },
            O003: {
                name: _('Single Engine Goal'),
                condition: _('The most common key type among your constructed cards appears 4 or more times (the highest of Will {will}, Wisdom {wisdom}, and Emotion {emotion} is 4 or more)'),
                x: _('Number of keys of the most common type among your constructed cards'),
            },
            O004: {
                name: _('Hand Goal'),
                condition: _('Have 6 or more cards in your hand at the end of your turn'),
                x: _('Number of cards in your hand'),
            },
            O005: {
                name: _('Light Goal'),
                condition: _('Gain 4 or more Psychic Light {light} in a single turn'),
                x: _('Number of Psychic Light {light} you hold'),
            },
            O006: {
                name: _('Analysis Floor Goal'),
                condition: _('Perform Psychoanalysis 2 or more times in a single turn'),
                x: _('Your Mind Track tier'),
            },
            O007: {
                name: _('Multi Engine Goal'),
                condition: _('Your constructed cards include all of Wisdom {wisdom}, Will {will}, Emotion {emotion}, and Wild {wild} keys'),
                x: _('Number of sets of Wisdom {wisdom}, Will {will}, and Emotion {emotion} keys'),
            },
        },

        // ---- 想起効果 13 種（002 §3.4。009 §8.5）。「Recall:」の後に出す ----
        recall: {
            DRAW_1: _('Draw 1 Memory card from the deck {draw}'),
            OPEN_DRAW_1: _('Take 1 open card into your hand {open}'),
            LIGHT_1: _('Gain 1 Psychic Light {light}'),
            NEXT_COMPOSE_DISCOUNT_1: _('Your next Construction action this turn costs 1 less energy {energy}'),
            DISCARD_FOR_LIGHT: _('Discard any number of Memory cards from your hand. Gain that much Psychic Light {light}'),
            SWAP_HAND_FIELD: _('Swap any number of Memory cards in your hand with open cards'),
            SWAP_SAME_COST: _('You may swap a constructed card with a Memory card of the same energy cost {energy} in your hand'),
            ALT_ANALYSIS: _('Instead of your floor action {floor}, you may take a Psychoanalysis action (turn player only)'),
            ALT_COMPOSE: _('Instead of your floor action {floor}, you may take a Construction action (turn player only)'),
            ALT_ANALYSIS_DISCOUNT_1: _('Instead of your floor action {floor}, you may take a Psychoanalysis action with cost -1 (turn player only)'),
            ALT_COMPOSE_DISCOUNT_1: _('Instead of your floor action {floor}, you may take a Construction action with energy {energy} -1 (turn player only)'),
            MOVE_NEXT_LV1: _('You may move to the next level 1 room. If you do, take 1 open card {open} and perform that room\'s floor action'),
            MOVE_NEXT_LV2: _('You may move to the next level 2 room. If you do, take 1 open card {open} and perform that room\'s floor action'),
        },

        // ---- 効果の種類（002 §3.3。009 §8.4） ----
        kinds: {
            TURN_START: _('Turn start'),
            ACTIVATED: _('Activated'),
            CONSTANT: _('Constant'),
            INSTANT: _('Instant'),
            HELD: _('Held'),
        },
    };
}
