<?php

namespace ecstsy\DropPackages\Listeners;

use ecstsy\DropPackages\Utils\DropPackages;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\world\sound\ChestOpenSound;

class EventListener implements Listener {

    public function onPlace(BlockPlaceEvent $event): void {
        $item = $event->getItem();
        $tag = $item->getNamedTag();

        if ($tag->getTag("drop_package") !== null) {
            $event->cancel();
        }
    }

    public function useDropPackage(PlayerItemUseEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $tag = $item->getNamedTag();

        if (($dptag = $tag->getTag("drop_package")) !== null) {
            $dropPackage = $dptag->getValue();
            $event->cancel();
        
            DropPackages::openDropPackage($player, $dropPackage)->send($player);
            $player->getWorld()->addSound($player->getLocation()->asVector3(), new ChestOpenSound(100));
            $item->pop();
            $player->getInventory()->setItemInHand($item);
        }
    }

}