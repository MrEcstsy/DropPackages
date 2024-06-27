<?php

namespace ecstsy\DropPackages;

use ecstsy\DropPackages\Commands\GiveDpCommand;
use ecstsy\DropPackages\Listeners\EventListener;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

class Loader extends PluginBase {

    use SingletonTrait;

    public function onLoad(): void {
        self::setInstance($this);
    }

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        $this->saveDefaultConfig();
        $this->getServer()->getCommandMap()->registerAll("droppackages", [
            new GiveDpCommand($this, "givedroppackage", "Give Drop Packages to players", ["givedp"]),
        ]);

        $this->saveResourceFiles('dp');
    }

    private function saveResourceFiles(string $resourceDir): void {
        $resourcePath = $this->getFile() . "resources/" . $resourceDir . "/";
        $targetPath = $this->getDataFolder() . $resourceDir . "/";
    
        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
        }
    
        foreach (glob($resourcePath . '*.yml') as $file) {
            $fileName = basename($file);
            $this->saveResource($resourceDir . '/' . $fileName, false);
        }
    }
}
