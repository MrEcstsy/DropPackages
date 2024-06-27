<?php

namespace ecstsy\DropPackages\Utils;

use ecstsy\DropPackages\Loader;
use ecstsy\DropPackages\Utils\Utils;
use muqsit\customsizedinvmenu\CustomSizedInvMenu;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;

class DropPackages {

    public static array $playerSessions = [];

    private static array $lootTables = [];

    public static function init(): void {
        $files = glob(__DIR__ . '/dp/*.yml');
        foreach ($files as $file) {
            $config = Utils::getConfiguration(Loader::getInstance(), $file)->getAll();
            $lootTableName = basename($file, '.yml');
            foreach ($config['rewards'] as $rarity => $items) {
                self::$lootTables[$lootTableName][$rarity] = Utils::setupRewards($items);
            }
        }
    }

    public static function openDropPackage(Player $player, string $dptype): InvMenu {
        self::init();
        $filePath = Server::getInstance()->getDataPath() . "plugin_data/DropPackages/dp/{$dptype}.yml";
        $config = yaml_parse_file($filePath);

        $playerName = $player->getName();
        self::$playerSessions[$playerName] = [
            'chosenSlots' => [],
            'inventory' => null
        ];
    
        $menu = CustomSizedInvMenu::create(45);
        $inventory = $menu->getInventory();
    
        $menu->setName(C::colorize($config['settings']['title']));
        $blackPane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem();
        Utils::fillBorders($inventory, $blackPane);
    
        if (!file_exists($filePath)) {
            Loader::getInstance()->getLogger()->error(C::RED . "Drop package configuration not found for type: " . $dptype);
            return $menu;
        }
    
        if ($config === false) {
            Loader::getInstance()->getLogger()->error(C::RED . "Failed to load drop package configuration for type: " . $dptype);
            return $menu;
        }
    
        $wGlassItemString = $config['open-gui']['item'] ?? 'minecraft:glass';
        $wGlass = StringToItemParser::getInstance()->parse($wGlassItemString) ?? VanillaItems::GLASS();
    
        $wGlassName = $config['open-gui']['name'] ?? "&r&l&f???&r&7";
        $wGlassLore = $config['open-gui']['lore'] ?? ["&r&7Choose &f5 mystery items &7 and,", "&r&7your &f&lSimple &r&7loot will be revealed."];
    
        $slotsToFill = array_merge(range(10, 16), range(19, 25), range(28, 34));
        foreach ($slotsToFill as $index => $slot) {
            $wGlassInstance = clone $wGlass;
            $wGlassInstance->setCustomName(C::colorize(str_replace("{SLOT}", ($index + 1), $wGlassName)));
            $wGlassInstance->setLore(array_map([C::class, 'colorize'], $wGlassLore));
            $inventory->setItem($slot, $wGlassInstance);
        }
    
        self::$playerSessions[$playerName]['inventory'] = $inventory;

        $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) use ($player, $slotsToFill, $wGlass, $blackPane, $config, $dptype): void {
            $playerName = $player->getName();
        
            if (!isset(self::$playerSessions[$playerName])) {
                return;
            }
        
            $settings = $config['settings'];
            $redeemableItems = $settings['redeemable-items'] ?? 5;
            $borderName = Utils::getConfiguration(Loader::getInstance(), "config.yml")->get("border-name", " "); 
        
            $itemClicked = $transaction->getItemClicked();
            $slot = $transaction->getAction()->getSlot();
        
            if ($itemClicked->getTypeId() === $wGlass->getTypeId() && count(self::$playerSessions[$playerName]['chosenSlots']) < $redeemableItems) {
                $selectedItemType = $config['selected']['item'] ?? "chest";
                $selectedSlot = StringToItemParser::getInstance()->parse($selectedItemType);
        
                if ($selectedSlot === null) {
                    $selectedSlot = VanillaBlocks::CHEST()->asItem();
                }
        
                $selectedSlot->setCustomName(C::colorize(str_replace("{SLOT}", (count(self::$playerSessions[$playerName]['chosenSlots']) + 1), $config['selected']['name'])));
                $selectedSlot->setLore(array_map([C::class, 'colorize'], $config['selected']['lore']));
                self::$playerSessions[$playerName]['inventory']->setItem($slot, $selectedSlot);
                self::$playerSessions[$playerName]['chosenSlots'][] = $slot;
        
                if (count(self::$playerSessions[$playerName]['chosenSlots']) === $redeemableItems) {
                    foreach ($slotsToFill as $index) {
                        if (!in_array($index, self::$playerSessions[$playerName]['chosenSlots'])) {
                            self::$playerSessions[$playerName]['inventory']->setItem($index, VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::BLACK())->asItem());
                        }
                    }
                }
            } elseif ($itemClicked->getTypeId() === $wGlass->getTypeId() && count(self::$playerSessions[$playerName]['chosenSlots']) < $redeemableItems) {
                self::$playerSessions[$playerName]['inventory']->setItem($slot, $wGlass);
                self::$playerSessions[$playerName]['chosenSlots'] = array_diff(self::$playerSessions[$playerName]['chosenSlots'], [$slot]);
            } elseif ($itemClicked->getTypeId() === VanillaBlocks::CHEST()->asItem()->getTypeId() && count(self::$playerSessions[$playerName]['chosenSlots']) === $redeemableItems) {
                $rarityType = "";
                foreach (["simple", "unique", "elite", "ultimate", "legendary"] as $type) {
                    if (strpos($itemClicked->getCustomName(), ucfirst($type)) !== false) {
                        $rarityType = $type;
                        break;
                    }
                }
                $rarity = self::determineRarity($dptype, $rarityType);
                $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor($rarity['color'])->asItem();
                $pane->setCustomName(C::colorize($rarity['name']));
                $pane->setLore(array_map([C::class, 'colorize'], $rarity['lore']));
                self::$playerSessions[$playerName]['inventory']->setItem($slot, $pane);
            } elseif ($itemClicked->getTypeId() === VanillaBlocks::STAINED_GLASS_PANE()->asItem()->getTypeId() && count(self::$playerSessions[$playerName]['chosenSlots']) === $redeemableItems && $itemClicked->getCustomName() !== $borderName) {
                $rarityType = "";
                foreach (["simple", "unique", "elite", "ultimate", "legendary"] as $type) {
                    if (strpos($itemClicked->getCustomName(), ucfirst($type)) !== false) {
                        $rarityType = $type;
                        break;
                    }
                }
                $item = self::getRandomItemFromLootTable($dptype, $rarityType, $player);
                self::$playerSessions[$playerName]['inventory']->setItem($slot, $item);
            }
            
        }));

        $settings = $config['settings'];
        $redeemableItems = $settings['redeemable-items'] ?? 5;

        $menu->setInventoryCloseListener(function () use ($playerName, $config, $redeemableItems, $dptype) {
            $player = Server::getInstance()->getPlayerExact($playerName);
            if ($player === null) {
                unset(self::$playerSessions[$playerName]);
                return;
            }
        
            if (count(self::$playerSessions[$playerName]['chosenSlots']) !== $redeemableItems) {
                for ($i = 0; $i < $redeemableItems; $i++) {
                    $item = DropPackages::getRandomItemFromLootTable($dptype, "legendary", $player); 
                    if ($item !== null) {
                        if (!$player->getInventory()->canAddItem($item)) {
                            $player->getWorld()->dropItem($player->getLocation()->asVector3(), $item);
                            continue;
                        }
                        $player->getInventory()->addItem($item);
                    }
                }
            } else {
                foreach (self::$playerSessions[$playerName]['chosenSlots'] as $slot) {
                    $item = self::$playerSessions[$playerName]['inventory']->getItem($slot);
                    if ($item->getTypeId() !== VanillaItems::AIR()->getTypeId()) {
                        if (!$player->getInventory()->canAddItem($item)) {
                            $player->getWorld()->dropItem($player->getLocation()->asVector3(), $item);
                            continue;
                        }
                        $player->getInventory()->addItem($item);
                    }
                }
            }
        
            unset(self::$playerSessions[$playerName]);
        });
        
        return $menu;
        
        

        return $menu;
    }    

    public static function getRandomItemFromLootTable(string $chestType, string $itemRarity, Player $player): ?Item {
        $filePath = Server::getInstance()->getDataPath() . "plugin_data/DropPackages/dp/{$chestType}.yml";
    
        if (!file_exists($filePath)) {
            return null;
        }
    
        $config = yaml_parse_file($filePath);
    
        if (!$config['rewards'][$itemRarity]) {
            return null;
        }
    
        $lootTable = $config['rewards'][$itemRarity];
    
        $randomIndex = array_rand($lootTable);
        $itemData = $lootTable[$randomIndex];
    
        $items = Utils::setupRewards([$itemData], $player);
    
        foreach ($items as $item) {
            if ($item instanceof Item) {
                return $item;
            }
        }
    
        return null;
    }

    public static function determineRarity(string $lootTableType): array {
        $rand = mt_rand(1, 100);
        if ($rand <= 25) {
            return ['name' => "&r&l&fSimple Mystery Item", 'color' => DyeColor::WHITE(), "lore" => ["&r&7Click here to reveal a", "&r&fSimple &7item from the " . self::translateStrToColor($lootTableType) . ucfirst($lootTableType), "&r&7loot table."], "type" => "simple"];
        } elseif ($rand <= 45) {
            return ['name' => "&r&l&aUnique Mystery Item", 'color' => DyeColor::LIME(), "lore" => ["&r&7Click here to reveal a", "&r&aUnique &7item from the " . self::translateStrToColor($lootTableType) . ucfirst($lootTableType), "&r&7loot table."], "type" => "unique"];
        } elseif ($rand <= 60) {
            return ['name' => "&r&l&bElite Mystery Item", 'color' => DyeColor::LIGHT_BLUE(), "lore" => ["&r&7Click here to reveal a", "&r&bElite &7item from the &fSimple" . self::translateStrToColor($lootTableType) . ucfirst($lootTableType), "&r&7loot table."], "type" => "elite"];
        } elseif ($rand <= 70) {
            return ['name' => "&r&l&eUltimate Mystery Item", 'color' => DyeColor::YELLOW(), "lore" => ["&r&7Click here to reveal a", "&r&eUltimate &7item from the &fSimple" . self::translateStrToColor($lootTableType) . ucfirst($lootTableType), "&r&7loot table."], "type" => "ultimate"];
        } else {
            return ['name' => "&r&l&6Legendary Mystery Item", 'color' => DyeColor::ORANGE(), "lore" => ["&r&7Click here to reveal a", "&r&6Legendary &7item from the " . self::translateStrToColor($lootTableType) . ucfirst($lootTableType), "&r&7loot table."], "type" => "legendary"];
        }
    }
    
    public static function translateStrToColor(string $str): string {
        $str = strtolower($str);
        if ($str === "simple") {
            return "§f";
        } elseif ($str === "unique") {
            return "§a";
        } elseif ($str === "elite") {
            return "§b";
        } elseif ($str === "ultimate") {
            return "§e";
        } else {
            return "§6";
        }
    }
}