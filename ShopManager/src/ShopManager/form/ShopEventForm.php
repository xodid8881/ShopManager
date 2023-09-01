<?php

declare(strict_types=1);

namespace ShopManager\form;

use pocketmine\form\Form;
use pocketmine\player\Player;
use ShopManager\ShopManager;
use function strtolower;

use MoneyManager\MoneyManager;

use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;

final class ShopEventForm implements Form{

    private static BigEndianNbtSerializer $serializer;

    public function jsonSerialize() : array{
        return [
            'type' => 'custom_form',
            'title' => '§l§b[상점]§r§7',
            'content' => [
				[
					'type' => 'dropdown',
					'text' => "제작할 이벤트를 선택해주세요.",
					"options" => ["구매","판매"]
				],
                [
                    'type' => 'input',
                    'text' => "갯수를 적어주세요."
                ]
            ]
        ];
    }

    public function handleResponse(Player $player, $data) : void{
        if($data === null) return;
        if(!isset($data[1])){
            $player->sendMessage(ShopManager::TAG . '빈칸을 채워주세요.');
            return;
        }
        $name = $player->getName();
        $api = ShopManager::getInstance();
        $shopname = $api->pldb [strtolower($name)] ["상점"];
        $nbt = $api->pldb [strtolower($name)] ["상점물품"];
        $buymoney = $api->shopdb [$shopname] ["물품"] [$nbt] ["구매가"];
        $sellmoney = $api->shopdb [$shopname] ["물품"] [$nbt] ["판매가"];
        switch($data[0]) {

            case 0 :
            $name = $player->getName();
            $mymoney = MoneyManager::getInstance ()->getMoney ($name);

            $shopname = $api->pldb [strtolower($name)] ["상점"];
            $nbt = $api->pldb [strtolower($name)] ["상점물품"];
            $buymoney = $api->shopdb [$shopname] ["물품"] [$nbt] ["구매가"];

            $koreamymoney = MoneyManager::getInstance ()->getKoreanMoney ($mymoney);
            $koreambuymoney = MoneyManager::getInstance ()->getKoreanMoney ($buymoney*$data[1]);
            if ($buymoney == 0){
                $player->sendMessage (ShopManager::TAG . "해당 물품은 구매가 금지된 물품입니다." );
            }
            if ( $mymoney < $buymoney*$data[1]) {
                $player->sendMessage (ShopManager::TAG . "돈이 부족해 구매하지 못합니다." );
                $player->sendMessage (ShopManager::TAG . "당신의 돈 : {$koreamymoney}\n물품 총 가격 : {$koreambuymoney}" );
            } else {
                self::$serializer = new BigEndianNbtSerializer();
                $item = Item::nbtDeserialize(self::$serializer->read($nbt)->mustGetCompoundTag());
                $itemcount = $item->getCount();
                $count = $itemcount*$data[1];

                if (!$player->getInventory()->canAddItem($item->setCount((int)$count))) {
                    $player->sendMessage (ShopManager::TAG . "인벤토리에 공간이 부족합니다." );
                    $player->sendTip(ShopManager::TAG . "인벤토리에 공간이 부족합니다.");
                    $event->cancel();
                    return;
                }
                $player->getInventory ()->addItem ( $item->setCount((int)$count) );
                MoneyManager::getInstance ()->sellMoney ($name, $buymoney*$data[1]);
                $player->sendMessage (ShopManager::TAG . "정상적으로 구매가 완료되었습니다." );
                $player->sendMessage (ShopManager::TAG . "구매에 사용된 총 금액 : {$koreambuymoney}" );
            }
            break;
            case 1 :
            $name = $player->getName();
            $mymoney = MoneyManager::getInstance ()->getMoney ($name);

            $shopname = $api->pldb [strtolower($name)] ["상점"];
            $nbt = $api->pldb [strtolower($name)] ["상점물품"];
            $sellmoney = $api->shopdb [$shopname] ["물품"] [$nbt] ["판매가"];

            $koreamymoney = MoneyManager::getInstance ()->getKoreanMoney ($mymoney);
            $koreamsellmoney = MoneyManager::getInstance ()->getKoreanMoney ($sellmoney*$data[1]);
            if ($sellmoney == 0){
                $player->sendMessage (ShopManager::TAG . "해당 물품은 판매가 금지된 물품입니다." );
            }
            self::$serializer = new BigEndianNbtSerializer();
            $item = Item::nbtDeserialize(self::$serializer->read($nbt)->mustGetCompoundTag());
            $itemcount = $item->getCount();
            $count = $itemcount*$data[1];
            if ($player->getInventory ()->contains ( $item->setCount((int)$count) )){
                $player->getInventory ()->removeItem ( $item->setCount($count) );
                MoneyManager::getInstance ()->addMoney ($name, $sellmoney*$data[1]);
                $player->sendMessage (ShopManager::TAG . "정상적으로 판매가 완료되었습니다." );
                $player->sendMessage (ShopManager::TAG . "구매에 사용된 총 금액 : {$koreamsellmoney}" );
            } else {
                $player->sendMessage (ShopManager::TAG . "판매할 물품의 갯수가 부족합니다." );
            }
            break;
        }
    }
}