<?php

namespace pawareness\slot;

use pawarenessc\slot\Main;
use pocketmine\event\Listener;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\event\server\DataPacketReceiveEvent;

use pawarenessc\slot\CallbackTask;

class FormEvent implements Listener {

    private $plugin;
    private $config;

    private $slot,$slota,$slotb;

    public function __construct(Main $plugin){
        $this->plugin = $plugin;
        $this->config = $plugin->config;
        $this->slot = $plugin->slot;
        $this->slota = $plugin->slota;
        $this->slotb = $plugin->slotb;
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
                                $money = $this->plugin->money->getMoney($p);
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

                                    $this->plugin->getScheduler()->scheduleDelayedTask(new CallbackTask([$this->plugin, "slot_1"], [$p,Main::SLOT]),30);
                                    $p->addTitle("§f[§e?§f]-[§e?§f]-[§e?§f]","§6抽選を開始します...");
                                    $this->plugin->sound($p, Main::SOUND_POP);
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
                                $money = $this->plugin->money->getMoney($p);
                                $price = $this->config->get("値段");
                                if($price < $money) {
                                    if($this->slot[$name] == false) {
                                        if($this->slotb[$name] == false) {
                                            $p->sendMessage("§l§bSLOT>> §cバッググラウンドのスロットを開始します...");
                                            $this->slotb[$name] = true;
                                            $this->plugin->getScheduler()->scheduleDelayedTask(new CallbackTask([$this->plugin, "slot_back"], [$p]),20);
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
                                            "default" => "{$this->plugin->stop->get($name)}",
                                        ]
                                    ]
                                ];
                                $this->createWindow($p, $data, 8003);
                                break;
                        }
                        break;

                    case 8003: //自動停止結果
                        $this->plugin->stop->set($name,$result[0]);
                        $this->plugin->stop->save();

                        $p->sendMessage("§l§bSLOT>> §c自動停止金額を{$result[0]}円に設定しました");
                        break;

                    case 8002:
                        switch($data)
                        {
                            case 0://座標
                                $xyz = $this->plugin->info;
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
                                $this->plugin->getServer()->loadLevel($this->plugin->xyz->get("world"));
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
                                $xyz = $this->plugin->info;
                                $level_name = $xyz->get("world");
                                $level = $this->plugin->getServer()->getLevelByName($level_name);
                                $this->plugin->ftp->setInvisible();
                                $level->addParticle($this->plugin->ftp);
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
                            $this->plugin->getServer()->broadcastMessage("§lSLOT>> §a{$name}さんがジャックポットのデバッグ機能を使用しました！");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new CallbackTask([$this->plugin, "end"], [$p, $result[1], $result[2], $result[3]]),10);
                            break;
                        }else{
                            $s1 = mt_rand(1,9);
                            $this->plugin->getServer()->broadcastMessage("§lSLOT>> §a{$name}さんがジャックポットのデバッグ機能を使用しました！");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new CallbackTask([$this->plugin, "gPerformance1"], [$p]),10);
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
        $data = [
            'type'    => 'form',
            'title'   => $title,
            'content' => $come,
            'buttons' => $buttons
        ];
        $pk->formData = json_encode( $data, JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE );
        $player->dataPacket($pk);
    }
}
