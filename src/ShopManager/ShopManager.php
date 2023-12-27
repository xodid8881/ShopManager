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
use MoneyManager\MoneyManager;

use function explode;

final class ShopManager
{
    use SingletonTrait;

    public array $pldb;
    public array $shopdb;
    public array $livedb;

    private BigEndianNbtSerializer $serializer;

    public function __construct(
        private readonly Config $player,
        private readonly Config $shop,
        private readonly Config $live,
    )
    {
        self::setInstance($this);
        $this->pldb = $this->player->getAll();
        $this->shopdb = $this->shop->getAll();
        $this->livedb = $this->live->getAll();
        $this->serializer = new BigEndianNbtSerializer();
    }

    /**
     * @throws JsonException
     */
    public function save(): void
    {
        $this->player->setAll($this->pldb);
        $this->player->save();
        $this->shop->setAll($this->shopdb);
        $this->shop->save();
        $this->live->setAll($this->livedb);
        $this->live->save();
    }

    public const TAG = "§c【 §fShop §c】 §7: ";
    public function ResetLiveShopConfig()
    {
        foreach ($this->shopdb ["프리셋정보"] as $shopname => $v) {
            foreach ($this->shopdb ["프리셋정보"] [$shopname] as $page => $v) {
                foreach ($this->shopdb ["프리셋정보"] [$shopname] [$page] as $i => $v) {
                    $this->livedb ["프리셋정보"] [$shopname] [$page] [$i] ["구매량"] = 0;
                    $this->livedb ["프리셋정보"] [$shopname] [$page] [$i] ["판매량"] = 0;
                }
            }
        }
    }

    public function LiveShop(Player $player, String $type,String $shopname,Item $item,Int $count)
    {
        $name = strtolower($player->getName ());
        $page = $this->pldb [$name] ["Page"];
        foreach ($this->shopdb ["프리셋정보"] [$shopname] [$page] as $i => $v) {
            $nbt = $this->shopdb ["프리셋정보"] [$shopname] [$page] [$i];
            $shopitem = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
            if ($shopitem == $item){
                if ($type == "purchase") {
                    $this->livedb ["프리셋정보"] [$shopname] [$page] [$i] ["구매량"] += $count;
                } else if ($type == "sale") {
                    $this->livedb ["프리셋정보"] [$shopname] [$page] [$i] ["판매량"] += $count;
                }
            }
        }
    }


    public function ShopGUI(Player $player): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player): void {
            if ($player->isOnline()) {
                $this->ShopMainGUI($player);
            }
        }), 10);
    }

    public function ShopMainGUI(Player $player): void
    {
        $this->ShopMain($player);
    }

    public function ShopMain(Player $player): void
    {
        $inv = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $inv->setName("상점목록");
        $realInv = $inv->getInventory();
        $count = 0;
        if (isset($this->shopdb ["프리셋"])) {
            foreach ($this->shopdb ["프리셋"] as $shopname => $v) {
                if (isset($this->shopdb ["프리셋"] [$shopname])) {
                    $nbt = $this->shopdb ["프리셋"] [$shopname];
                    $item = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
                    $item->setCustomName($shopname);
                    $realInv->setItem($count, $item);
                    ++$count;
                }
            }
        }

        $inv->setListener(function (InvMenuTransaction $transaction) use ($inv): InvMenuTransactionResult {
            $itemname = $transaction->getItemClicked()->getCustomName();
            $name = strtolower($transaction->getPlayer()->getName());
            if (isset($this->shopdb [$itemname])) {
                $this->pldb [$name] ["상점"] = $itemname;
                $inv->onClose($transaction->getPlayer());
                $this->pldb [$name] ["Page"] = 0;
                $this->ShopEventGUI($transaction->getPlayer(), $itemname);
                return $transaction->discard();
            } elseif ($itemname === " ") {
                return $transaction->discard();
            }
            return $transaction->continue();
        });
        $inv->send($player);
    }


    public function ShopEventGUI(Player $player, String $shopname): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $shopname): void {
            if ($player->isOnline()) {
                $this->ShopEvent($player, $shopname);
            }
        }), 10);
    }

    public function ShopEvent(Player $player, String $shopname): void
    {
        $this->Shop($player, $shopname);
    }

    public function Shop(Player $player, String $shopname): void
    {
        $inv = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $inv->setName("상점");
        $realInv = $inv->getInventory();
        $name = strtolower($player->getName ());
        $page = $this->pldb [$name] ["Page"];
        if (isset($this->shopdb ["프리셋정보"] [$shopname] [$page])) {
            foreach ($this->shopdb ["프리셋정보"] [$shopname] [$page] as $i => $v) {
                $nbt = $this->shopdb ["프리셋정보"] [$shopname] [$page] [$i];
                $item = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
                $lore = $item->getLore();
                if ($this->shopdb [$shopname] ["물품"] [$nbt] ["구매가"] <= 0) {
                    $buymoney = "§c구매불가";
                } else {
                    $buymoney = MoneyManager::getInstance()->getKoreanMoney($this->shopdb [$shopname] ["물품"] [$nbt] ["구매가"]);
                }
                if ($this->shopdb [$shopname] ["물품"] [$nbt] ["판매가"] <= 0) {
                    $sellmoney = "§c판매불가";
                } else {
                    $sellmoney = MoneyManager::getInstance()->getKoreanMoney($this->shopdb [$shopname] ["물품"] [$nbt] ["판매가"]);
                }
                $PurchaseVolume = $this->livedb ["프리셋정보"] [$shopname] [$page] [$i] ["구매량"];
                $SalesRate = $this->livedb ["프리셋정보"] [$shopname] [$page] [$i] ["판매량"];
                $lore[] =
                    "§6§l● §f구매가 §6: §f{$buymoney}\n".
                    "§6§l● §f판매가 §6: §f{$sellmoney}\n\n".
                    "§6● §f실시간 1시간\n".
                    "§f구매량 §6: §7{$PurchaseVolume}\n".
                    "§f판매량 §6: §7{$SalesRate}\n\n".
                    "§6§l● §f클릭시 §6구매/판매 §f를 이용할 수 있습니다.";
                $item = $item->setLore($lore);

                $realInv->setItem($i, $item);
            }
        }

        $item = VanillaItems::DISC_FRAGMENT_5();
        $realInv->setItem(46, $item->setCustomName("준비중"));
        $realInv->setItem(47, $item->setCustomName("이전페이지"));
        $realInv->setItem(48, $item->setCustomName("이전페이지"));
        $realInv->setItem(49, $item->setCustomName("나가기"));
        $realInv->setItem(50, $item->setCustomName("다음페이지"));
        $realInv->setItem(51, $item->setCustomName("다음페이지"));
        $realInv->setItem(52, $item->setCustomName("준비중"));

        $inv->setListener(function (InvMenuTransaction $transaction) use ($inv): InvMenuTransactionResult {
            $itemname = $transaction->getItemClicked()->getCustomName();
            $name = strtolower($transaction->getPlayer()->getName());
            if ($transaction->getItemClicked() == VanillaItems::AIR()) {
                return $transaction->discard();
            }
            if ($itemname === "준비중") {
                return $transaction->discard();
            }
            if ($itemname === "이전페이지") {
                $inv->onClose($transaction->getPlayer());
                $this->pldb [$name] ["Page"] -= 1;
                $shopname = $this->pldb [$name] ["상점"];
                $this->ShopEventGUI($transaction->getPlayer(), $shopname);
                return $transaction->discard();
            }
            if ($itemname === "나가기") {
                $inv->onClose($transaction->getPlayer());
                return $transaction->discard();
            }
            if ($itemname === "다음페이지") {
                $inv->onClose($transaction->getPlayer());
                $this->pldb [$name] ["Page"] += 1;
                $shopname = $this->pldb [$name] ["상점"];
                $this->ShopEventGUI($transaction->getPlayer(), $shopname);
                return $transaction->discard();
            }
            if ($itemname === "준비중") {
                return $transaction->discard();
            }
            $slot = $transaction->getAction()->getSlot();
            $shopname = $this->pldb [$name] ["상점"];
            $page = $this->pldb [$name] ["Page"];
            if (isset($this->shopdb ["프리셋정보"] [$shopname] [$page])) {
                if (isset($this->shopdb ["프리셋정보"] [$shopname] [$page] [$slot])) {
                    $nbt = $this->shopdb ["프리셋정보"] [$shopname] [$page] [$slot];
                    $this->pldb [$name] ["상점"] = $shopname;
                    $this->pldb [$name] ["상점물품"] = $nbt;
                    $inv->onClose($transaction->getPlayer());
                    $this->ShopBuySellEventGUI($transaction->getPlayer());
                    return $transaction->discard();
                }
            }
            return $transaction->continue();
        });
        $inv->send($player);
    }


    public function ShopItemSettingGUI(Player $player, String $shopname): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $shopname): void {
            if ($player->isOnline()) {
                $this->ShopItemSetting($player, $shopname);
            }
        }), 10);
    }

    public function ShopItemSetting(Player $player, String $shopname): void
    {
        $this->ShopItem($player, $shopname);
    }

    public function ShopItem(Player $player, String $shopname): void
    {
        $inv = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $inv->setName("상점");
        $realInv = $inv->getInventory();
        $name = $player->getName();
        $count = 0;
        $page = $this->pldb [strtolower($name)] ["Page"];
        if (isset($this->shopdb ["프리셋정보"] [$shopname] [$page])) {
            foreach ($this->shopdb ["프리셋정보"] [$shopname] [$page] as $i => $v) {
                $nbt = $this->shopdb ["프리셋정보"] [$shopname] [$page] [$i];
                $item = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
                $realInv->setItem($i, $item);
            }
        }
        $item = VanillaItems::DISC_FRAGMENT_5();
        $realInv->setItem(46, $item->setCustomName("준비중"));
        $realInv->setItem(47, $item->setCustomName("이전페이지"));
        $realInv->setItem(48, $item->setCustomName("이전페이지"));
        $realInv->setItem(49, $item->setCustomName("나가기"));
        $realInv->setItem(50, $item->setCustomName("다음페이지"));
        $realInv->setItem(51, $item->setCustomName("다음페이지"));
        $realInv->setItem(52, $item->setCustomName("준비중"));
        $realInv->setItem(53, VanillaBlocks::CHEST()->asItem()->setCustomName("설정완료")->setLore(["이벤트 이용시 상점 {$page}Page 프리셋 설정완료\n경고 : 이용시 이전 저장정보가 삭제됩니다."]));

        $inv->setListener(function (InvMenuTransaction $transaction) use ($inv): InvMenuTransactionResult {
            $itemname = $transaction->getItemClicked()->getCustomName();
            $name = strtolower($transaction->getPlayer()->getName());
            if ($itemname === "준비중") {
                return $transaction->discard();
            }
            if ($itemname === "이전페이지") {
                $inv->onClose($transaction->getPlayer());
                $this->pldb [$name] ["Page"] -= 1;
                $shopname = $this->pldb [$name] ["상점"];
                $this->ShopItemSettingGUI($transaction->getPlayer(), $shopname);
                return $transaction->discard();
            }
            if ($itemname === "나가기") {
                return $transaction->discard();
            }
            if ($itemname === "다음페이지") {
                $inv->onClose($transaction->getPlayer());
                $this->pldb [$name] ["Page"] += 1;
                $shopname = $this->pldb [$name] ["상점"];
                $this->ShopItemSettingGUI($transaction->getPlayer(), $shopname);
                return $transaction->discard();
            }
            if ($itemname === "준비중") {
                return $transaction->discard();
            }
            if ($itemname == "설정완료") {
                $shopname = $this->pldb [$name] ["상점"];
                $page = $this->pldb [$name] ["Page"];
                unset($this->shopdb ["프리셋정보"] [$shopname] [$page]);
                $i = 0;
                while ($i <= 44) {
                    $item = $inv->getInventory()->getItem($i);

                    if (!$item->isNull()) {
                        $item = $this->serializer->write(new TreeRoot($item->nbtSerialize()));
                        $this->shopdb ["프리셋정보"] [$shopname] [$page] [$i] = $item;
                        if (!isset($this->shopdb [$shopname] ["물품"] [$item])) {
                            if (!isset($this->shopdb [$shopname] ["물품"] [$item] ["구매가"])) {
                                $this->shopdb [$shopname] ["물품"] [$item] ["구매가"] = 0;
                            }
                            if (!isset($this->shopdb [$shopname] ["물품"] [$item] ["판매가"])) {
                                $this->shopdb [$shopname] ["물품"] [$item] ["판매가"] = 0;
                            }
                            $this->livedb ["프리셋정보"] [$shopname] [$page] [$i] ["구매량"] = 0;
                            $this->livedb ["프리셋정보"] [$shopname] [$page] [$i] ["판매량"] = 0;
                        }
                        $this->save();
                    }
                    ++$i;
                }
                $transaction->getPlayer()->sendMessage(self::TAG . "아이템 설정이 완료되었습니다.");
                return $transaction->discard();
            } elseif ($itemname === " ") {
                return $transaction->discard();
            }
            return $transaction->continue();
        });
        $inv->send($player);
    }

    public function ShopMoneySettingGUI(Player $player, String $shopname): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $shopname): void {
            if ($player->isOnline()) {
                $this->ShopMoneySetting($player, $shopname);
            }
        }), 10);
    }

    public function ShopMoneySetting(Player $player, String $shopname): void
    {
        $this->ShopMoney($player, $shopname);
    }

    public function ShopMoney(Player $player, String $shopname): void
    {
        $inv = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $inv->setName("상점");
        $realInv = $inv->getInventory();
        $name = $player->getName();

        $page = $this->pldb [strtolower($name)] ["Page"];
        if (isset($this->shopdb ["프리셋정보"] [$shopname] [$page])) {
            foreach ($this->shopdb ["프리셋정보"] [$shopname] [$page] as $i => $v) {
                $nbt = $this->shopdb ["프리셋정보"] [$shopname] [$page] [$i];
                $item = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
                $lore = $item->getLore();
                if ($this->shopdb [$shopname] ["물품"] [$nbt] ["구매가"] <= 0) {
                    $buymoney = "§c구매불가";
                } else {
                    $buymoney = MoneyManager::getInstance()->getKoreanMoney($this->shopdb [$shopname] ["물품"] [$nbt] ["구매가"]);
                }
                if ($this->shopdb [$shopname] ["물품"] [$nbt] ["판매가"] <= 0) {
                    $sellmoney = "§c판매불가";
                } else {
                    $sellmoney = MoneyManager::getInstance()->getKoreanMoney($this->shopdb [$shopname] ["물품"] [$nbt] ["판매가"]);
                }
                $lore[] = "§6§l● §f구매가 §6: §f{$buymoney}\n§6§l● §f판매가 §6: §f{$sellmoney}\n\n§6§l● §6구매가/판매가 §f를 변경할 수 있습니다.";
                $item = $item->setLore($lore);

                $realInv->setItem($i, $item);
            }
        }
        $item = VanillaItems::DISC_FRAGMENT_5();
        $realInv->setItem(46, $item->setCustomName("준비중"));
        $realInv->setItem(47, $item->setCustomName("이전페이지"));
        $realInv->setItem(48, $item->setCustomName("이전페이지"));
        $realInv->setItem(49, $item->setCustomName("나가기"));
        $realInv->setItem(50, $item->setCustomName("다음페이지"));
        $realInv->setItem(51, $item->setCustomName("다음페이지"));
        $realInv->setItem(52, $item->setCustomName("준비중"));

        $inv->setListener(function (InvMenuTransaction $transaction) use ($inv): InvMenuTransactionResult {
            $itemname = $transaction->getItemClicked()->getCustomName();
            if ($transaction->getItemClicked() == VanillaItems::AIR()) {
                return $transaction->discard();
            }
            if ($itemname === "준비중") {
                return $transaction->discard();
            }
            if ($itemname === "이전페이지") {
                $inv->onClose($transaction->getPlayer());
                sleep(1);
                $this->pldb [strtolower($transaction->getPlayer()->getName())] ["Page"] -= 1;
                $shopname = $this->pldb [strtolower($transaction->getPlayer()->getName())] ["상점"];
                $this->ShopMoneySettingGUI($transaction->getPlayer(), $shopname);
                return $transaction->discard();
            }
            if ($itemname === "나가기") {
                return $transaction->discard();
            }
            if ($itemname === "다음페이지") {
                $inv->onClose($transaction->getPlayer());
                $this->pldb [strtolower($transaction->getPlayer()->getName())] ["Page"] += 1;
                $shopname = $this->pldb [strtolower($transaction->getPlayer()->getName())] ["상점"];
                $this->ShopMoneySettingGUI($transaction->getPlayer(), $shopname);
                return $transaction->discard();
            }
            if ($itemname === "준비중") {
                return $transaction->discard();
            }
            $slot = $transaction->getAction()->getSlot();
            $shopname = $this->pldb [strtolower($transaction->getPlayer()->getName())] ["상점"];
            $page = $this->pldb [strtolower($transaction->getPlayer()->getName())] ["Page"];
            if (isset($this->shopdb ["프리셋정보"] [$shopname] [$page])) {
                if (isset($this->shopdb ["프리셋정보"] [$shopname] [$page] [$slot])) {
                    $nbt = $this->shopdb ["프리셋정보"] [$shopname] [$page] [$slot];
                    $this->pldb [strtolower($transaction->getPlayer()->getName())] ["상점"] = $shopname;
                    $this->pldb [strtolower($transaction->getPlayer()->getName())] ["상점물품"] = $nbt;
                    $inv->onClose($transaction->getPlayer());
                    $this->ShopMoneySettingUI($transaction->getPlayer());
                    return $transaction->discard();
                }
            } else if ($itemname === " ") {
                return $transaction->discard();
            }
            return $transaction->continue();
        });
        $inv->send($player);
    }

    public function ShopBuySellEventGUI(Player $player): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player): void {
            if ($player->isOnline()) {
                $this->SellCheck($player);
            }
        }), 10);
    }

    public function ShopBuySellEvent(Player $player): void
    {
        $this->ShopBuySell($player);
    }

    public function ShopBuySell(Player $player): void
    {
        $inv = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $inv->setName("상점 구매/판매 창");
        $realInv = $inv->getInventory();

        $name = $player->getName();
        $shopname = $this->pldb [strtolower($name)] ["상점"];
        $nbt = $this->pldb [strtolower($name)] ["상점물품"];
        $sellmoney = $this->shopdb [$shopname] ["물품"] [$nbt] ["판매가"];

        $item = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
        $realInv->setItem(19, $item);

        $item = VanillaItems::DISC_FRAGMENT_5();
        $realInv->setItem(13, $item->setCustomName("1개 판매"));
        $realInv->setItem(14, $item->setCustomName("10개 판매"));
        $realInv->setItem(15, $item->setCustomName("64개 판매"));
        $realInv->setItem(16, $item->setCustomName("전량 판매"));

        $realInv->setItem(31, $item->setCustomName("1개 구매"));
        $realInv->setItem(32, $item->setCustomName("10개 구매"));
        $realInv->setItem(33, $item->setCustomName("64개 구매"));
        $realInv->setItem(34, $item->setCustomName("가득 구매"));

        $realInv->setItem(48, $item->setCustomName("이용완료"));
        $realInv->setItem(49, $item->setCustomName("구매/판매 이용중"));
        $realInv->setItem(50, $item->setCustomName("이전창 이동"));

        $inv->setListener(function (InvMenuTransaction $transaction) use ($inv): InvMenuTransactionResult {
            $itemname = $transaction->getItemClicked()->getCustomName();
            $name = strtolower($transaction->getPlayer()->getName());
            if ($transaction->getItemClicked() == VanillaItems::AIR()) {
                return $transaction->discard();
            }
            if ($itemname === "이용완료") {
                $inv->onClose($transaction->getPlayer());
                return $transaction->discard();
            }
            if ($itemname === "구매/판매 이용중") {
                return $transaction->discard();
            }
            if ($itemname === "이전창 이동") {
                $inv->onClose($transaction->getPlayer());
                $shopname = $this->pldb [$name] ["상점"];
                $this->ShopEventGUI($transaction->getPlayer(), $shopname);
                return $transaction->discard();
            }
            if ($itemname === "1개 판매") {
                $this->SellCheckEvent($transaction->getPlayer(), 1);
                return $transaction->discard();
            }
            if ($itemname === "10개 판매") {
                $this->SellCheckEvent($transaction->getPlayer(), 10);
                return $transaction->discard();
            }
            if ($itemname === "64개 판매") {
                $this->SellCheckEvent($transaction->getPlayer(), 64);
                return $transaction->discard();
            }
            if ($itemname === "전체 판매") {
                $this->SellCheckEvent($transaction->getPlayer(), (string)"All");
                return $transaction->discard();
            }
            if ($itemname === "1개 구매") {
                $this->BuyCheckEvent($transaction->getPlayer(), 1);
                return $transaction->discard();
            }
            if ($itemname === "10개 구매") {
                $this->BuyCheckEvent($transaction->getPlayer(), 10);
                return $transaction->discard();
            }
            if ($itemname === "64개 구매") {
                $this->BuyCheckEvent($transaction->getPlayer(), 64);
                return $transaction->discard();
            }
            if ($itemname === "가득 구매") {
                $this->BuyCheckEvent($transaction->getPlayer(), "All");
                return $transaction->discard();
            }
            return $transaction->discard();
        });
        $inv->send($player);
    }

    public function SellCheckEvent(Player $player, $selldata): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $selldata): void {
            if ($player->isOnline()) {
                $this->SellCheck($player, $selldata);
            }
        }), 10);
    }

    public function SellCheck(Player $player, $selldata): void
    {
        $this->Sell($player, $selldata);
    }

    public function Sell(Player $player, $selldata): void
    {
        $name = strtolower($player->getName());
        $shopname = $this->pldb [$name] ["상점"];
        $nbt = $this->pldb [$name] ["상점물품"];
        $sellmoney = $this->shopdb [$shopname] ["물품"] [$nbt] ["판매가"];

        $clickitem = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
        $itemcount = $clickitem->getCount();
        if ($selldata != "All") {
            $koreamsellmoney = MoneyManager::getInstance()->getKoreanMoney($sellmoney * $selldata);
            $count = $itemcount * $selldata;
            if ($sellmoney == 0) {
                $player->sendMessage(self::TAG . "해당 물품은 판매가 금지된 물품입니다.");
                return;
            }
            if ($player->getInventory()->contains($clickitem->setCount((int)$count))) {
                $player->getInventory()->removeItem($clickitem->setCount((int)$count));
                MoneyManager::getInstance()->addMoney($name, $sellmoney * $selldata);
                $player->sendMessage(self::TAG . "정상적으로 판매가 완료되었습니다.");
                $player->sendMessage(self::TAG . "판매후 얻은 총 금액 : {$koreamsellmoney}");
                $this->LiveShop ($player,"sale",$shopname,$clickitem,$count);
                return;
            } else {
                $player->sendMessage(self::TAG . "판매할 물품의 갯수가 부족합니다.");
                return;
            }
        } else {
            $i = 0;
            $count = 0;
            while ($i == 0) {
                $clickitem = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
                if ($player->getInventory()->contains($clickitem)) {
                    $player->getInventory()->removeItem($clickitem);
                    ++$count;
                } else {
                    ++$i;
                    MoneyManager::getInstance()->addMoney($name, $sellmoney * $count);
                    $koreamsellmoney = MoneyManager::getInstance()->getKoreanMoney($sellmoney * $count);
                    $player->sendMessage(self::TAG . "정상적으로 전량 판매가 완료되었습니다.");
                    $player->sendMessage(self::TAG . "판매후 얻은 총 금액 : {$koreamsellmoney}");
                    $this->LiveShop ($player,"sale",$shopname,$clickitem,$count);
                }
            }
        }
    }

    public function BuyCheckEvent(Player $player, $buydata): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $buydata): void {
            if ($player->isOnline()) {
                $this->BuyCheck($player, $buydata);
            }
        }), 10);
    }

    public function BuyCheck(Player $player, $buydata): void
    {
        $this->Buy($player, $buydata);
    }

    public function Buy(Player $player, $buydata): void
    {
        $name = strtolower($player->getName());
        $mymoney = MoneyManager::getInstance()->getMoney($name);

        $shopname = $this->pldb [$name] ["상점"];
        $nbt = $this->pldb [$name] ["상점물품"];
        $buymoney = $this->shopdb [$shopname] ["물품"] [$nbt] ["구매가"];

        $clickitem = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
        $itemcount = $clickitem->getCount();
        if ($buydata != "All") {
            $koreamymoney = MoneyManager::getInstance()->getKoreanMoney($mymoney);
            $koreambuymoney = MoneyManager::getInstance()->getKoreanMoney($buymoney * $buydata);
            $count = $itemcount * $buydata;
            if ($buymoney == 0) {
                $player->sendMessage(self::TAG . "해당 물품은 구매가 금지된 물품입니다.");
                return;
            }
            if ($mymoney < $buymoney * $buydata) {
                $player->sendMessage(self::TAG . "돈이 부족해 구매하지 못합니다.");
                $player->sendMessage(self::TAG . "당신의 돈 : {$koreamymoney}\n물품 총 가격 : {$koreambuymoney}");
                return;
            } else {
                if (!$player->getInventory()->canAddItem($clickitem->setCount((int)$count))) {
                    $player->sendMessage(self::TAG . "인벤토리에 공간이 부족합니다.");
                    $player->sendTip(self::TAG . "인벤토리에 공간이 부족합니다.");
                    return;
                }
                $player->getInventory()->addItem($clickitem->setCount((int)$count));
                MoneyManager::getInstance()->sellMoney($name, $buymoney * $buydata);
                $player->sendMessage(self::TAG . "정상적으로 구매가 완료되었습니다.");
                $player->sendMessage(self::TAG . "구매에 사용된 총 금액 : {$koreambuymoney}");
                $this->LiveShop ($player,"purchase",$shopname,$clickitem,$count);
                return;
            }
        } else {
            if ($buymoney == 0) {
                $player->sendMessage(self::TAG . "해당 물품은 구매가 금지된 물품입니다.");
                return;
            }
            $i = 0;
            $count = 0;
            while ($i == 0) {
                $clickitem = Item::nbtDeserialize($this->serializer->read($nbt)->mustGetCompoundTag());
                $mymoney = MoneyManager::getInstance()->getMoney($name);
                if ($mymoney < $buymoney) {
                    ++$i;
                    $koreambuymoney = MoneyManager::getInstance()->getKoreanMoney($buymoney * $count);
                    $player->sendMessage(self::TAG . "정상적으로 가득 구매가 완료되었습니다.");
                    $player->sendMessage(self::TAG . "구매에 사용된 총 금액 : {$koreambuymoney}");
                } else {
                    if ($player->getInventory()->canAddItem($clickitem)) {
                        MoneyManager::getInstance()->sellMoney($name, $buymoney);
                        $player->getInventory()->addItem($clickitem);
                        ++$count;
                    } else {
                        $koreambuymoney = MoneyManager::getInstance()->getKoreanMoney($buymoney * $count);
                        $player->sendMessage(self::TAG . "정상적으로 가득 구매가 완료되었습니다.");
                        $player->sendMessage(self::TAG . "구매에 사용된 총 금액 : {$koreambuymoney}");
                        $this->LiveShop ($player,"purchase",$shopname,$clickitem,$count);
                        ++$i;
                    }
                }
            }
        }
    }

    public function ShopMoneySettingUI(Player $player): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player): void {
            if ($player->isOnline()) {
                $this->PlayerSettingUI($player);
            }
        }), 10);
    }

    public function PlayerSettingUI(Player $player): void
    {
        $player->sendForm(new ShopSettingForm());
    }

    public function ShopEventUI(Player $player): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player): void {
            if ($player->isOnline()) {
                $this->PlayerEventUI($player);
            }
        }), 10);
    }

    public function PlayerEventUI(Player $player): void
    {
        $player->sendForm(new ShopEventForm());
    }

}