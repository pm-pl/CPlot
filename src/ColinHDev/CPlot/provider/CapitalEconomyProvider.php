<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\provider;

use pocketmine\player\Player;
use pocketmine\Server;

class CapitalEconomyProvider extends EconomyProvider {

    /**
     * @throws \RuntimeException
     */
    public function __construct() {
        if (Server::getInstance()->getPluginManager()->getPlugin("Capital") === null) {
            throw new \RuntimeException("CapitalEconomyProvider requires the plugin \"Capital\" to be installed.");
        }
    }

    public function getCurrency() : string {
    }

    public function parseMoneyToString(float $money) : string {
    }

    public function getMoney(Player $player, callable $onSuccess, callable $onError) : void {
    }

    public function removeMoney(Player $player, float $money, callable $onSuccess, callable $onError) : void {
    }
}