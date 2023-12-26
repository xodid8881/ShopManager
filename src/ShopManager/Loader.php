<?php

declare(strict_types=1);

namespace ShopManager;

use JsonException;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use ReflectionException;
use ShopManager\Commands\ShopCommand;
use ShopManager\Commands\ShopSettingCommand;
use ShopManager\Commands\ShopAllSellCommand;
use function strtolower;

final class Loader extends PluginBase{
    use SingletonTrait;

    private ShopManager $api;

    protected function onLoad() : void{
        self::setInstance($this);
    }

    /**
     * @throws ReflectionException
     */
    protected function onEnable() : void{
        if(!InvMenuHandler::isRegistered()){
            InvMenuHandler::register($this);
        }

        $this->api = new ShopManager(
            player: new Config ($this->getDataFolder() . "players.yml", Config::YAML),
            shop: new Config ($this->getDataFolder() . "shops.yml", Config::YAML)
        );

        $server = $this->getServer();
        $cmdMap = $server->getCommandMap();
        $cmdMap->register('ShopManager', new ShopCommand());
        $cmdMap->register('ShopManager', new ShopSettingCommand());
        $cmdMap->register('ShopManager', new ShopAllSellCommand());
        $server->getPluginManager()->registerEvent(PlayerJoinEvent::class, function(PlayerJoinEvent $event) : void {
            $player = $event->getPlayer();
            $name = $player->getName();
            if(!isset($this->api->pldb [strtolower($name)])){
                $this->api->pldb [strtolower($name)] ["상점"] = "없음";
                $this->api->pldb [strtolower($name)] ["Page"] = 0;
                $this->api->save();
            }
        }, EventPriority::MONITOR, $this);
    }

    /**
     * @throws JsonException
     */
    protected function onDisable() : void{
        $this->api->save();
    }
}