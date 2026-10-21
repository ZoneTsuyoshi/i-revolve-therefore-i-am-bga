<?php

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * IRevolveThereforeIAm implementation : © だんじり協奏曲 (Danjiri Concerto) https://x.com/danjiriconcerto
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * 『我廻る、故に我あり』のメインクラス（薄い入口。仕様書 003 §4.2）。独自メソッドの名前は ir で始める（仕様書 003 §4.3）。
 * フレームワークが要求するメソッド、依存の組み立て、通知送信の共通ヘルパー、debug_ メソッドだけを持つ。
 * 状態の中身は States/、ルール計算は Flow/、DB は Data/、セットアップは Setup/。
 */

declare(strict_types=1);

namespace Bga\Games\IRevolveThereforeIAm;

use Bga\Games\IRevolveThereforeIAm\Data\BgaDatabase;
use Bga\Games\IRevolveThereforeIAm\Data\Database;
use Bga\Games\IRevolveThereforeIAm\Data\EffectStack;
use Bga\Games\IRevolveThereforeIAm\Data\EgoRepository;
use Bga\Games\IRevolveThereforeIAm\Data\GamePhase;
use Bga\Games\IRevolveThereforeIAm\Data\GlobalValues;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryCardRepository;
use Bga\Games\IRevolveThereforeIAm\Data\MemoryLocation;
use Bga\Games\IRevolveThereforeIAm\Data\ObjectiveRepository;
use Bga\Games\IRevolveThereforeIAm\Data\PlayerValues;
use Bga\Games\IRevolveThereforeIAm\Data\PublicView;
use Bga\Games\IRevolveThereforeIAm\Data\RoomRepository;
use Bga\Games\IRevolveThereforeIAm\Data\SpiritRepository;
use Bga\Games\IRevolveThereforeIAm\Data\StoredGameView;
use Bga\Games\IRevolveThereforeIAm\Effects\Catalog\CatalogSpecSource;
use Bga\Games\IRevolveThereforeIAm\Effects\Condition\ConditionEvaluator;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameEvent;
use Bga\Games\IRevolveThereforeIAm\Effects\Context\GameView;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\ActiveSpecs;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\KeyViewQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\LightScheduleQuery;
use Bga\Games\IRevolveThereforeIAm\Effects\Query\MovePaymentQuery;
use Bga\Games\IRevolveThereforeIAm\Flow\GameProgress;
use Bga\Games\IRevolveThereforeIAm\Flow\HandLimit;
use Bga\Games\IRevolveThereforeIAm\Flow\MoveBoardReader;
use Bga\Games\IRevolveThereforeIAm\Flow\MoveExecutor;
use Bga\Games\IRevolveThereforeIAm\Flow\MovePhase;
use Bga\Games\IRevolveThereforeIAm\Flow\MovePlanner;
use Bga\Games\IRevolveThereforeIAm\Flow\Notice;
use Bga\Games\IRevolveThereforeIAm\Flow\Notification;
use Bga\Games\IRevolveThereforeIAm\Flow\StateId;
use Bga\Games\IRevolveThereforeIAm\Flow\TurnKit;
use Bga\Games\IRevolveThereforeIAm\Setup\GameSetup;
use Bga\Games\IRevolveThereforeIAm\Setup\InitialEgoDraft;
use Bga\Games\IRevolveThereforeIAm\Setup\PersonaPlacement;
use Bga\Games\IRevolveThereforeIAm\Setup\SetupPlanner;
use Bga\Games\IRevolveThereforeIAm\States\SetupEgoDraftNext;

class Game extends \Bga\GameFramework\Table
{
    /** ゲームオプション 100「回廊の配置」（仕様書 003 §7.2、007 §5.1）。 */
    public const OPTION_FLOOR_LAYOUT = 100;

    private ?Database $irDb = null;

    private ?GlobalValues $irGlobalValues = null;

    private ?TurnKit $irTurnKit = null;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 進行度（0〜99。仕様書 006 §11）。82 NextTurn に入るたびに呼ばれる。最大値を保って単調増加にする。
     */
    public function getGameProgression(): int
    {
        $view = $this->irPublicView();
        $rows = $view->results($this->irPlayerValues()->playerIds());
        $globals = $this->irGlobals();
        $memory = $this->irMemoryCards();

        $progress = GameProgress::calculate(
            maxTier: $rows === [] ? 0 : max(array_column($rows, 'tier')),
            maxScore: $rows === [] ? 0 : max(array_column($rows, 'total')),
            deck1Left: $memory->count(MemoryLocation::Deck1),
            deck1Initial: $globals->deckInitial(1),
            deck2Left: $memory->count(MemoryLocation::Deck2),
            deck2Initial: $globals->deckInitial(2),
            highWater: $globals->progressHighWater(),
        );
        $globals->setProgressHighWater($progress);

        return $progress;
    }

    /**
     * Migrate database.
     *
     * You don't have to care about this until your game has been published on BGA. Once your game is on BGA, this
     * method is called everytime the system detects a game running with your old database scheme. In this case, if you
     * change your database scheme, you just have to apply the needed changes in order to update the game database and
     * allow the game to continue to run with your new version.
     *
     * @param int $from_version
     * @return void
     */
    public function upgradeTableDb($from_version)
    {
//       if ($from_version <= 1404301345)
//       {
//            // ! important ! Use `DBPREFIX_<table_name>` for all tables
//
//            $sql = "ALTER TABLE `DBPREFIX_xxxxxxx` ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
//
//       if ($from_version <= 1405061421)
//       {
//            // ! important ! Use `DBPREFIX_<table_name>` for all tables
//
//            $sql = "CREATE TABLE `DBPREFIX_xxxxxxx` ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
    }

    /*
     * 現在の盤面を閲覧者の視点で返す（仕様書 007 §8）。ページの表示・再読み込みのたびに呼ばれる。
     * 非公開情報（他人の手札、部屋の裏向きのカードの種類、山札の中身、強化自我の山）は含めない。PublicView が出し分ける。
     */
    protected function getAllDatas(int $currentPlayerId): array
    {
        // キー players はフレームワーク用に予約されている。独自の値は PublicView が playerData に入れる
        $result = [];
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` AS `id`, `player_score` AS `score` FROM `player`"
        );

        return $result + $this->irPublicView()->allDatas($currentPlayerId);
    }

    /**
     * 新しいゲームの開始時に一度だけ呼ばれる。ルールどおりに初期状態を作る（仕様書 001 §4、007 §13）。
     */
    protected function setupNewGame($players, $options = [])
    {
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        $query_values = [];
        foreach ($players as $player_id => $player) {
            $query_values[] = vsprintf("(%s, '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                addslashes($player["player_name"]),
            ]);
        }

        static::DbQuery(
            sprintf(
                "INSERT INTO `player` (`player_id`, `player_color`, `player_name`) VALUES %s",
                implode(",", $query_values)
            )
        );

        $this->reattributeColorsBasedOnPreferences($players, $gameinfos["player_colors"]);
        $this->reloadPlayersBasicInfos();

        // 手番順は席順（player_no の昇順。Q-18、007 §7.1 T-8）。スタートプレイヤーは 25 SetupFinish で 1 番手にする
        $layout = $this->bga->tableOptions->get(self::OPTION_FLOOR_LAYOUT) ?? SetupPlanner::LAYOUT_FIXED;
        $this->irGameSetup()->run($this->irPlayerValues()->playerIds(), $layout);

        // TODO(I-11): 統計の初期化（stats.jsonc。007 §14）

        // 最初の状態は GAME 状態の初期自我のドラフト（006 §3.2）
        return SetupEgoDraftNext::class;
    }

    /**
     * デバッグ用: 指定した状態へ飛ぶ（Studio の Debug ボタンから）。
     */
    public function debug_goToState(int $state = StateId::TURN_START)
    {
        $this->gamestate->jumpToState($state);
    }

    /**
     * DB 操作の口（Data/ のリポジトリに渡す）。
     */
    public function irDatabase(): Database
    {
        return $this->irDb ??= new BgaDatabase();
    }

    /**
     * ゲーム全体の値（仕様書 007 §7.2）。
     */
    public function irGlobals(): GlobalValues
    {
        return $this->irGlobalValues ??= new GlobalValues($this->bga->globals);
    }

    /**
     * player テーブルの追加列（仕様書 007 §7.1）。
     */
    public function irPlayerValues(): PlayerValues
    {
        return new PlayerValues($this->irDatabase());
    }

    public function irMemoryCards(): MemoryCardRepository
    {
        return new MemoryCardRepository($this->irDatabase());
    }

    public function irRooms(): RoomRepository
    {
        return new RoomRepository($this->irDatabase());
    }

    public function irSpirits(): SpiritRepository
    {
        return new SpiritRepository($this->irDatabase());
    }

    public function irEgos(): EgoRepository
    {
        return new EgoRepository($this->irDatabase());
    }

    public function irObjectives(): ObjectiveRepository
    {
        return new ObjectiveRepository($this->irDatabase());
    }

    /**
     * 効果解決スタック（仕様書 007 §6）。
     */
    public function irEffectStack(): EffectStack
    {
        return new EffectStack($this->irDatabase());
    }

    /**
     * getAllDatas と getArgs の公開部分を作る唯一の入口（仕様書 007 §8）。
     */
    public function irPublicView(): PublicView
    {
        return new PublicView(
            $this->irMemoryCards(),
            $this->irRooms(),
            $this->irSpirits(),
            $this->irEgos(),
            $this->irObjectives(),
            $this->irPlayerValues(),
            $this->irGlobals(),
        );
    }

    /**
     * 席順（player_no の昇順 = 手番順）のプレイヤー ID。
     *
     * @return list<int>
     */
    public function irSeatOrder(): array
    {
        return $this->irPlayerValues()->playerIds();
    }

    /**
     * 初期自我の秘密ドラフト（状態 20〜22）。zombie の選択だけ乱数（003 §4.4）。
     */
    public function irEgoDraft(): InitialEgoDraft
    {
        return new InitialEgoDraft(
            $this->irEgos(),
            $this->irGlobals(),
            static fn(int $min, int $max): int => random_int($min, $max),
        );
    }

    /**
     * 人格の配置（状態 23・24）。
     */
    public function irPersonaPlacement(): PersonaPlacement
    {
        return new PersonaPlacement($this->irPlayerValues(), $this->irRooms(), $this->irGlobals());
    }

    /**
     * 効果エンジンが状態を読む口の本番の実装（DB の読み取り。仕様書 008 §11.1）。
     */
    public function irGameView(): GameView
    {
        return new StoredGameView(
            $this->irMemoryCards(),
            $this->irEgos(),
            $this->irPlayerValues(),
            $this->irGlobals(),
            $this->irRooms(),
            $this->irSpirits(),
        );
    }

    /**
     * 手札上限（仕様書 006 §8）。
     */
    public function irHandLimit(): HandLimit
    {
        return new HandLimit($this->irMemoryCards(), $this->irPlayerValues(), $this->irEffectStack());
    }

    /**
     * 移動フェイズ（状態 31。仕様書 006 §5.1、008 §6.2）。
     */
    public function irMovePhase(): MovePhase
    {
        $active = new ActiveSpecs($this->irGameView(), new CatalogSpecSource(), new ConditionEvaluator($this->irGameView()));
        $light = new LightScheduleQuery($active);
        $payments = new MovePaymentQuery($active, new KeyViewQuery($active), $light);

        return new MovePhase(
            new MoveBoardReader($this->irMemoryCards(), $this->irRooms(), $this->irPlayerValues(), $this->irGlobals()),
            new MovePlanner($payments, $light),
            new MoveExecutor($this->irMemoryCards(), $this->irPlayerValues(), $this->irGlobals(), new CatalogSpecSource()),
            $this->irHandLimit(),
            $payments,
            $light,
        );
    }

    /**
     * 想起・床アクション・自己構成・精神分析・効果解決・フリーアクションの部品（70・71・40・50・51・60・61・62 が共有する）。
     * 1 回のリクエストの中では同じ組み立てを使う（通知が溜まる順序を保つため）。
     */
    public function irTurn(): TurnKit
    {
        return $this->irTurnKit ??= new TurnKit(
            $this->irMemoryCards(),
            $this->irRooms(),
            $this->irSpirits(),
            $this->irEgos(),
            $this->irPlayerValues(),
            $this->irGlobals(),
            $this->irEffectStack(),
            $this->irGameView(),
            new CatalogSpecSource(),
            fn(int $playerId): string => $this->getPlayerNameById($playerId),
            fn() => $this->irCheckVictory(),
        );
    }

    /**
     * 効果解決スタックにイベント（EV-01〜EV-08。仕様書 008 §4.1）の誘発を積む口。
     * 自己構成済みカード・自我カードのうちこのイベントに反応する TRIGGER を集め、条件を評価して SPEC_RUN の frame を
     * 解決スタックの先頭に積む（複数で衝突するときは ORDER_EFFECTS。008 §6.3）。
     *
     * @param list<GameEvent> $events
     */
    public function irEmitEvents(array $events): void
    {
        $this->irTurn()->triggers->emit($events);
    }

    /**
     * 勝利判定（断片の結集 = 自己構成スコア 20 以上。008 §6.8）。スコアが変わりうる操作の直後に呼ばれる。成立したら end_reason を書く。
     */
    public function irCheckVictory(): void
    {
        // TODO(I-08・I-10): WinChecker と ScoreCalculator（目標カードの得点を含む）で全プレイヤーを評価し、FRAGMENTS を書く
    }

    /**
     * フェイズを切り替える。ログに出すフェイズ（移動・想起・床アクション・自己構成・クリーンアップ）は、変わったときだけ通知する。
     * 70 を経由して同じ状態に戻ってきたとき（60 など）に何度も通知しないため。
     */
    public function irEnterPhase(GamePhase $phase): void
    {
        $globals = $this->irGlobals();
        $changed = $globals->phase() !== $phase;
        $globals->setPhase($phase);
        if ($changed && Notification::isLoggedPhase($phase)) {
            $this->irSend(Notification::phaseStarted($phase));
        }
    }

    /**
     * 通知を全員（と観戦者）へ送る。
     */
    public function irSend(Notice ...$notices): void
    {
        foreach ($notices as $notice) {
            $this->bga->notify->all($notice->type, $notice->message, $notice->args);
        }
    }

    /**
     * セットアップの組み立て（仕様書 007 §13）。乱数は random_int（仕様書 003 §4.4）。
     */
    private function irGameSetup(): GameSetup
    {
        return new GameSetup(
            new SetupPlanner(static fn(int $min, int $max): int => random_int($min, $max)),
            $this->irMemoryCards(),
            $this->irRooms(),
            $this->irSpirits(),
            $this->irEgos(),
            $this->irObjectives(),
            $this->irPlayerValues(),
            $this->irGlobals(),
        );
    }
}
