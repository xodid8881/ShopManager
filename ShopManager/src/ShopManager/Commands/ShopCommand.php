<?php
declare(strict_types=1);

namespace ShopManager\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use ShopManager\ShopManager;

final class ShopCommand extends Command{

    private ShopManager $api;

    /**
     * @var string[]
     * @phpstan-var  array<string, string>
     */
    private array $chat = [];

    public function __construct(){
        parent::__construct('상점', '상점 GUI를 오픈합니다.', '/상점');
        $this->setPermission(DefaultPermissions::ROOT_USER);
        $this->api = ShopManager::getInstance();
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
        if(!$sender instanceof Player) return true;
        $name = $sender->getName();
        if(!isset ($this->chat [$name])){
            $this->api->ShopGUI($sender);
            $this->chat [$name] = date("YmdHis", strtotime("+3 seconds"));
            return true;
        }
        if(date("YmdHis") - $this->chat [$name] < 3){
            $sender->sendMessage(ShopManager::TAG . "이용 쿨타임이 지나지 않아 불가능합니다.");
        }else{
            $this->api->ShopGUI($sender);
            $this->chat [$name] = date("YmdHis", strtotime("+3 seconds"));
        }
        return true;
    }
}
