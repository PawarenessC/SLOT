<?php

namespace pawarenessc\slot\language;

use pawarenessc\slot\Main;

class Language{

    private $plugin;
    public $lang;

    public static $instance;

    public function __construct(Main $plugin){
        $this->plugin = $plugin;
        $this->lang = $plugin->lang_yaml;
        self::$instance = $this;
    }

    static public function getTranslate(string $string, array $params = []): string {
        $string = self::$instance->lang->get($string);
        foreach($params as $i => $p){
            $string = str_replace("{%$i}", $p, $string);
        }
        return $string;
    }
}
