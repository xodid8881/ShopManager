<?php
declare(strict_types=1);

namespace ShopManager\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use ShopManager\ShopManager;
use MoneyManager\MoneyManager;

final class ShopAllSellCommand extends Command{

    private static BigEndianNbtSerializer $serializer;

    private ShopManager $api;

    /**
     * @var string[]
     * @phpstan-var  array<string, string>
     */
    private array $chat = [];

    public function __construct(){
        parent::__construct('판매전체', '판매전체 명령어 입니다.', '/판매전체');
        $this->setPermission(DefaultPermissions::ROOT_USER);
        $this->api = ShopManager::getInstance();
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
        if(!$sender instanceof Player) return true;
        $name = $sender->getName();
        $money = 0;
        foreach($this->api->shopdb ["프리셋정보"] as $shopname => $v){
            foreach($this->api->shopdb ["프리셋정보"] [$shopname] as $page => $v){
                foreach($this->api->shopdb ["프리셋정보"] [$shopname] [$page] as $i => $v){
                    $nbt = $this->api->shopdb ["프리셋정보"] [$shopname] [$page] [$i];
                    if (isset($this->api->shopdb [$shopname] ["물품"] [$nbt])){
                        $sellmoney = (int)$this->api->shopdb [$shopname] ["물품"] [$nbt] ["판매가"];
                        self::$serializer = new BigEndianNbtSerializer();
                        $item = Item::nbtDeserialize(self::$serializer->read($nbt)->mustGetCompoundTag());
                        if (is_numeric ($sellmoney)) {
                            if ($sellmoney > 0) {
                                $i = 0;
                                while ($i != 1){
                                    if ($sender->getInventory ()->contains ( $item )){
                                        MoneyManager::getInstance()->addMoney ($name, $sellmoney);
                                        $money += $sellmoney;
                                        $sender->getInventory()->removeItem($item);
                                    } else {
                                        ++$i;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $sender->sendMessage (ShopManager::TAG . "모든 물품을 판매했습니다");
        $koreammoney = MoneyManager::getInstance ()->getKoreanMoney ($money);
        $sender->sendMessage (ShopManager::TAG . "{$koreammoney} 원을 얻었습니다.");
        return true;
    }
}
