<?php
declare(strict_types=1);

namespace ShopManager\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use ShopManager\ShopManager;
use pocketmine\item\VanillaItems;

final class ShopSettingCommand extends Command{

    private static BigEndianNbtSerializer $serializer;

    private ShopManager $api;

    /**
     * @var string[]
     * @phpstan-var array<string, string>
     */
    private array $chat;

    public function __construct(){
        parent::__construct('상점설정', '상점을 관리하는 명령어 합니다.', '/상점설정');
        $this->setPermission(DefaultPermissions::ROOT_OPERATOR);
        $this->api = ShopManager::getInstance();
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
        if(!$sender instanceof Player) return true;
        $name = $sender->getName();
        if(!$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
            $sender->sendMessage(ShopManager::TAG . "권한이 없습니다.");
            return true;
        }
        if(!isset($args[0])){
            $sender->sendMessage(ShopManager::TAG);
            $sender->sendMessage(ShopManager::TAG . "/상점설정 생성 ( 상점이름 ) < 상점을 생성합니다. >");
            $sender->sendMessage(ShopManager::TAG . "/상점설정 물품설정 ( 상점이름 ) < 상점에 물품을 설정합니다. >");
            $sender->sendMessage(ShopManager::TAG . "/상점설정 가격수정 ( 상점이름 ) < 상점 물품의 가격을 수정합니다. >");
            return true;
        }
        switch($args [0]){
            case "생성" :
                if(isset($args[1])){
                    if(isset($this->api->shopdb [$args[1]])){
                        $sender->sendMessage(ShopManager::TAG . "이미 해당 이름으로 상점이 만들어져 있습니다.");
                        return true;
                    }
                    $item = $sender->getInventory()->getItemInHand();
                    if($item == VanillaItems::AIR()){
                        $sender->sendMessage(ShopManager::TAG . "손에 워프 프리셋에 보여줄 아이템을 들고 진행해주세요.");
                        return true;
                    }
                    $this->api->shopdb [$args[1]] = [];
                    $item = $sender->getInventory()->getItemInHand();
                    self::$serializer = new BigEndianNbtSerializer();
                    $item = self::$serializer->write(new TreeRoot($item->nbtSerialize()));
                    $this->api->shopdb ["프리셋"] [$args[1]] = $item;
                    $this->api->shopdb [$args[1]] ["물품"] = [];
                    $sender->sendMessage(ShopManager::TAG . $args[1] . "상점이 생성되었습니다.");
                    return true;
                }else{
                    $sender->sendMessage(ShopManager::TAG . "/상점설정 생성 ( 상점이름 ) < 상점을 생성합니다. >");
                    return true;
                }
                return true;
            case "물품설정" :
                if(isset($args[1])){
                    if(isset($this->api->shopdb [$args[1]])){
                        $this->api->ShopItemSettingGUI($sender,$args[1]);
                        $this->api->pldb [strtolower($name)] ["상점"] = $args[1];
                        return true;
                    } else {
                        $sender->sendMessage(ShopManager::TAG . "해당 이름으로 상점이 존재하지 않습니다.");
                        return true;
                    }
                }else{
                    $sender->sendMessage(ShopManager::TAG . "/상점설정 물품생성 ( 상점이름 ) < 손에든 물품을 상점을 등록합니다. >");
                    return true;
                }
                return true;
            case "가격수정" :
                if(isset($args[1])){
                    if(isset($this->api->shopdb [$args[1]])){
                        $this->api->ShopMoneySettingGUI($sender,$args[1]);
                        $this->api->pldb [strtolower($name)] ["상점"] = $args[1];
                        return true;
                    } else {
                        $sender->sendMessage(ShopManager::TAG . "이미 해당 이름으로 상점이 존재하지 않습니다.");
                        return true;
                    }
                }else{
                    $sender->sendMessage(ShopManager::TAG . "/상점설정 가격수정 ( 상점이름 ) < 상점 물품의 가격을 수정합니다. >");
                    return true;
                }
                return true;
        }
        return true;
    }

}
