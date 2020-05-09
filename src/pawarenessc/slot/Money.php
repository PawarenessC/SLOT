<?php

namespace pawarenessc\slot;

use pawarenessc\slot\Main;
use pocketmine\Player;

use MixCoinSystem\MixCoinSystem;
use metowa1227\moneysystem\api\core\API;
use onebone\economyapi\EconomyAPI;

class Money{

    private $plugin;

    public function __construct(Main $plugin){
        $this->plugin = $plugin;
    }

    public function addMoney(Int $money, Player $p)
    {
        $plugin_economy = $this->plugin->config->get("Price");
        $name = $p->getName();
        switch ($plugin_economy) {
            case "EconomyAPI":
                EconomyAPI::getInstance()->addmoney($name, $money);
                break;

            case "MixCoinSystem":
                MixCoinSystem::getInstance()->PlusCoin($name, $money);
                break;

            case "MoneySystem":
                API::getInstance()->increase($p, $money, "SLOT", "当選");
                break;
        }
    }

    public function getMoney(Player $p): Int
    {
        $plugin_economy = $this->plugin->config->get("プラグイン");
        $name = $p->getName();

        switch ($plugin_economy) {
            case "EconomyAPI":
                return EconomyAPI::getInstance()->myMoney($name);
                break;

            case "MixCoinSystem":
                return MixCoinSystem::getInstance()->GetCoin($name);
                break;

            case "MoneySystem":
                return API::getInstance()->get($p);
                break;
        }
    }

    public function cutMoney(Player $p, Int $money)
    {
        $plugin_economy = $this->plugin->config->get("プラグイン");
        $name = $p->getName();

        switch ($plugin_economy) {
            case "EconomyAPI":
                EconomyAPI::getInstance()->reduceMoney($name, $money);
                break;

            case "MixCoinSystem":
                MixCoinSystem::getInstance()->MinusCoin($name,$money);
                break;

            case "MoneySystem":
                API::getInstance()->reduce($p, $money, "SLOT", "スロット料金");
                break;
        }
    }
}