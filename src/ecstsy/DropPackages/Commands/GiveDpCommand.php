<?php

namespace ecstsy\DropPackages\Commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\DropPackages\Utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

class GiveDpCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("dropPackage", false));
        $this->registerArgument(1, new IntegerArgument("amount", false));
        $this->registerArgument(2, new RawStringArgument("name", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $type = isset($args["dropPackage"]) ? $args["dropPackage"] : null;
        $amount = isset($args["amount"]) ? $args["amount"] : 1;
        $player = isset($args["name"]) ? Utils::getPlayerByPrefix($args["name"]) : $sender;

        if ($type !== null) {
            if ($player instanceof Player) {
                if ($amount !== null) {
                    $player->getInventory()->addItem(Utils::createDropPackage($type, $amount));
                    $player->sendMessage(C::colorize("&r&l&a(!) &r&aSuccessfully gave x{$amount} {$type} to {$player->getName()}!"));
                }
            }
        } else {
            $sender->sendMessage(C::colorize("&r&l&a(!) &r&aPlease specify a drop package!"));
        }
    }

    public function getPermission(): string
    {
        return "droppackages.give";
    }
}