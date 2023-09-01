<?php

declare(strict_types=1);

namespace ShopManager;

use JsonException;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\Position;

use ShopManager\form\ShopSettingForm;
use ShopManager\form\ShopEventForm;

use function explode;

final class ShopManager{
    use SingletonTrait;

    public array $pldb;
    public array $shopdb;

    private BigEndianNbtSerializer $serializer;

    public function __construct(
        private readonly Config $player,
        private readonly Config $shop,
    ){
        self::setInstance($this);
        $this->pldb = $this->player->getAll();
        $this->shopdb = $this->shop->getAll();
        $this->serializer = new BigEndianNbtSerializer();
    }

    /**
     * @throws JsonException
     */
    public function save() : void{
        $this->player->setAll($this->pldb);
        $this->player->save();
        $this->shop->setAll($this->shopdb);
        $this->shop->save();
    }

    public const TAG = "§c【 §fShop §c】 §7: ";

    public function ShopGUI($player) : void{
        $inv = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $inv->setName("상점");
        $realInv = $inv->getInventory();
        $count = 0;
        if (isset($this->shopdb ["프리셋"])){
            foreach($this->shopdb ["프리셋"] as $shopname => $v){
                if (isset($this->shopdb ["프리셋"] [$shopname])){
                    $nbt = $this->shopdb ["프리셋"] [$shopname];
                    $item = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
                    $item->setCustomName($shopname);
                    $realInv->setItem($count, $item);
                    ++$count;
                }
            }
        }

        $inv->setListener(function(InvMenuTransaction $transaction) use($inv) : InvMenuTransactionResult {
            $itemname = $transaction->getItemClicked()->getCustomName();
            if(isset($this->shopdb [$itemname])){
                $this->pldb [strtolower($transaction->getPlayer()->getName())] ["상점"] = $itemname;
                $inv->onClose($transaction->getPlayer());
                sleep(1);
                $this->ShopEventGUI($transaction->getPlayer(),$itemname);
                return $transaction->discard();
            }elseif($itemname === " "){
                return $transaction->discard();
            }
            return $transaction->continue();
        });
        $inv->send($player);
    }

    public function ShopEventGUI($player,$shopname) : void{
        $inv = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $inv->setName("상점");
        $realInv = $inv->getInventory();
        $count = 0;

        if (!isset($this->shopdb ["프리셋정보"] [$shopname])){
            $this->pldb [strtolower($transaction->getPlayer()->getName())] ["상점"] = $shopname;
            foreach($this->shopdb [$shopname] ["물품"] as $nbt => $v){
                $buymoney = $this->shopdb [$shopname] ["물품"] [$nbt] ["구매가"];
                $sellmoney = $this->shopdb [$shopname] ["물품"] [$nbt] ["판매가"];
                $item = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
                if (!is_null($item->getLore())) {
                    $lore = $item->getLore();
                    $buymoney = (string)$this->shopdb [$shopname] ["물품"] [$nbt] ["구매가"];
                    $sellmoney = (string)$this->shopdb [$shopname] ["물품"] [$nbt] ["판매가"];
                    $Text = "§6§l● §f구매가 §6: §f{$buymoney} §6원\n§6§l● §f판매가 §6: §f{$sellmoney} §6원\n\n§6§l● §f클릭시 §6구매/판매 §f를 이용할 수 있습니다.";
                    $item = $item->setLore ([(string)$Text]);
                    $realInv->setItem($count, $item);
                    ++$count;
                } else {
                    $lore = $item->getLore();
                    $buymoney = (string)$this->shopdb [$shopname] ["물품"] [$nbt] ["구매가"];
                    $sellmoney = (string)$this->shopdb [$shopname] ["물품"] [$nbt] ["판매가"];
                    $Text = "{$lore} \n§6§l● §f구매가 §6: §f{$buymoney} §6원\n§6§l● §f판매가 §6: §f{$sellmoney} §6원\n\n§6§l● §f클릭시 §6구매/판매 §f를 이용할 수 있습니다.";
                    $item = $item->setLore ([(string)$Text]);
                    $realInv->setItem($count, $item);
                    ++$count;
                }
            }
        } else {
            foreach($this->shopdb ["프리셋정보"] [$shopname] as $i => $v){
                $nbt = $this->shopdb ["프리셋정보"] [$shopname] [$i];
                $item = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
                if (!is_null($item->getLore())) {
                    $lore = $item->getLore();
                    if ($this->shopdb [$shopname] ["물품"] [$nbt] ["구매가"] <= 0){
                        $buymoney = "§c구매불가";
                    } else {
                        $buymoney = "{$this->shopdb [$shopname] ["물품"] [$nbt] ["구매가"]} §6원";
                    }
                    if ($this->shopdb [$shopname] ["물품"] [$nbt] ["판매가"] <= 0){
                        $sellmoney = "§c판매불가";
                    } else {
                        $sellmoney = "{$this->shopdb [$shopname] ["물품"] [$nbt] ["판매가"]} §6원";
                    }
                    $Text = "§6§l● §f구매가 §6: §f{$buymoney}\n§6§l● §f판매가 §6: §f{$sellmoney}\n\n§6§l● §f클릭시 §6구매/판매 §f를 이용할 수 있습니다.";
                    $item = $item->setLore ([(string)$Text]);
                    $realInv->setItem($i, $item);
                    ++$count;
                } else {
                    $lore = $item->getLore();
                    if ($this->shopdb [$shopname] ["물품"] [$nbt] ["구매가"] <= 0){
                        $buymoney = "§c구매불가";
                    } else {
                        $buymoney = "{$this->shopdb [$shopname] ["물품"] [$nbt] ["구매가"]} §6원";
                    }
                    if ($this->shopdb [$shopname] ["물품"] [$nbt] ["판매가"] <= 0){
                        $sellmoney = "§c판매불가";
                    } else {
                        $sellmoney = "{$this->shopdb [$shopname] ["물품"] [$nbt] ["판매가"]} §6원";
                    }
                    $Text = "{$lore} \n§6§l● §f구매가 §6: §f{$buymoney}\n§6§l● §f판매가 §6: §f{$sellmoney}\n\n§6§l● §f클릭시 §6구매/판매 §f를 이용할 수 있습니다.";
                    $item = $item->setLore ([(string)$Text]);
                    $realInv->setItem($i, $item);
                    ++$count;
                }
            }
        }

        $inv->setListener(function(InvMenuTransaction $transaction) use($inv) : InvMenuTransactionResult {
            $itemname = $transaction->getItemClicked()->getCustomName();
            if($transaction->getItemClicked() == VanillaItems::AIR()){
                return $transaction->discard();
            }
            if($itemname === " "){
                return $transaction->discard();
            }
            $slot = $transaction->getAction()->getSlot();
            $shopname = $this->pldb [strtolower($transaction->getPlayer()->getName())] ["상점"];
            if (isset($this->shopdb ["프리셋정보"] [$shopname])){
                if (isset($this->shopdb ["프리셋정보"] [$shopname] [$slot])){
                    $nbt = $this->shopdb ["프리셋정보"] [$shopname] [$slot];
                    $this->pldb [strtolower($transaction->getPlayer()->getName())] ["상점"] = $shopname;
                    $this->pldb [strtolower($transaction->getPlayer()->getName())] ["상점물품"] = $nbt;
                    $inv->onClose($transaction->getPlayer());
                    sleep(1);
                    $this->ShopEventUI($transaction->getPlayer());
                    return $transaction->discard();
                }
            }
            return $transaction->continue();
        });
        $inv->send($player);
    }

    public function ShopItemSettingGUI($player,$shopname) : void{
        $inv = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $inv->setName("상점");
        $realInv = $inv->getInventory();
        $count = 0;
        if (!isset($this->shopdb ["프리셋정보"] [$shopname])){
            foreach($this->shopdb [$shopname] ["물품"] as $nbt => $v){
                $item = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
                $realInv->setItem($count, $item);
                ++$count;
            }
        } else {
            foreach($this->shopdb ["프리셋정보"] [$shopname] as $i => $v){
                $nbt = $this->shopdb ["프리셋정보"] [$shopname] [$i];
                $item = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
                $realInv->setItem($i, $item);
            }
        }
        $realInv->setItem(45, VanillaBlocks::MOB_HEAD()->asItem()->setCustomName(" "));
        $realInv->setItem(46, VanillaBlocks::MOB_HEAD()->asItem()->setCustomName(" "));
        $realInv->setItem(47, VanillaBlocks::MOB_HEAD()->asItem()->setCustomName(" "));
        $realInv->setItem(48, VanillaBlocks::MOB_HEAD()->asItem()->setCustomName(" "));
        $realInv->setItem(49, VanillaBlocks::MOB_HEAD()->asItem()->setCustomName(" "));
        $realInv->setItem(50, VanillaBlocks::MOB_HEAD()->asItem()->setCustomName(" "));
        $realInv->setItem(51, VanillaBlocks::MOB_HEAD()->asItem()->setCustomName(" "));
        $realInv->setItem(52, VanillaBlocks::MOB_HEAD()->asItem()->setCustomName(" "));
        $realInv->setItem(53, VanillaBlocks::MOB_HEAD()->asItem()->setCustomName("설정완료")->setLore(["이벤트 이용시 워프세팅 설정완료\n경고 : 이용시 이전 저장정보가 삭제됩니다."]));

        $inv->setListener(function(InvMenuTransaction $transaction) use($inv) : InvMenuTransactionResult {
            $itemname = $transaction->getItemClicked()->getCustomName();
            if($itemname == "설정완료"){
                $shopname = $this->pldb [strtolower($transaction->getPlayer()->getName ())] ["상점"];
                unset($this->shopdb ["프리셋정보"] [$shopname]);
                $i = 0;
                while($i <= 44){
                    $item = $inv->getInventory()->getItem($i);

                    if(!$item->isNull()){
                        $item = $this->serializer->write(new TreeRoot($item->nbtSerialize()));
                        $this->shopdb ["프리셋정보"] [$shopname] [$i] = $item;
                        if (!isset($this->shopdb [$shopname] ["물품"] [$item])){
                            if (!isset($this->shopdb [$shopname] ["물품"] [$item] ["구매가"])){
                                $this->shopdb [$shopname] ["물품"] [$item] ["구매가"] = 0;
                            }
                            if (!isset($this->shopdb [$shopname] ["물품"] [$item] ["판매가"])){
                                $this->shopdb [$shopname] ["물품"] [$item] ["판매가"] = 0;
                            }
                        }
                        $this->save();
                    }
                    ++$i;
                }
                $transaction->getPlayer()->sendMessage(self::TAG. "아이템 설정이 완료되었습니다.");
                $inv->onClose($transaction->getPlayer());
                return $transaction->discard();
            }elseif($itemname === " "){
                return $transaction->discard();
            }
            return $transaction->continue();
        });
        $inv->send($player);
    }

    public function ShopMoneySettingGUI($player,$shopname) : void{
        $inv = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $inv->setName("상점");
        $realInv = $inv->getInventory();
        if (!isset($this->shopdb ["프리셋정보"] [$shopname])){
            foreach($this->shopdb [$shopname] ["물품"] as $nbt => $v){
                $item = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
                $realInv->setItem($count, $item);
                ++$count;
            }
        } else {
            foreach($this->shopdb ["프리셋정보"] [$shopname] as $i => $v){
                $nbt = $this->shopdb ["프리셋정보"] [$shopname] [$i];
                $item = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
                $realInv->setItem($i, $item);
            }
        }

        $inv->setListener(function(InvMenuTransaction $transaction) use($inv) : InvMenuTransactionResult {
            $itemname = $transaction->getItemClicked()->getCustomName();
            $item = $this->serializer->write(new TreeRoot($transaction->getItemClicked()->nbtSerialize()));
            $shopname = $this->pldb [strtolower($transaction->getPlayer()->getName())] ["상점"];
            if(isset($this->shopdb [$shopname] ["물품"] [$item])){
                $shopname = $this->pldb [strtolower($transaction->getPlayer()->getName ())] ["상점"];
                $this->pldb [strtolower($transaction->getPlayer()->getName())] ["상점물품"] = $item;
                $inv->onClose($transaction->getPlayer());
                sleep(1);
                $this->ShopMoneySettingUI($transaction->getPlayer());
                return $transaction->discard();
            }elseif($itemname === " "){
                return $transaction->discard();
            }
            return $transaction->continue();
        });
        $inv->send($player);
    }

    public function ShopMoneySettingUI(Player $player) : void{
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use($player) : void {
            if($player->isOnline()) {
                $this->PlayerSettingUI($player);
            }
        }), 20);
    }

    public function PlayerSettingUI(Player $player) : void{
        $player->sendForm(new ShopSettingForm());
    }

    public function ShopEventUI(Player $player) : void{
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use($player) : void {
            if($player->isOnline()) {
                $this->PlayerEventUI($player);
            }
        }), 20);
    }

    public function PlayerEventUI(Player $player) : void{
        $player->sendForm(new ShopEventForm());
    }

}