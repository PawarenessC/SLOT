<?php

namespace pawarenessc\slot;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;

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
		public $slot = [];
		public $slota = [];
		public $slotb = [];
		public $ftp;
		
		
		public function onEnable()
    	{
    					
			$this->kaku1 = mt_rand(1,9);
			$this->kaku2 = mt_rand(1,9);
			$this->kaku3 = mt_rand(1,9);
    		
    		$this->getLogger()->info("=========================");
 			$this->getLogger()->info("SLOTを読み込みました");
 			$this->getLogger()->info("制作者: PawarenessC");
 			$this->getLogger()->info("ライセンス: NYSL Version 0.9982");
 			$this->getLogger()->info("http://www.kmonos.net/nysl/");
 			$this->getLogger()->info("バージョン:{$this->getDescription()->getVersion()}");
 			$this->getLogger()->info("スロットの確定番号は {$this->kaku1}{$this->kaku2}{$this->kaku3} です");
 			$this->getLogger()->info("=========================");
       
        	$this->config = new Config($this->getDataFolder()."Setup.yml", Config::YAML,
			[
			
			"説明" > "プラグインは使用する経済プラグイン(EconomyAPI,MoneySystem,MixCoinSystem)から選んで記入してください、値段は一回スロットをする値段、ジャックポット以外の賞金は777以外のゾロ目が当たった時の値段を設定してください、ジャックポットは弄らない方がいいです",
			"プラグイン" => "EconomyAPI",
			"値段" => 100,
			"ジャックポット以外の賞金" => 1000,
			"初期ジャックポット" => 10000,
			"ジャックポット" => 10000,
			"LastPlayer" => "NO NAME",
			"LastJackPot" => 0,
			"UpdateInterval" => 1,
			]);
			
			$this->xyz = new Config($this->getDataFolder()."xyz.yml", Config::YAML,
			[
			"world" => "world",
			"x" => 281,
			"y" => 4,
			"z" => 284,
			]);
			
			$this->info = new Config($this->getDataFolder()."info.yml", Config::YAML,
			[
			"説明" => "改行をするときは{br}です",
			"title" => "=-=-=現在のスロットの情報=-=-={br}",
			"text" => "§b現在のジャックポット §6{jackpot}§f円{br}§b当選確定番号 §l§f{kakutei}§r§f番{br}§b最後のジャックポット当選者 §l§6{lastname} §f{lastjackpot}円",
			]);
			
			$this->stop = new Config($this->getDataFolder() ."stop.yml", Config::YAML);
			
			$this->system = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
			$this->getServer()->getPluginManager()->registerEvents($this, $this);
			
			$this->slot = [];
			$this->slota = [];
			$this->slotb = [];
			
			$kaku1 = $this->kaku1;
			$kaku2 = $this->kaku2;
			$kaku3 = $this->kaku3;
			
			$kakutei = $kaku1.$kaku2.$kaku3; //.をつけてくっつける
			$jackpot = $this->config->get("ジャックポット");
			$lastname = $this->config->get("LastPlayer");
			$lastjackpot = $this->config->get("LastJackPot");
			
			$text = $this->info->get("text");
			$text = str_replace("{br}", "\n", $text);
			$text = str_replace("{jackpot}", $jackpot, $text);
			$text = str_replace("{kakutei}", $kakutei, $text);
			$text = str_replace("{lastname}", $lastname, $text);
			$text = str_replace("{lastjackpot}", $lastjackpot, $text);
			
			$title = $this->info->get("title");
			$title = str_replace("{br}", "\n", $title);
			$title = str_replace("{jackpot}", $jackpot, $title);
			$title = str_replace("{kakutei}", $kakutei, $title);
			$title = str_replace("{lastname}", $lastname, $title);
			$title = str_replace("{lastjackpot}", $lastjackpot, $title);
 			
 			$xyz = $this->xyz;
 			$x = $xyz->get("x");
 			$y = $xyz->get("y");
 			$z = $xyz->get("z");
 			$level_name = $xyz->get("world");
 			$level = $this->getServer()->getLevelByName($level_name);
 			$pos = new Vector3($x, $y, $z, $level);
			$this->ftp = new FloatingTextParticle($pos,$text,$title);
			$level->addParticle($this->ftp);
			
			$int = $this->config->get("UpdateInterval") * 20;
			$this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "slotinfo"]), $int);
			
			
  		}
  		
  		public function onJoin(PlayerJoinEvent $event)
  		{
  			$player = $event->getPlayer();
  			$name = $player->getName();
  			$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "JoinType"], [$name]),20);
  			$this->slot[$name] = false;
  			
  			if(!$this->stop->exists($name))
			{
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
  		
  		
  		///////////////////////////////////////////////////////////////////////////////
  		// slot  その名前のプレイヤーのスロットが行われているかどうか
  		// slota その名前のプレイヤーの自動抽選が有効かどうか
  		// slotb その名前のプレイヤーがバッググラウンドのスロットを行っているかどうか
  		///////////////////////////////////////////////////////////////////////////////
  		
  	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		$name = $sender->getName();
		
		switch($label)
		{
			
			case "slot":
			$money = $this->getMoney($sender);
			$price = $this->config->get("値段");
			if($price > $money)
			{
				$sender->sendMessage("§b§lSLOT>> §cお金が足りません！ スロットを一回回すには§f{$price}円§c必要です");
				return true;
				break;
			}elseif($this->slot[$name] == true)
			{
				$sender->sendMessage("§b§lSLOT>> §cスロット中です。");
				return true;
			}else{
			
				$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot1"], [$sender]),30);
				$sender->addTitle("§f[§e?§f]-[§e?§f]-[§e?§f]","§6抽選を開始します...");
				$this->oto($sender, "pop");
				$this->slot[$name] = true;
				return true;
			}
			break;
			
			case "slotui":
			if($sender->isOp()){
				$this->SlotUIa($sender);
			}else{
				$this->SlotUIg($sender);
			}
			break;
		}
	return true;
}
	
	public function slot1($p)
	{
		$s1 = mt_rand(1,10);
		if($s1 == 10){ $s1 = 1; }
		if($s1 == 7){ $p->addTitle("§f[§6{$s1}§f]-[§a§k?§r§f]-[§c§k?§r§f]"); }else{
		$p->addTitle("§f[§e{$s1}§f]-[§a§k?§r§f]-[§c§k?§r§f]"); }
		$this->oto($p, "pop");
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot2"], [$p, $s1]),30);
	}
	
	public function fslot1($p) //再抽選用
	{
		$s1 = mt_rand(1,13);
		if($s1 == 10){ $s1 = 1; }
		if($s1 == 11){ $s1 = 1; }
		if($s1 == 12){ $s1 = 1; }
		if($s1 == 13){ $s1 = 1; }
		if($s1 == 7){ $p->addTitle("§f[§6{$s1}§f]-[§a§k?§r§f]-[§c§k?§r§f]"); }else{
		$p->addTitle("§f[§e{$s1}§f]-[§a§k?§r§f]-[§c§k?§r§f]"); }
		$this->oto($p, "pop");
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "fslot2"], [$p, $s1]),10);
	}
	
	public function bslot1($p) //バッググラウンド用
	{
		$name = $p->getName();
		if($this->slotb[$name] == true)
		{
			$s1 = mt_rand(1,21);
			if($s1 == 10){ $s1 = 1; }
			if($s1 == 11){ $s1 = 1; }
			if($s1 == 12){ $s1 = 1; }
			if($s1 == 13){ $s1 = 1; }
			if($s1 == 14){ $s1 = 1; }
			if($s1 == 15){ $s1 = 1; }
			if($s1 == 16){ $s1 = 1; }
			if($s1 == 17){ $s1 = 1; }
			if($s1 == 18){ $s1 = 1; }
			if($s1 == 19){ $s1 = 1; }
			if($s1 == 20){ $s1 = 1; }
			if($s1 == 21){ $s1 = 1; }
			$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "bslot2"], [$p, $s1]),10);
		}
	}	
	
	public function slot2($p,$s1)
	{
		$s2 = mt_rand(1,10);
		if($s2 == 10){ $s2 = 2; }
		if($s2 == 7 && $s1 == 7){ $p->addTitle("§f[§6{$s1}§f]-[§6{$s2}§f]-[§c§k?§r§f]"); }else{
		$p->addTitle("§f[§e{$s1}§f]-[§a{$s2}§f]-[§c§k?§r§f]"); }
		$this->oto($p, "pop");
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot3"], [$p, $s1, $s2]),30);
	}
	
	public function fslot2($p,$s1) //再抽選
	{
		$s2 = mt_rand(1,13);
		if($s2 == 10){ $s2 = 2; }
		if($s2 == 11){ $s2 = 2; }
		if($s2 == 12){ $s2 = 2; }
		if($s2 == 13){ $s2 = 2; }
		if($s2 == 7 && $s1 == 7){ $p->addTitle("§f[§6{$s1}§f]-[§6{$s2}§f]-[§c§k?§r§f]"); }else{
		$p->addTitle("§f[§e{$s1}§f]-[§a{$s2}§f]-[§c§k?§r§f]"); }
		$this->oto($p, "pop");
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "fslot3"], [$p, $s1, $s2]),10);
	}
	
	public function bslot2($p,$s1) //バッググラウンド
	{
		$s2 = mt_rand(1,21);
		if($s2 == 10){ $s2 = 2; }
		if($s2 == 11){ $s2 = 2; }
		if($s2 == 12){ $s2 = 2; }
		if($s2 == 13){ $s2 = 2; }
		if($s2 == 14){ $s2 = 2; }
		if($s2 == 15){ $s2 = 2; }
		if($s2 == 16){ $s2 = 2; }
		if($s2 == 17){ $s2 = 2; }
		if($s2 == 18){ $s2 = 2; }
		if($s2 == 19){ $s2 = 2; }
		if($s2 == 20){ $s2 = 2; }
		if($s2 == 21){ $s2 = 2; }
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "bslot3"], [$p, $s1, $s2]),5);
	}
	
	
	public function slot3($p,$s1,$s2)
	{
		$s3 = mt_rand(1,10);
		if($s3 == 10){ $s3 = 3; }
		if($s3 == 7 && $s2 == 7 && $s1 == 7){ $p->addTitle("§f[§6{$s1}§f]-[§6{$s2}§f]-[§6{$s3}§f]","",20,15,10); }else{
		$p->addTitle("§f[§e{$s1}§f]-[§a{$s2}§f]-[§c{$s3}§f]"); }
		$this->oto($p, "pop");
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "end"], [$p, $s1, $s2, $s3]),20);
	}
	
	public function fslot3($p,$s1,$s2) //再抽選
	{
		$s3 = mt_rand(1,13);
		if($s3 == 10){ $s3 = 3; }
		if($s3 == 11){ $s3 = 3; }
		if($s3 == 12){ $s3 = 3; }
		if($s3 == 13){ $s3 = 3; }
		if($s3 == 7 && $s2 == 7 && $s1 == 7){ $p->addTitle("§f[§6{$s1}§f]-[§6{$s2}§f]-[§6{$s3}§f]","",20,15,10); }else{
		$p->addTitle("§f[§e{$s1}§f]-[§a{$s2}§f]-[§c{$s3}§f]"); }
		$this->oto($p, "pop");
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "end"], [$p, $s1, $s2, $s3]),10);
	}
	
	public function bslot3($p,$s1,$s2) //バッググラウンド
	{
		$s3 = mt_rand(1,13);
		if($s3 == 10){ $s3 = 3; }
		if($s3 == 11){ $s3 = 3; }
		if($s3 == 12){ $s3 = 3; }
		if($s3 == 13){ $s3 = 3; }
		if($s3 == 14){ $s3 = 3; }
		if($s3 == 15){ $s3 = 3; }
		if($s3 == 16){ $s3 = 3; }
		if($s3 == 17){ $s3 = 3; }
		if($s3 == 18){ $s3 = 3; }
		if($s3 == 19){ $s3 = 3; }
		if($s3 == 20){ $s3 = 3; }
		if($s3 == 21){ $s3 = 3; }
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "bend"], [$p, $s1, $s2, $s3]),10);
	}
	
	public function end($p,$s1,$s2,$s3)
	{
		$money = $this->config->get("値段");
		$name = $p->getName();
		$this->cutMoney($p,$money);
		$this->slot[$name] = false;
		$kaku = mt_rand(1,999);
		$kaku1 = $this->kaku1;
		$kaku2 = $this->kaku2;
		$kaku3 = $this->kaku3;
		
		if($s1 == $s2 && $s1 == $s3)
		{
			switch($s1)
			{
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
				if($this->slota[$name]){ $p->sendMessage("§b§lSLOT>> §f当たったので再抽選を停止します"); }
				
				$this->config->set("ジャックポット", $this->config->get("ジャックポット") + $this->config->get("値段"));
				$this->config->save();
				//$this->slotinfo();
				break;
				
				case 7:
				$money = $this->config->get("ジャックポット");
				$this->addMoney($money, $p);
				$this->oto($p, "good");
				
				$p->sendMessage("§b§lSLOT>> §6ジャックポット§bおめでとうございます！");
				$p->sendMessage("§b§lSLOT>> §6{$money}円手に入れた！");
				if($this->slota[$name]){ $p->sendMessage("§b§lSLOT>> §f当たったので再抽選を停止します"); }
				
				$this->getServer()->broadcastMessage("§lSLOT>> §a{$p->getName()}さんがジャックポット {$money}円を手に入れました！");
				$this->getServer()->broadcastMessage("§lSLOT>> ジャックポットが{$this->config->get("初期ジャックポット")}に戻りました");
				
				$this->config->set("LastPlayer", $name);
				$this->config->save();
				$this->config->set("LastJackPot", $this->config->get("ジャックポット"));
				$this->config->save();
				//$this->slotinfo();
				
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
		}elseif($s1 == $kaku1 && $s2 == $kaku2 && $s3 == $kaku3 or $kaku == 1) //確定ってやつ？ 起動時に3つの数字を乱数生成し、抽選番号がそれと合致するか、1/999の確率で確定を起こす
		{
			$p->sendMessage("§b§lSLOT>> §c残念...ハズレです...(-ω-)");
			$this->JoinType($name); //スロットを全て停止
			$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "kakutei1"], [$p]),50);
			
			$pk = new LevelEventPacket();
			$pk->evid = LevelEventPacket::EVENT_GUARDIAN_CURSE;
			$pk->data = 0;
			$pk->position = $p->asVector3();
			$p->dataPacket($pk);
			
		}else
		{
			
			$this->config->set("ジャックポット", $this->config->get("ジャックポット") + $this->config->get("値段"));
			$this->config->save();
			//$this->slotinfo();
			$p->sendMessage("§b§lSLOT>> §c残念...ハズレです...");
			$p->sendMessage("§b§lSLOT>> §c現在のジャックポット§e{$this->config->get("ジャックポット")}円");
			$this->oto($p,"bad");
			$money = $this->config->get("値段");
			if($this->slota[$name] == true)
			{
				if($this->getMoney($p) > $money && $this->canslot($p))
				{
					$p->sendMessage("§l§bSLOT>> §a外れたので再抽選を行います");
					$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "fslot1"], [$p]),20);
					$p->addTitle("§f[§e?§f]-[§e?§f]-[§e?§f]","§6抽選を開始します...");
					$this->oto($p, "pop");
					$this->slot[$name] = true;
				}else
				{
					$p->sendMessage("§l§bSLOT>> §c所持金が足りない もしくは 設定した金額まで所持金が減った ので再抽選を停止しました");
					$this->JoinType($name);
				}
			}
	}
}
	
	public function bend($p,$s1,$s2,$s3)
	{
		$money = $this->config->get("値段");
		$name = $p->getName();
		$this->cutMoney($p,$money);
		$this->slot[$name] = false;
		if($s1 == $s2 && $s1 == $s3)
		{
			switch($s1)
			{
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
				
				$this->config->set("ジャックポット", $this->config->get("ジャックポット") + $this->config->get("値段"));
				$this->config->save();
				
				$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "bslot1"], [$p]),5);
				$this->slot[$name] = true;
				
				//$this->slotinfo();
				break;
				
				case 7:
				$money = $this->config->get("ジャックポット");
				$this->addMoney($money, $p);
				$this->oto($p, "good");
				$this->slotb[$name] = false;
				
				$p->sendMessage("§b§lSLOT>> §6ジャックポット§bおめでとうございます！");
				$p->sendMessage("§b§lSLOT>> §6{$money}円手に入れた！");
				$p->sendMessage("§b§lSLOT>> §f当たったのでバッググラウンドスロットを停止します");
				
				
				$this->getServer()->broadcastMessage("§lSLOT>> §a{$p->getName()}さんがジャックポット {$money}円を手に入れました！");
				$this->getServer()->broadcastMessage("§lSLOT>> ジャックポットが{$this->config->get("初期ジャックポット")}に戻りました");
				
				$this->config->set("LastPlayer", $name);
				$this->config->save();
				$this->config->set("LastJackPot", $this->config->get("ジャックポット"));
				$this->config->save();
				//$this->slotinfo();
				
				
				$this->config->set("ジャックポット", $this->config->get("初期ジャックポット"));
				$this->config->save();
				break;
			}
		}else
		{
			$this->config->set("ジャックポット", $this->config->get("ジャックポット") + $this->config->get("値段"));
			$this->config->save();
			//$this->slotinfo();
			$p->sendPopup("§b§lSLOT>> §cハズレ! §e{$s1}-{$s2}-{$s3} ジャックポット: {$this->config->get("ジャックポット")}円");
			$money = $this->config->get("値段");
			if($this->slotb[$name] == true)
			{
				if($this->getMoney($p) > $money && $this->canslot($p))
				{
					$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "bslot1"], [$p]),5);
					$this->slot[$name] = true;
				}else
				{
					$p->sendMessage("§l§bSLOT>> §c所持金が足りない もしくは 設定した金額まで所持金が減った のでバッググラウンドスロットを停止しました。");
					$this->slotb[$name] = false;
				}
			}
	}
}

	public function kakutei1($p)
	{
		$p->sendMessage("§l§bSLOT>> §aおや...？、なにやら様子が....");
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "kakutei2"], [$p]),50);
		$this->oto($p, "what");
		$this->oto($p, "good");
		$this->oto($p, "bad");
	}
	
	public function kakutei2($p)
	{
		$p->sendMessage("§l§bSLOT>> §cこれは...確定....!?!?");
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "kakutei3"], [$p]),80);
		$this->oto($p, "what");
		$this->oto($p, "good");
		$this->oto($p, "bad");
	}
	
	public function kakutei3($p)
	{
		$p->sendMessage("§l§bSLOT>> §eおめでとうございます、あなたは当選しました！！！");
		$money = $this->config->get("ジャックポット");
		$this->addMoney($money, $p);
		$this->oto($p, "good");
				
		$p->sendMessage("§b§lSLOT>> §6ジャックポット§bおめでとうございます！");
		$p->sendMessage("§b§lSLOT>> §6{$money}円手に入れた！");
			
			
		$this->getServer()->broadcastMessage("§lSLOT>> §a{$p->getName()}さんがジャックポット {$money}円を手に入れました！");
		$this->getServer()->broadcastMessage("§lSLOT>> ジャックポットが{$this->config->get("初期ジャックポット")}に戻りました");
		
		$name = $p->getName();
		
		$this->JoinType($name);
		
		$this->config->set("LastPlayer", $name);
		$this->config->save();
		$this->config->set("LastJackPot", $this->config->get("ジャックポット"));
		$this->config->save();
		//$this->slotinfo();
		
		
		$this->config->set("ジャックポット", $this->config->get("初期ジャックポット"));
		$this->config->save();
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
 			API::getInstance()->increase($p, $money);
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
 		if($plugin == "EconomyAPI")
 		{
 	  		$this->system->reduceMoney($name, $money);
 		}
 		
 		if($plugin == "MixCoinSystem")
 		{
 			MixCoinSystem::getInstance()->MinusCoin($name,$money);
 		}
 		
 		if($plugin == "MoneySystem")
 		{
 			API::getInstance()->reduce($p, $money);
 		}
 	}
 	
 	public function oto($player, $id)
 	{
 		switch($id)
 		{
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
 		}
 	}
 	
 	public function slotinfo()
 	{
 		$xyz = $this->xyz;
 		$level_name = $xyz->get("world");
 		$level = $this->getServer()->getLevelByName($level_name);
 		$this->ftp->setInvisible();
		$level->addParticle($this->ftp);
		
		$xyz = $this->xyz;
 		$level_name = $xyz->get("world");
 		$level = $this->getServer()->getLevelByName($level_name);
 		$this->ftp->setInvisible();
		$level->addParticle($this->ftp);
 		
 		$kaku1 = $this->kaku1;
		$kaku2 = $this->kaku2;
		$kaku3 = $this->kaku3;
		
		$kakutei = $kaku1.$kaku2.$kaku3; //.をつけてくっつける
		$jackpot = $this->config->get("ジャックポット");
		$lastname = $this->config->get("LastPlayer");
		$lastjackpot = $this->config->get("LastJackPot");
		
		$text = $this->info->get("text");
		$text = str_replace("{br}", "\n", $text);
		$text = str_replace("{jackpot}", $jackpot, $text);
		$text = str_replace("{kakutei}", $kakutei, $text);
		$text = str_replace("{lastname}", $lastname, $text);
		$text = str_replace("{lastjackpot}", $lastjackpot, $text);
		
		$title = $this->info->get("title");
		$title = str_replace("{br}", "\n", $title);
		$title = str_replace("{jackpot}", $jackpot, $title);
		$title = str_replace("{kakutei}", $kakutei, $title);
		$title = str_replace("{lastname}", $lastname, $title);
		$title = str_replace("{lastjackpot}", $lastjackpot, $title);
		
 		$x = $xyz->get("x");
 		$y = $xyz->get("y");
 		$z = $xyz->get("z");
 		$pos = new Vector3($x, $y, $z, $level);
		$this->ftp = new FloatingTextParticle($pos,$text,$title);
		$level->addParticle($this->ftp);
	}
	
	public function SlotUIg($p)
	{
		$buttons[] = [
		'text' => "スロットを行う"];
		$buttons[] = [
		'text' => "autoslotを行う/キャンセル"];
		$buttons[] = [
		'text' => "backslotを行う/キャンセル"];
		$buttons[] = [
		'text' => "設定"];
		$this->sendForm($p,"SLOT","            §lv§b{$this->getDescription()->getVersion()}     \n",$buttons,8000); //いえい
	}
	
	public function SlotUIa($p)
	{
		$buttons[] = [
		'text' => "スロットを行う"];
		$buttons[] = [
		'text' => "autoslotを行う/キャンセル"];
		$buttons[] = [
		'text' => "backslotを行う/キャンセル"];
		$buttons[] = [
		'text' => "設定"];
		$buttons[] = [
		'text' => "SLOTの設定"];
		$buttons[] = [
		'text' => "デバッグ\n§4悪用厳禁"];
		$this->sendForm($p,"SLOT","             §l§b{$this->getDescription()->getVersion()}§r     \n§l権限者用のFormです\n",$buttons,8000);
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
					
						$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "slot1"], [$p]),30);
						$p->addTitle("§f[§e?§f]-[§e?§f]-[§e?§f]","§6抽選を開始します...");
						$this->oto($p, "pop");
						$this->slot[$name] = true;
					}
					break;
					
					case 1://autoslot
					if($this->slota[$name] == false)
					{
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
					if($price < $money)
					{
						if($this->slot[$name] == false)
						{
							if($this->slotb[$name] == false)
							{
								$p->sendMessage("§l§bSLOT>> §cバッググラウンドのスロットを開始します...");
								$this->slotb[$name] = true;
								$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "bslot1"], [$p]),20);
								break;
							}else{
							
								$p->sendMessage("§l§bSLOT>> §cバッググラウンドのスロットを停止します...");
								$this->slotb[$name] = false;
								break;
							}
						}elseif($this->slotb[$name] == true)
						{
							$p->sendMessage("§l§bSLOT>> §cバッググラウンドのスロットを停止します...");
							$this->slotb[$name] = false;
							$this->slot[$name] = false;
							break;
						}else
						{
						
							$p->sendMessage("§b§lSLOT>> §c通常スロットが抽選を行っています、しばらくお待ちください。");
							$p->sendMessage("§b§lSLOT>> §cもしも自動抽選を行っている場合は/autoslotをもう一回実行し、停止してください");
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
						'text' => "浮き文字の内容変更"];//1
						$buttons[] = [
						'text' => "JPを変更"];//2
						$buttons[] = [
						'text' => "1回の値段を変更"];//3
						$this->sendForm($p,"SLOT","§l設定したい項目を選択してください\n",$buttons,8002);
						break;
						
						case 5:
						$data = [
						"type" => "custom_form",
						"title" => "SETUP",
						"content" => [
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
							]
						]
						];
						$this->createWindow($p, $data, 94941);
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
						break;
						
						case 1://内容変更
						$data = [
						"type" => "custom_form",
						"title" => "SETUP",
						"content" => [
							[
								"type" => "label",
								"text" => "§lタイトルは一番上の行、テキストはそれ以降の文です\n\n改行..{br}\nジャックポット..{jackpot}\n当選確定番号..{kakutei}\n最後のジャックポット当選者..{lastname}\n最後のジャックポット金額..{lastjackpot}"
							],
							[
								"type" => "input",
								"text" => "§lタイトル",
								"placeholder" => "",
								"default" => "{$this->info->get("title")}",
							],
							[
								"type" => "input",
								"text" => "§lテキスト",
								"placeholder" => "",
								"default" => "{$this->info->get("text")}",
							]
						]
						];
						$this->createWindow($p, $data, 8004);
						break;
						
						case 2://JP変更
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
						
						case 3://値段変更
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
					}
					break;
					
					case 8004://内容変更結果
					$this->info->set("title",$result[1]);
					$this->info->set("text",$result[2]);
					$this->info->save();
					
					$p->sendMessage("§l§bSLOT>> §c浮き文字の内容を変更しました！");
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
					$this->getServer()->broadcastMessage("§lSLOT>> §a{$name}さんがジャックポットのデバッグ機能を使用しました！");
					$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "end"], [$p, $result[0], $result[1], $result[2]]),10);
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

	public function __construct(callable $callable, array $args = []){
		$this->callable = $callable;
		$this->args = $args;
	}

	public function onRun($tick){
		call_user_func_array($this->callable, $this->args);
	}

}


