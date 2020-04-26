<?php

namespace pawarenessc\slot;

//use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

use pocketmine\Player;

use pocketmine\scheduler\Task;

use pocketmine\plugin\PluginBase;

use pocketmine\Server;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;

use pocketmine\math\Vector3;

use pocketmine\level\particle\FloatingTextParticle;

use pocketmine\event\server\DataPacketReceiveEvent;

use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\ActorEventPacket;

use MixCoinSystem\MixCoinSystem;
use metowa1227\moneysystem\api\core\API;

class Main extends pluginBase implements Listener
{
    const SLOT = 0;
    const SLOT_AUTO = 1;
    const SLOT_BACK = 2;

    public $slot = array();
    public $slota = array();
    public $slotb = array();
    public $slot_karma = array();
    public $ftp;
    public $godp;

    public $confirm_1, $confirm_2, $confirm_3;

    /* @var Config */
    public $config;

    /* @var Config */
    public $xyz;

    /* @var Config */
    public $info;

    /* @var Config */
    public $stop;


    public function onEnable()
    {

        $this->confirm_1 = mt_rand(1, 9);
        $this->confirm_2 = mt_rand(1, 9);
        $this->confirm_3 = mt_rand(1, 9);

        $this->godp = 0;

        $this->getLogger()->info("=========================");
        $this->getLogger()->info("SLOTを読み込みました");
        $this->getLogger()->info("制作者: PawarenessC");
        $this->getLogger()->info("ライセンス: NYSL Version 0.9982");
        $this->getLogger()->info("http://www.kmonos.net/nysl/");
        $this->getLogger()->info("バージョン:{$this->getDescription()->getVersion()}");
        $this->getLogger()->info("スロットの確定番号は {$this->confirm_1}{$this->confirm_2}{$this->confirm_3} です");
        $this->getLogger()->info("=========================");

        $this->config = new Config($this->getDataFolder()."Setup.yml", Config::YAML,
            [
                "プラグイン" => "EconomyAPI",
                "値段" => 100,
                "ジャックポット以外の賞金" => 1000,
                "初期ジャックポット" => 10000,
                "ジャックポット" => 10000,
                "KarmaJudge" => 1000,
                "LastPlayer" => "NO NAME",
                "LastJackPot" => 0,
                "HighPlayer" => "NO NAME",
                "HighJackPot" => 100,
                "UpdateInterval" => 1,
            ]);
        $this->xyz = new Config($this->getDataFolder()."xyz.yml", Config::YAML,
            [
                "world" => "world",
                "x" => 281,
                "y" => 4,
                "z" => 284,
            ]);
        $this->getServer()->loadLevel($this->xyz->get("world"));
        $this->info = new Config($this->getDataFolder()."info.yml", Config::YAML,
            [
                "説明" => "改行をするときは{br}です",
                "title" => "=-=-=現在のスロットの情報=-=-={br}",
                "text" => "§b現在のジャックポット §6{jackpot}§f円{br}§b当選確定番号 §l§f{kakutei}§r§f番{br}§b最後のジャックポット当選者 §l§6{lastname} §f{lastjackpot}円{br}§a最高ジャックポット当選者 §l{highname} §d{highjp}",
            ]);
        $this->stop = new Config($this->getDataFolder() . "stop.yml", Config::YAML);

        $this->system = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $xyz = $this->xyz;
        $x = $xyz->get("x");
        $y = $xyz->get("y");
        $z = $xyz->get("z");
        $level_name = $xyz->get("world");
        $level = $this->getServer()->getLevelByName($level_name);
        $pos = new Vector3($x, $y, $z, $level);
        $this->ftp = new FloatingTextParticle($pos,"DEBUG","DEBUG and DEBUG");
        $level->addParticle($this->ftp);

        $this->slotinfo();

        $int = $this->config->get("UpdateInterval") * 20;
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "slotinfo"]), $int);
    }

    public function onJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();
        $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "JoinType"], [$name]), 20);
        $this->slot[$name] = false;
        $this->slot_karma[$name] = 0;

        if (!$this->stop->exists($name)) {
            $this->stop->set($name, 1000);
            $this->stop->save();
        }
    }

    public function JoinType($name)
    {
        $this->slota[$name] = false;
        $this->slot[$name] = false;
        $this->slotb[$name] = false;
    }

    public function onQuit(PlayerQuitEvent $event)
    {
        $name = $event->getPlayer()->getName();
        $this->slot_karma[$name] = 0;
        $this->JoinType($name);
    }

    /*public function onMove(PlayerMoveEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();
        $player->sendTip("YourKarma: {$this->slot_karma[$name]}");
    }*/

    ///////////////////////////////////////////////////////////////////////////////
    // slot  その名前のプレイヤーのスロットが行われているかどうか
    // slota その名前のプレイヤーの自動抽選が有効かどうか
    // slotb その名前のプレイヤーがバッググラウンドのスロットを行っているかどうか
    ///////////////////////////////////////////////////////////////////////////////

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        $name = $sender->getName();
        switch ($label) {
            case "slot":
                $money = $this->getMoney($sender);
                $price = $this->config->get("値段");
                if ($price > $money) {
                    $sender->sendMessage("§b§lSLOT>> §cお金が足りません！ スロットを一回回すには§f{$price}円§c必要です");
                    break;
                } elseif ($this->slot[$name]) {
                    $sender->sendMessage("§b§lSLOT>> §cスロット中です。");
                } else {
                    $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot_1"], [$sender, self::SLOT]), 30);
                    $sender->addTitle("§f[§e?§f]-[§e?§f]-[§e?§f]", "§6抽選を開始します...");
                    $this->oto($sender, "pop");
                    $this->slot[$name] = true;
                }
                break;

            case "slotui":
                if($sender instanceof Player) {
                    if ($sender->isOp()) {
                        $this->SlotUIa($sender);
                    } else {
                        $this->SlotUIg($sender);
                    }
                    break;
                }else{
                    $sender->sendMessage("§b§lSLOT>> §cゲームでこのコマンドを打ってください");
                    break;
                }
        }
        return true;
    }

    public function slot_1(Player $p, int $type)
    {
        $name = $p->getName();
        switch ($type) {
            case self::SLOT:
                $s1 = rand(1, 9);
                $bool = false;
                if($this->slot_karma[$name] > $this->config->get("KarmaJudge")) {
                    $s1 = rand(6,8);
                    $bool = true;
                }
                $this->sendslot($p, $s1, $bool);
                //Server::getInstance()->broadcastMessage("{$name}は{$s1}");
                $this->oto($p, "pop");
                $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot_2"], [$p, self::SLOT, $s1]), 30);
                break;
            case self::SLOT_AUTO:
                $s1 = rand(1, 12);
                if ($s1 > 9) $s1 = 1;
                $bool = false;
                if($this->slot_karma[$name] > $this->config->get("KarmaJudge")) {
                    $s1 = rand(5,9);
                    $bool = true;
                }
                $this->sendslot($p, $s1, $bool);
                //Server::getInstance()->broadcastMessage("{$name}は{$s1}");
                $this->oto($p, "pop");
                $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot_2"], [$p, self::SLOT_AUTO, $s1]), 10);
                break;
        }
    }

    public function slot_2(Player $p, int $type, int $s1)
    {
        $name = $p->getName();
        switch ($type) {
            case self::SLOT:
                $s2 = rand(1,9);
                $bool = false;
                if($this->slot_karma[$name] > $this->config->get("KarmaJudge")) {
                    $s2 = rand(6,8);
                    $bool = true;
                }
                $this->sendslot($p, $s1, $bool, $s2);
                //Server::getInstance()->broadcastMessage("{$name}は{$s2}");
                $this->oto($p, "pop");
                $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot_3"], [$p, self::SLOT, $s1, $s2]), 30);
                break;
            case self::SLOT_AUTO:
                $s2 = rand(1, 12);
                if ($s2 > 9) $s2 = 2;
                $bool = false;
                if($this->slot_karma[$name] > $this->config->get("KarmaJudge")) {
                    $s2 = rand(5,9);
                    $bool = true;
                }
                $this->sendslot($p, $s1, $bool, $s2);
                //Server::getInstance()->broadcastMessage("{$name}は{$s2}");
                $this->oto($p, "pop");
                $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot_3"], [$p, self::SLOT_AUTO, $s1, $s2]), 10);
                break;
        }
    }

    public function slot_3(Player $p, int $type, int $s1, $s2)
    {
        $name = $p->getName();
        switch ($type) {
            case self::SLOT:
                $s3 = rand(1, 9);
                $bool = false;
                if($this->slot_karma[$name] > $this->config->get("KarmaJudge")) {
                    $s3 = rand(6,8);
                    $bool = true;
                }
                $this->sendslot($p, $s1, $bool, $s2, $s3);
                //Server::getInstance()->broadcastMessage("{$name}は{$s3}");
                $this->oto($p, "pop");
                $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "end"], [$p, $s1, $s2, $s3]), 20);
                break;
            case self::SLOT_AUTO:
                $s3 = rand(1, 12);
                if ($s3 > 9) $s3 = 3;
                $bool = false;
                if($this->slot_karma[$name] > $this->config->get("KarmaJudge")) {
                    $s3 = rand(5,9);
                    $bool = true;
                }
                $this->sendslot($p, $s1, $bool, $s2, $s3);
                //Server::getInstance()->broadcastMessage("{$name}は{$s3}");
                $this->oto($p, "pop");
                $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "end"], [$p, $s1, $s2, $s3]), 10);
                break;
        }
    }

    public function end(Player $p, $s1, $s2, $s3){
        $money = $this->config->get("値段");
        $name = $p->getName();
        $this->cutMoney($p, $money);
        $this->slot[$name] = false;
        $confirm = mt_rand(1, 999);
        $confirm1 = $this->confirm_1;
        $confirm2 = $this->confirm_2;
        $confirm3 = $this->confirm_3;

        if ($s1 == $s2 and $s1 == $s3) {
            switch ($s1) {
                case 1:
                case 2:
                case 3:
                case 4:
                case 5:
                case 6:
                case 8:
                case 9:

                    $money = $this->config->get("ジャックポット以外の賞金");
                    $this->addMoney($money, $p);
                    $this->oto($p, "good");

                    $p->sendMessage("§b§lSLOT>> §6ゾロ目おめでとうございます！");
                    $p->sendMessage("§b§lSLOT>> §6{$money}円§a手に入れた！");
                    $this->slot_karma[$name] = 0;
                    if ($this->slota[$name]) {
                        $p->sendMessage("§b§lSLOT>> §f当たったので再抽選を停止します");
                    }

                    $this->config->set("ジャックポット", $this->config->get("ジャックポット") + $this->config->get("値段"));
                    $this->config->save();
                    break;

                case 7:
                    $money = $this->config->get("ジャックポット");
                    $this->addMoney($money, $p);
                    $this->oto($p, "good");

                    $p->sendMessage("§b§lSLOT>> §6ジャックポット§bおめでとうございます！");
                    $p->sendMessage("§b§lSLOT>> §6{$money}円手に入れた！");
                    $this->slot_karma[$name] = 0;
                    if ($this->slota[$name]) {
                        $p->sendMessage("§b§lSLOT>> §f当たったので再抽選を停止します");
                    }

                    $this->getServer()->broadcastMessage("§lSLOT>> §a{$p->getName()}さんがジャックポット {$money}円を手に入れました！");
                    $this->getServer()->broadcastMessage("§lSLOT>> ジャックポットが{$this->config->get("初期ジャックポット")}に戻りました");

                    $this->config->set("LastPlayer", $name);
                    $this->config->save();
                    $this->config->set("LastJackPot", $this->config->get("ジャックポット"));
                    $this->config->save();

                    $this->scan($name, $this->config->get("ジャックポット"));

                    $this->config->set("ジャックポット", $this->config->get("初期ジャックポット"));
                    $this->config->save();


                    $inv = $p->getInventory();
                    $i = $inv->getItemInHand();
                    $inv->setItemInHand(Item::get(ItemIds::TOTEM));
                    $p->broadcastEntityEvent(ActorEventPacket::CONSUME_TOTEM);
                    $inv->setItemInHand($i);/*冬月さんありがとうございました！*/


                    $pk = new LevelEventPacket();
                    $pk->evid = LevelEventPacket:: EVENT_SOUND_TOTEM;
                    $pk->data = 0;
                    $pk->position = $p->asVector3();
                    $p->dataPacket($pk);
                    break;
            }

        }elseif ($s1 == 7 and $s2 == 7){
            $rand = mt_rand(1,3);
            if ($rand == 1){
                $p->addTitle("§l§4Challenge!!","§6奇跡を信じましょう...");
                $this->oto($p,"bad");
                $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "challenge_slot_1"], [$p]), 80);
            }else{
                $this->sendslot($p, $s1, false, $s2, $s3);
                $this->config->set("ジャックポット", $this->config->get("ジャックポット") + $this->config->get("値段"));
                $this->config->save();
                $p->sendMessage("§b§lSLOT>> §c残念...ハズレです...");
                $p->sendMessage("§b§lSLOT>> §c現在のジャックポット§e{$this->config->get("ジャックポット")}円");
                //Server::getInstance()->broadcastMessage("普通ハズレ");
                $this->slot_karma[$name]++;
                $this->oto($p, "bad");
                $money = $this->config->get("値段");
                if ($this->slota[$name]) {
                    if ($this->getMoney($p) > $money && $this->canslot($p)) {
                        $p->sendMessage("§l§bSLOT>> §a外れたので再抽選を行います");
                        $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot_1"], [$p, self::SLOT_AUTO]), 20);
                        $p->addTitle("§f[§e?§f]-[§e?§f]-[§e?§f]", "§6抽選を開始します...");
                        $this->oto($p, "pop");
                        $this->slot[$name] = true;
                    } else {
                        $p->sendMessage("§l§bSLOT>> §c所持金が足りないもしくは設定した金額まで所持金が減ったので再抽選を停止しました");
                        $this->JoinType($name);
                    }
                }
            }
        }elseif (mt_rand(1,8192) == 1) {
            $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "gPerformance1"], [$p]), 60);
            return true;
        } elseif ($s1 == $confirm1 && $s2 == $confirm2 && $s3 == $confirm3 or $confirm == 1) { //確定ってやつ？ 起動時に3つの数字を乱数生成し、抽選番号がそれと合致するか、1/999の確率で確定を起こす
            $p->sendMessage("§b§lSLOT>> §c残念...ハズレです...");
            $this->JoinType($name); //スロットを全て停止
            $this->slot_karma[$name] = 0;
            //Server::getInstance()->broadcastMessage("確定ハズレ");
            $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "confirm_1_slot"], [$p]), 50);

            $pk = new LevelEventPacket();
            $pk->evid = LevelEventPacket::EVENT_GUARDIAN_CURSE;
            $pk->data = 0;
            $pk->position = $p->asVector3();
            $p->dataPacket($pk);

        } else {

            $this->config->set("ジャックポット", $this->config->get("ジャックポット") + $this->config->get("値段"));
            $this->config->save();
            $p->sendMessage("§b§lSLOT>> §c残念...ハズレです...");
            $p->sendMessage("§b§lSLOT>> §c現在のジャックポット§e{$this->config->get("ジャックポット")}円");
            //Server::getInstance()->broadcastMessage("普通ハズレ");
            $this->slot_karma[$name]++;
            $this->oto($p, "bad");
            $money = $this->config->get("値段");
            if ($this->slota[$name]) {
                if ($this->getMoney($p) > $money && $this->canslot($p)) {
                    $p->sendMessage("§l§bSLOT>> §a外れたので再抽選を行います");
                    $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot_1"], [$p, self::SLOT_AUTO]), 20);
                    $p->addTitle("§f[§e?§f]-[§e?§f]-[§e?§f]", "§6抽選を開始します...");
                    $this->oto($p, "pop");
                    $this->slot[$name] = true;
                } else {
                    $p->sendMessage("§l§bSLOT>> §c所持金が足りない もしくは 設定した金額まで所持金が減った ので再抽選を停止しました");
                    $this->JoinType($name);
                }
            }
        }
    }

    public function challenge_slot_1(Player $player){
        $player->addTitle("§f[§67§f]-[§67§f]-[§c§k9§r§f]","Challenge...",20,60,20);
        $this->oto($player, "pop");
        $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "challenge_slot_2"], [$player]), 80);
    }

    public function challenge_slot_2(Player $player){
        $rand = mt_rand(1,10);
        $name = $player->getName();
        if ($rand == 1){ //チャレンジ成功!
            $player->addTitle("§f[§67§f]-[§67§f]-[§67§f]");
            $this->oto($player, "ex");
            $money = $this->config->get("ジャックポット");
            $this->addMoney($money, $player);
            $this->oto($player, "good");
            if ($this->slota[$name]) $player->sendMessage("§b§lSLOT>> §f当たったので再抽選を停止します");

            $player->sendMessage("§b§lSLOT>> §6ジャックポット§bおめでとうございます！");
            $player->sendMessage("§b§lSLOT>> §6{$money}円手に入れた！");
            $this->slot_karma[$name] = 0;

            $this->getServer()->broadcastMessage("§lSLOT>> §a{$player->getName()}さんがジャックポット {$money}円を手に入れました！");
            $this->getServer()->broadcastMessage("§lSLOT>> ジャックポットが{$this->config->get("初期ジャックポット")}に戻りました");

            $inv = $player->getInventory();
            $i = $inv->getItemInHand();
            $inv->setItemInHand(Item::get(ItemIds::TOTEM));
            $player->broadcastEntityEvent(ActorEventPacket::CONSUME_TOTEM);
            $inv->setItemInHand($i);/*冬月さんありがとうございました！*/

            $this->config->set("LastPlayer", $name);
            $this->config->set("LastJackPot", $this->config->get("ジャックポット"));
            $this->scan($name, $this->config->get("ジャックポット"));
            $this->config->set("ジャックポット", $this->config->get("初期ジャックポット"));
            $this->config->save();
        }else{
            $player->addTitle("§f[§67§f]-[§67§f]-[§c8§f]");
            $this->config->set("ジャックポット", $this->config->get("ジャックポット") + $this->config->get("値段"));
            $this->config->save();
            $player->sendMessage("§b§lSLOT>> §c残念...ハズレです...");
            $player->sendMessage("§b§lSLOT>> §c現在のジャックポット§e{$this->config->get("ジャックポット")}円");
            //Server::getInstance()->broadcastMessage("普通ハズレ");
            $this->slot_karma[$name]++;
            $this->oto($player, "bad");
            $money = $this->config->get("値段");
            if ($this->slota[$name]) {
                if ($this->getMoney($player) > $money && $this->canslot($player)) {
                    $player->sendMessage("§l§bSLOT>> §a外れたので再抽選を行います");
                    $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot_1"], [$player, self::SLOT_AUTO]), 20);
                    $player->addTitle("§f[§e?§f]-[§e?§f]-[§e?§f]", "§6抽選を開始します...");
                    $this->oto($player, "pop");
                    $this->slot[$name] = true;
                } else {
                    $player->sendMessage("§l§bSLOT>> §c所持金が足りないもしくは設定した金額まで所持金が減ったので再抽選を停止しました");
                    $this->JoinType($name);
                }
            }
        }
    }

    public function slot_back(Player $player)
    {
        $s1 = rand(1, 18);
        $s2 = rand(1, 18);
        $s3 = rand(1, 18);

        if ($s1 > 9) $s1 = 1;
        if ($s2 > 9) $s2 = 2;
        if ($s3 > 9) $s3 = 3;
        $name = $player->getName();

        $bool = false;
        if($this->slot_karma[$name] > $this->config->get("KarmaJudge")){
            $s1 = rand(1,9);
            $s2 = rand(1,9);
            $s3 = rand(1,9);
            $bool = true;
        }

        $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "bend"], [$player, $s1, $s2, $s3, $bool]), 20);
    }

    public function bend(Player $p, $s1, $s2, $s3, $bool){
        $money = $this->config->get("値段");
        $name = $p->getName();
        $this->cutMoney($p, $money);
        $this->slot[$name] = false;
        if ($s1 == $s2 && $s1 == $s3) {
            switch ($s1) {
                case 1:
                case 2:
                case 3:
                case 4:
                case 5:
                case 6:
                case 8:
                case 9:

                    $money = $this->config->get("ジャックポット以外の賞金");
                    $this->addMoney($money, $p);
                    $this->oto($p, "good");

                    $p->sendMessage("§b§lSLOT>> §6ゾロ目おめでとうございます！ §f(§e{$s1}-{$s2}-{$s3}§f)");
                    $p->sendMessage("§b§lSLOT>> §6{$money}円§a手に入れた！");
                    $this->slot_karma[$name] = 0;

                    $this->config->set("ジャックポット", $this->config->get("ジャックポット") + $this->config->get("値段"));
                    $this->config->save();

                    $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot_back"], [$p]), 5);
                    $this->slot[$name] = true;
                    break;

                case 7:
                    $money = $this->config->get("ジャックポット");
                    $this->addMoney($money, $p);
                    $this->oto($p, "good");
                    $this->slotb[$name] = false;
                    $this->slot_karma[$name] = 0;

                    $p->sendMessage("§b§lSLOT>> §6ジャックポット§bおめでとうございます！");
                    $p->sendMessage("§b§lSLOT>> §6{$money}円手に入れた！");
                    $p->sendMessage("§b§lSLOT>> §f当たったのでバッググラウンドスロットを停止します");


                    $this->getServer()->broadcastMessage("§lSLOT>> §a{$p->getName()}さんがジャックポット {$money}円を手に入れました！");
                    $this->getServer()->broadcastMessage("§lSLOT>> ジャックポットが{$this->config->get("初期ジャックポット")}に戻りました");

                    $this->config->set("LastPlayer", $name);
                    $this->config->set("LastJackPot", $this->config->get("ジャックポット"));
                    $this->config->save();

                    $this->scan($name, $this->config->get("ジャックポット"));

                    $this->config->set("ジャックポット", $this->config->get("初期ジャックポット"));
                    $this->config->save();
                    break;
            }
        } else {
            $this->config->set("ジャックポット", $this->config->get("ジャックポット") + $this->config->get("値段"));
            $this->config->save();
            $p->sendPopup("§b§lSLOT>> §cハズレ! §e{$s1}-{$s2}-{$s3} ジャックポット: {$this->config->get("ジャックポット")}円\n".
                                  "§b§lSLOT>> 当選確率上昇中です...");
            $this->slot_karma[$name]++;
            $money = $this->config->get("値段");
            if ($this->slotb[$name]) {
                if ($this->getMoney($p) > $money && $this->canslot($p)) {
                    $this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot_back"], [$p]), 10);
                    $this->slot[$name] = true;
                } else {
                    $p->sendMessage("§l§bSLOT>> §c所持金が足りない もしくは 設定した金額まで所持金が減った のでバッググラウンドスロットを停止しました。");
                    $this->slotb[$name] = false;
                }
            }
        }
    }

	public function confirm_1_slot($p)
	{
		$p->sendMessage("§l§bSLOT>> §aおや...？、なにやら様子が....");
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "confirm_2_slot"], [$p]),50);
		$this->oto($p, "what");
		$this->oto($p, "good");
		$this->oto($p, "bad");
	}
	
	public function confirm_2_slot($p)
	{
		$p->sendMessage("§l§bSLOT>> §cこれは...確定....!?!?");
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "confirm_3_slot"], [$p]),80);
		$this->oto($p, "what");
		$this->oto($p, "good");
		$this->oto($p, "bad");
	}
	
	public function confirm_3_slot(Player $p)
	{
		$p->sendMessage("§l§bSLOT>> §eおめでとうございます、あなたは当選しました！！！");
		$money = $this->config->get("ジャックポット");
		$this->addMoney($money, $p);
		$this->oto($p, "good");
				
		$p->sendMessage("§b§lSLOT>> §6ジャックポット§bおめでとうございます！");
		$p->sendMessage("§b§lSLOT>> §6{$money}円手に入れた！");
        $this->slot_karma[$p->getName()] = 0;
			
			
		$this->getServer()->broadcastMessage("§lSLOT>> §a{$p->getName()}さんがジャックポット {$money}円を手に入れました！");
		$this->getServer()->broadcastMessage("§lSLOT>> ジャックポットが{$this->config->get("初期ジャックポット")}に戻りました");
		
		$name = $p->getName();
		
		$this->JoinType($name);
		
		$this->config->set("LastPlayer", $name);
		$this->config->set("LastJackPot", $this->config->get("ジャックポット"));
		$this->config->save();
		
		$this->scan($name, $this->config->get("ジャックポット"));
		
		$this->config->set("ジャックポット", $this->config->get("初期ジャックポット"));
		$this->config->save();
	}
	
	public function gPerformance1($p)
	{
		$s = mt_rand(1,9);
		$p->addTitle("§f[§4{$s}§f]-[§4?§f]-[§4?§f]");
		//$this->getServer()->broadcastMessage("§lSLOTDebug>> per1作動");
		$this->oto($p, "bad");
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "gPer2"], [$p]),10);
	}
	
	public function gPer2($p)
	{
		$s = mt_rand(1,9);
		$p->addTitle("§f[§4{$s}§r§f]-[§4{$s}§f]-[§4?§f]");
		//$this->getServer()->broadcastMessage("§lSLOTDebug>> per2作動");
		$this->oto($p, "bad");
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "gPer3"], [$p]),10);
	}
	
	public function gPer3($p)
	{
		$this->godp++;
		$s = mt_rand(1,9);
		$p->addTitle("§f[§4{$s}§r§f]-[§4{$s}§f]-[§4{$s}§f]");
		$this->oto($p, "bad");
		//$this->getServer()->broadcastMessage("§lSLOTDebug>> per3作動");
		if($this->godp !== 10){
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "gPerformance1"], [$p]),10);
		
		}else{
		$this->godp = 0;
		$p->addTitle("§l§aU§bL§cT§eR§fA §dJ§6A§4C§7K§bP§aO§2T§4!!");
		
		$pk = new LevelEventPacket();
		$pk->evid = LevelEventPacket:: EVENT_SOUND_TOTEM;
		$pk->data = 0;
		$pk->position = $p->asVector3();
		$p->dataPacket($pk);
		
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "godsu"], [$p]),30);
		}
	}
	
	public function godsu($p)
	{
		$this->oto($p, "what");
		$this->oto($p, "good");
		$this->oto($p, "bad");
		
		$p->sendMessage("§l§bSLOT>> §6おめでとうございます、あなたは当選しました！！！");
		$money = $this->config->get("ジャックポット");
		$num = mt_rand(2,7);
		$moneyn = $money * $num;
		$this->addMoney($moneyn, $p);
				
		$p->sendMessage("§b§lSLOT>> §6ウルトラジャックポット§bおめでとうございます！");
		$p->sendMessage("§b§lSLOT>> §6{$moneyn}円手に入れた！");
			
			
		$this->getServer()->broadcastMessage("§lSLOT>> §a{$p->getName()}さんがウルトラジャックポットにより、 {$money}円の{$num}倍、{$moneyn}円を手に入れました！");
		$this->getServer()->broadcastMessage("§lSLOT>> ジャックポットが{$this->config->get("初期ジャックポット")}に戻りました");
		
		$name = $p->getName();
        $this->slot_karma[$name] = 0;
		$this->JoinType($name);
		
		$this->config->set("LastPlayer", $name);
		$this->config->save();
		$this->config->set("LastJackPot", $moneyn);
		$this->config->save();
		
		$this->scan($name, $this->config->get("ジャックポット"));
		
		$this->config->set("ジャックポット", $this->config->get("初期ジャックポット"));
		$this->config->save();
		
		$inv = $p->getInventory();
		$i = $inv->getItemInHand();
		$inv->setItemInHand(Item::get(ItemIds::TOTEM));
		$p->broadcastEntityEvent(ActorEventPacket::CONSUME_TOTEM);
		$inv->setItemInHand($i);/*冬月さんありがとうございました！*/
		
		
		$pk = new LevelEventPacket();
		$pk->evid = LevelEventPacket:: EVENT_SOUND_TOTEM;
		$pk->data = 0;
		$pk->position = $p->asVector3();
		$p->dataPacket($pk);
	}
	
  	public function addMoney($money, $p)
  	{
 		$plugin = $this->config->get("プラグイン");
		$name = $p->getName();
 		
 		if($plugin == "EconomyAPI")
 		{
 	  		$this->system->addmoney($name ,$money);
 		}
 		
 		if($plugin == "MixCoinSystem")
 		{
 	 		MixCoinSystem::getInstance()->PlusCoin($name,$money);
 		}
 		
 		if($plugin == "MoneySystem")
 		{
 			API::getInstance()->increase($p, $money, "SLOT", "当選のため");
 		}
 	}
 	
 	public function getMoney($p)
 	{
 		$plugin = $this->config->get("プラグイン");
		$name = $p->getName();
 		if($plugin == "EconomyAPI")
 		{
 	  		return $this->system->myMoney($name);
 		}
 		
 		if($plugin == "MixCoinSystem")
 		{
 			return MixCoinSystem::getInstance()->GetCoin($name);
 		}
 		
 		if($plugin == "MoneySystem")
 		{
 			return API::getInstance()->get($p);
 		}
 	}
 	
 	public function cutMoney($p, $money)
 	{
 		$plugin = $this->config->get("プラグイン");
 		$name = $p->getName();
 		if($plugin == "EconomyAPI") {
 	  		$this->system->reduceMoney($name, $money);
 		}
 		
 		if($plugin == "MixCoinSystem") {
 			MixCoinSystem::getInstance()->MinusCoin($name,$money);
 		}
 		
 		if($plugin == "MoneySystem") {
 			API::getInstance()->reduce($p, $money, "SLOT", "スロット料金");
 		}
 	}
 	
 	public function oto($player, $id)
 	{
 		switch($id) {
 			case "pop": //抽選中
 			$pk = new PlaySoundPacket;
			$pk->soundName = "random.pop";
			$pk->x = $player->x;
			$pk->y = $player->y;
			$pk->z = $player->z;
			$pk->volume = 0.5;
			$pk->pitch = 1;
			$player->sendDataPacket($pk);
 			break;
 			
 			case "bad": //ハズレ
 			$pk = new PlaySoundPacket;
			$pk->soundName = "random.anvil_land";
			$pk->x = $player->x;
			$pk->y = $player->y;
			$pk->z = $player->z;
			$pk->volume = 0.5;
			$pk->pitch = 1;
			$player->sendDataPacket($pk);
 			break;
 			
 			case "good": //あたり...
 			$pk = new PlaySoundPacket;
			$pk->soundName = "random.levelup";
			$pk->x = $player->x;
			$pk->y = $player->y;
			$pk->z = $player->z;
			$pk->volume = 0.5;
			$pk->pitch = 1;
			$player->sendDataPacket($pk);
			break;
				
			case "what": //?...
 			$pk = new PlaySoundPacket;
			$pk->soundName = "entity.lightning_bolt.thunder";
			$pk->x = $player->x;
			$pk->y = $player->y;
			$pk->z = $player->z;
			$pk->volume = 0.5;
			$pk->pitch = 1;
			$player->sendDataPacket($pk);
			break;

            case "ex":
            $pk = new PlaySoundPacket;
            $pk->soundName = "entity.generic.explode";
            $pk->x = $player->x;
            $pk->y = $player->y;
            $pk->z = $player->z;
            $pk->volume = 0.5;
            $pk->pitch = 1;
            $player->sendDataPacket($pk);
            break;
 		}
 	}

 	public function sendslot(Player $p, $s1, $bool = false, $s2="§k?§r", $s3="§k?§r"){
	    if($bool) {
            $up = "当選確率上昇中...";
        }else{
	        $up = "";
        }
        if($s1 == 7) {
            $p->addTitle("§f[§6{$s1}§f]-[§a{$s2}§f]-[§c{$s3}§f]",$up);
        }
        elseif($s1 == 7 && $s2 == 7){
            $p->addTitle("§f[§6{$s1}§f]-[§6{$s2}§r§f]-[§c{$s3}§f]",$up);
        }
	    elseif($s1 == 7 && $s2 == 7 && $s3 == 7){
            $p->addTitle("§f[§6{$s1}§f]-[§6{$s2}§r§f]-[§6{$s3}§r§f]",$up);
        }else{
            $p->addTitle("§f[§e{$s1}§f]-[§a{$s2}§f]-[§c{$s3}§f]",$up);
        }
    }
 	
 	public function scan($name, $jp){
	if($this->config->get("LastHighJackPot") < $jp){ $this->config->set("HighJackPot", $jp); $this->config->save(); }
	}
	
	public function slotinfo(){

		$xyz = $this->xyz;
 		$level_name = $xyz->get("world");
 		$level = $this->getServer()->getLevelByName($level_name);
 		$this->ftp->setInvisible();
		$level->addParticle($this->ftp);
 		
 		$confirm1 = $this->confirm_1;
		$confirm2 = $this->confirm_2;
		$confirm3 = $this->confirm_3;
		
		$kakutei = $confirm1.$confirm2.$confirm3; //.をつけてくっつける
		$jackpot = $this->config->get("ジャックポット");
		$lastname = $this->config->get("LastPlayer");
		$lastjackpot = $this->config->get("LastJackPot");
		$highname = $this->config->get("HighPlayer");
		$highjp = $this->config->get("HighJackPot");

		if($highjp < $lastjackpot){
		    $this->config->set("HighPlayer",$lastname);
		    $this->config->set("HighJackPot",$lastjackpot);
        }
		
		$text = $this->info->get("text");
		$text = str_replace("{br}", "\n", $text);
		$text = str_replace("{jackpot}", $jackpot, $text);
		$text = str_replace("{kakutei}", $kakutei, $text);
		$text = str_replace("{lastname}", $lastname, $text);
		$text = str_replace("{lastjackpot}", $lastjackpot, $text);
		$text = str_replace("{highname}", $highname, $text);
		$text = str_replace("{highjp}", $highjp, $text);
		
		$title = $this->info->get("title");
		$title = str_replace("{br}", "\n", $title);
		$title = str_replace("{jackpot}", $jackpot, $title);
		$title = str_replace("{kakutei}", $kakutei, $title);
		$title = str_replace("{lastname}", $lastname, $title);
		$title = str_replace("{lastjackpot}", $lastjackpot, $title);
		$text = str_replace("{highname}", $highname, $text);
		$text = str_replace("{highjp}", $highjp, $text);
		
 		$x = $xyz->get("x");
 		$y = $xyz->get("y");
 		$z = $xyz->get("z");
 		$pos = new Vector3($x, $y, $z, $level);
		$this->ftp = new FloatingTextParticle($pos,$text,$title);
		$level->addParticle($this->ftp);
	}
	
	public function SlotUIg(Player $p)
	{
	    $bool_auto = $this->slota[$p->getName()];
	    $bool_back = $this->slotb[$p->getName()];
	    if($bool_auto){ $auto = "§bON§f"; }else{ $auto = "§7OFF§f"; }
        if($bool_back){ $auto_b = "§bON§f"; }else{ $auto_b = "§7OFF§f"; }
		$buttons[] = [
		'text' => "スロットを行う"];
		$buttons[] = [
		'text' => "autoslotを行う/キャンセル\n現在:§l{$auto}"];
		$buttons[] = [
		'text' => "backslotを行う/キャンセル\n現在:§l{$auto_b}"];
		$buttons[] = [
		'text' => "設定"];
		$this->sendForm($p,"SLOT","§lv§b{$this->getDescription()->getVersion()}     \n",$buttons,8000); //いえい
	}
	
	public function SlotUIa(Player $p)
	{
        $bool_auto = $this->slota[$p->getName()];
        $bool_back = $this->slotb[$p->getName()];
        if($bool_auto){ $auto = "§bON§f"; }else{ $auto = "§7OFF§f"; }
        if($bool_back){ $auto_b = "§bON§f"; }else{ $auto_b = "§7OFF§f"; }
		$buttons[] = [
		'text' => "スロットを行う"];
		$buttons[] = [
		'text' => "autoslotを行う/キャンセル\n現在:§l{$auto}"];
        $buttons[] = [
        'text' => "backslotを行う/キャンセル\n現在:§l{$auto_b}"];
		$buttons[] = [
		'text' => "設定"];
		$buttons[] = [
		'text' => "SLOTのシステム設定"];
		$buttons[] = [
		'text' => "デバッグ\n§l§4悪用厳禁"];
		$this->sendForm($p,"SLOT","§lv§b{$this->getDescription()->getVersion()}§r     \n§l権限者用のFormです\n",$buttons,8000);
	}
	
	public function onDataPacketReceiveEvent(DataPacketReceiveEvent $event) {
		$pk = $event->getPacket();
		$p = $event->getPlayer();
		$name = $p->getName();
		if($pk instanceof ModalFormResponsePacket) {
			$id = $pk->formId;
			$data = $pk->formData;
			$result = json_decode($data);
			if($data === "null\n") {
			}else{
			
			switch($id)
			{
				case 8000:
				switch($data)
				{
					case 0: //スロットを行う
					$money = $this->getMoney($p);
					$price = $this->config->get("値段");
					if($price > $money)
					{
						$p->sendMessage("§b§lSLOT>> §cお金が足りません！ スロットを一回回すには§f{$price}円§c必要です");
						break;
					}elseif($this->slot[$name] == true)
					{
						$p->sendMessage("§b§lSLOT>> §cスロット中です。");
						break;
					}else{
					
						$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot_1"], [$p,self::SLOT]),30);
						$p->addTitle("§f[§e?§f]-[§e?§f]-[§e?§f]","§6抽選を開始します...");
						$this->oto($p, "pop");
						$this->slot[$name] = true;
					}
					break;
					
					case 1://autoslot
					if($this->slota[$name] == false) {
						$p->sendMessage("§b§lSLOT>> §bスロットの自動化を有効にしました。");
						$this->slota[$name] = true;
						break;
					}else{
						$p->sendMessage("§b§lSLOT>> §bスロットの自動化を無効にしました。");
						$this->slota[$name] = false;
						break;
					}
					break;
					
					case 2: //backslot
					$money = $this->getMoney($p);
					$price = $this->config->get("値段");
					if($price < $money) {
						if($this->slot[$name] == false) {
							if($this->slotb[$name] == false) {
								$p->sendMessage("§l§bSLOT>> §cバッググラウンドのスロットを開始します...");
								$this->slotb[$name] = true;
								$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot_back"], [$p]),20);

								break;
							}else{
								$p->sendMessage("§l§bSLOT>> §cバッググラウンドのスロットを停止します...");
								$this->slotb[$name] = false;
								break;
							}
						}elseif($this->slotb[$name] == true) {
							$p->sendMessage("§l§bSLOT>> §cバッググラウンドのスロットを停止します...");
							$this->slotb[$name] = false;
							$this->slot[$name] = false;
							break;
						}else {
							$p->sendMessage("§b§lSLOT>> §c通常スロットが抽選を行っています、しばらくお待ちください。");
							$p->sendMessage("§b§lSLOT>> §cもしも自動抽選を行っている場合は停止してください");
							break;
						}
						}else{
							$p->sendMessage("§b§lSLOT>> §cお金が足りません！ スロットを回すには§f{$price}円§c必要です");
							break;
						}
						break;
						
						case 3://設定
						$buttons[] = [
						'text' => "スロット自動停止金額"];
						$this->sendForm($p,"SLOT","§lオートスロットかバックスロットを行っている際に、所持金が一定金額以下になったら自動で停止します。\n",$buttons,8001);
						break;
						
						case 4://設定(スロット)
						$buttons[] = [
						'text' => "浮き文字の座標変更"];//0
						$buttons[] = [
						'text' => "JPを変更"];//1
						$buttons[] = [
						'text' => "1回の値段を変更"];//2
						$buttons[] = [
						'text' => "浮き文字を一旦消す"];//3
						$this->sendForm($p,"SLOT","§l設定したい項目を選択してください\n",$buttons,8002);
						break;
						
						case 5:
						$data = [
						"type" => "custom_form",
						"title" => "SETUP",
						"content" => [
							[
								"type" => "label",
								"text" => "§lウルトラジャックポットを実行する際は全部aと打ってください"
							],
							[
								"type" => "input",
								"text" => "§l1桁目",
								"placeholder" => "",
								"default" => "",
							],
							[
								"type" => "input",
								"text" => "§l2桁目",
								"placeholder" => "",
								"default" => "",
							],
							[
								"type" => "input",
								"text" => "§l3桁目",
								"placeholder" => "",
								"default" => "",
							],
							
						]
						];
						$this->createWindow($p, $data, 94941);
						break;
						
						case 6:
						$s1 = mt_rand(1,9);
						$this->getServer()->broadcastMessage("§lSLOT>> §a{$name}さんがジャックポットのデバッグ機能を使用しました！");
						$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "gslot2"], [$p,$s1]),10);
						break;
					}
					break;
					
					case 8001: //設定
					switch($data)
					{
						case 0://自動停止
						$name = $p->getName();
						$data = [
						"type" => "custom_form",
						"title" => "SETUP",
						"content" => [
							[
								"type" => "input",
								"text" => "§l自動停止金額",
								"placeholder" => "",
								"default" => "{$this->stop->get($name)}",
							]
						]
						];
						$this->createWindow($p, $data, 8003);
						break;
					}
					break;
					
					case 8003: //自動停止結果
					$this->stop->set($name,$result[0]);
					$this->stop->save();
					
					$p->sendMessage("§l§bSLOT>> §c自動停止金額を{$result[0]}円に設定しました");
					break;
					
					case 8002:
					switch($data)
					{
						case 0://座標
						$xyz = $this->xyz;
						$level = $p->getLevel();
						$x = $p->getX();
						$y = $p->getY() +1;
						$z = $p->getZ();
						$level = $p->getLevel();
						$level_name = $level->getName();
						$xyz->set("x",$x);
						$xyz->set("y",$y);
						$xyz->set("z",$z);
						$xyz->set("world",$level_name);
						$xyz->save();
						$p->sendMessage("§b§lSLOT>> 登録しました §a{$x} §b{$y} §e{$z} §d{$level_name}");
						$this->getServer()->loadLevel($this->xyz->get("world"));
						break;
						
						case 1://JP変更
						$data = [
						"type" => "custom_form",
						"title" => "SETUP",
						"content" => [
							[
								"type" => "input",
								"text" => "§l現在のジャックポット",
								"placeholder" => "",
								"default" => "{$this->config->get("ジャックポット")}",
							]
						]
						];
						$this->createWindow($p, $data, 8005);
						break;
						
						case 2://値段変更
						$data = [
						"type" => "custom_form",
						"title" => "SETUP",
						"content" => [
							[
								"type" => "input",
								"text" => "§lスロット1回の値段",
								"placeholder" => "",
								"default" => "{$this->config->get("値段")}",
							]
						]
						];
						$this->createWindow($p, $data, 8006);
						break;
						
						case 3: //一旦消す
						$xyz = $this->xyz;
 						$level_name = $xyz->get("world");
 						$level = $this->getServer()->getLevelByName($level_name);
 						$this->ftp->setInvisible();
						$level->addParticle($this->ftp);
						$p->sendMessage("§l§bSLOT>> §c浮き文字を一旦消去しました");
						break;
					}
					break;
					
					case 8005: //JP変更結果
					$this->config->set("ジャックポット",$result[0]);
					$this->config->save();
					$p->sendMessage("§l§bSLOT>> §cジャックポットを§6{$result[0]}§c円に変更しました。");
					break;
					
					case 8006: //値段変更結果
					$this->config->set("値段",$result[0]);
					$this->config->save();
					$p->sendMessage("§l§bSLOT>> §c値段を§6{$result[0]}§c円に変更しました。");
					break;
					
					case 94941: //Debug
					if($result[1] !== "a"){
					$this->getServer()->broadcastMessage("§lSLOT>> §a{$name}さんがジャックポットのデバッグ機能を使用しました！");
					$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "end"], [$p, $result[1], $result[2], $result[3]]),10);
					break;
					}else{
					$s1 = mt_rand(1,9);
					$this->getServer()->broadcastMessage("§lSLOT>> §a{$name}さんがジャックポットのデバッグ機能を使用しました！");
					$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "gPerformance1"], [$p]),10);
					}
					break;
				}
			}
		}
	}
	public function createWindow(Player $player, $data, int $id)
    {
		$pk = new ModalFormRequestPacket();
		$pk->formId = $id;
		$pk->formData = json_encode($data, JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE);
		$player->dataPacket($pk);
	}
	
	public function sendForm(Player $player, $title, $come, $buttons, $id)
	{
  		$pk = new ModalFormRequestPacket(); 
  		$pk->formId = $id;
  		$this->pdata[$pk->formId] = $player;
  		$data = [ 
  		'type'    => 'form', 
  		'title'   => $title, 
  		'content' => $come, 
  		'buttons' => $buttons 
  		]; 
  		$pk->formData = json_encode( $data, JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE );
  		$player->dataPacket($pk);
  		$this->lastFormData[$player->getName()] = $data;
  	}
  	
  	public function canslot($p)
  	{
  		$n = $p->getName();
  		if($this->getMoney($p) >= $this->stop->get($n)){ return true; }else{ return false; }
	}
}

class CallbackTask extends Task{

	private $callable, $args;

    public function __construct(callable $callable, array $args = []){
		$this->callable = $callable;
		$this->args = $args;
	}

	public function onRun($tick){
		call_user_func_array($this->callable, $this->args);
	}

}
