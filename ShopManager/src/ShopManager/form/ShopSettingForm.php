<?php

declare(strict_types=1);

namespace ShopManager\form;

use pocketmine\form\Form;
use pocketmine\player\Player;
use ShopManager\ShopManager;
use function strtolower;

final class ShopSettingForm implements Form{

    public function jsonSerialize() : array{
        return [
            'type' => 'custom_form',
            'title' => '§l§b[상점]§r§7',
            'content' => [
                [
                    'type' => 'input',
                    'text' => "구매가 를 적어주세요."
                ],
                [
                    'type' => 'input',
                    'text' => "판매가를 적어주세요."
                ]
            ]
        ];
    }

    public function handleResponse(Player $player, $data) : void{
        if($data === null) return;
        if(!isset($data[0])){
            $player->sendMessage(ShopManager::TAG . '빈칸을 채워주세요.');
            return;
        }
        if(!isset($data[1])){
            $player->sendMessage(ShopManager::TAG . '빈칸을 채워주세요.');
            return;
        }

        $name = $player->getName();
        $api = ShopManager::getInstance();
        $shopname = $api->pldb [strtolower($name)] ["상점"];
        $item = $api->pldb [strtolower($name)] ["상점물품"];
        $api->shopdb [$shopname] ["물품"] [$item] ["구매가"] = (int)$data[0];
        $api->shopdb [$shopname] ["물품"] [$item] ["판매가"] = (int)$data[1];
        $api->save();
    }
}