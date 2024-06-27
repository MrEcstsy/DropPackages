<?php

namespace ecstsy\DropPackages\Utils;

use ecstsy\DropPackages\Loader;
use pocketmine\block\VanillaBlocks;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\inventory\Inventory;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;

class Utils {

    public static function getConfiguration(PluginBase $plugin, string $fileName): Config {
        $pluginFolder = $plugin->getDataFolder();
        $filePath = $pluginFolder . $fileName;

        $config = null;

        if (!file_exists($filePath)) {
            $plugin->getLogger()->warning("Configuration file '$fileName' not found.");
        } else {
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);

            switch ($extension) {
                case 'yml':
                case 'yaml':
                    $config = new Config($filePath, Config::YAML);
                    break;

                case 'json':
                    $config = new Config($filePath, Config::JSON);
                    break;

                default:
                    $plugin->getLogger()->warning("Unsupported configuration file format for '$fileName'.");
                    break;
            }
        }

        return $config;
    }

        /**
     * Returns an online player whose name begins with or equals the given string (case insensitive).
     * The closest match will be returned, or null if there are no online matches.
     *
     * @param string $name The prefix or name to match.
     * @return Player|null The matched player or null if no match is found.
     */
    public static function getPlayerByPrefix(string $name): ?Player {
        $found = null;
        $name = strtolower($name);
        $delta = PHP_INT_MAX;

        /** @var Player[] $onlinePlayers */
        $onlinePlayers = Server::getInstance()->getOnlinePlayers();

        foreach ($onlinePlayers as $player) {
            if (stripos($player->getName(), $name) === 0) {
                $curDelta = strlen($player->getName()) - strlen($name);

                if ($curDelta < $delta) {
                    $found = $player;
                    $delta = $curDelta;
                }

                if ($curDelta === 0) {
                    break;
                }
            }
        }

        return $found;
    }

    
    /**
     * Fill the borders of the inventory with gray glass.
     *
     * @param Inventory $inventory
     */
    public static function fillBorders(Inventory $inventory, Item $glassType, array $excludedSlots = []): void
    {
        $size = $inventory->getSize();
        $rows = intdiv($size, 9); 
    
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < 9; $col++) {
                $slot = $row * 9 + $col;
    
                if (!in_array($slot, $excludedSlots) && ($col === 0 || $col === 8 || $row === 0 || $row === $rows - 1)) {
                    $item = clone $glassType;
                    $item->setCustomName(C::colorize(self::getConfiguration(Loader::getInstance(), "config.yml")->get("border-name")));
                    $inventory->setItem($slot, $item);
                }
            }
        }
    }
    
    public static function createDropPackage(string $type, int $amount = 1): ?Item {
        $type = strtolower($type);
        $filePath = Loader::getInstance()->getDataFolder() . "dp/{$type}.yml";
    
        if (!file_exists($filePath)) {
            return null; 
        }
    
        $config = new Config($filePath, Config::YAML);
        $itemData = $config->get("item", []);
    
        if (!isset($itemData['item'])) {
            return null; 
        }
    
        $itemType = $itemData['item'];
        $item = StringToItemParser::getInstance()->parse($itemType);
        if ($item === null) {
            return null; 
        }
        $item->setCount($amount);
    
        if (isset($itemData['name'])) {
            $item->setCustomName(C::colorize($itemData['name']));
        }
    
        if (isset($itemData['lore']) && is_array($itemData['lore'])) {
            $lore = array_map(fn($line) => C::colorize($line), $itemData['lore']);
            $item->setLore($lore);
        }
    
        $item->getNamedTag()->setString("drop_package", $type);
    
        return $item;
    }

    public static function setupRewards(array $rewardData, ?Player $player = null): array
    {
        $rewards = [];
        $stringToItemParser = StringToItemParser::getInstance();
        
        foreach ($rewardData as $data) {
            if (!isset($data["item"])) {
                continue; 
            }

            $itemString = $data["item"];
            $item = $stringToItemParser->parse($itemString);
            if ($item === null) {
                continue;
            }

            if (isset($data["command"])) {
                $commandString = $data["command"] ?? null;
                if ($commandString !== null) {
                    Server::getInstance()->dispatchCommand(new ConsoleCommandSender(Server::getInstance(), Server::getInstance()->getLanguage()), str_replace("{player}", $player->getName(), $commandString));
                }
            }
    
            $amount = $data["amount"] ?? 1;
            $item->setCount($amount);
    
            $name = $data["name"] ?? null;
            if ($name !== null) {
                $item->setCustomName(C::colorize($name));
            }
    
            $lore = $data["lore"] ?? null;
            if ($lore !== null) {
                $lore = array_map(function ($line) {
                    return C::colorize($line);
                }, $lore);
                $item->setLore($lore);
            }
    
            $enchantments = $data["enchantments"] ?? null;
            if ($enchantments !== null) {
                foreach ($enchantments as $enchantmentData) {
                    $enchantment = $enchantmentData["enchant"] ?? null;
                    $level = $enchantmentData["level"] ?? 1;
                    if ($enchantment !== null) {
                        $item->addEnchantment(new EnchantmentInstance(StringToEnchantmentParser::getInstance()->parse($enchantment)), $level);
                    }
                }
            }
    
            $nbtData = $data["nbt"] ?? null;
            if ($nbtData !== null) {
                $tag = $nbtData["tag"] ?? "";
                $value = $nbtData["value"] ?? "";
            
                if (is_int($value)) {
                    $item->getNamedTag()->setInt($tag, $value);
                } else {
                    $item->getNamedTag()->setString($tag, $value);
                }
            }            
    
            $rewards[] = $item;
        }

        if (isset($data["display-item"])) {
            $displayItemString = $data["display-item"];
            $displayItem = $stringToItemParser->parse($displayItemString);
            if ($displayItem !== null) {
                $displayItem->setCustomName($item->getCustomName());
                $displayItem->setLore($item->getLore());
                $rewards[] = $displayItem;
            }
        } else {
            $rewards[] = $item;
        }
    
        return $rewards;
    }    

}