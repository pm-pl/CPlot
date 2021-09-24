<?php

namespace ColinHDev\CPlot\commands\subcommands;

use ColinHDev\CPlot\commands\Subcommand;
use ColinHDev\CPlot\provider\EconomyProvider;
use ColinHDev\CPlot\tasks\async\PlotBorderChangeAsyncTask;
use ColinHDev\CPlotAPI\BasePlot;
use ColinHDev\CPlotAPI\Plot;
use pocketmine\command\CommandSender;
use pocketmine\permission\Permission;
use pocketmine\player\Player;
use pocketmine\Server;

class ClaimSubcommand extends Subcommand {

    public function execute(CommandSender $sender, array $args) : void {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("claim.senderNotOnline"));
            return;
        }

        $worldSettings = $this->getPlugin()->getProvider()->getWorld($sender->getWorld()->getFolderName());
        if ($worldSettings === null) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("claim.noPlotWorld"));
            return;
        }

        $plot = Plot::fromPosition($sender->getPosition(), false);
        if ($plot === null) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("claim.noPlot"));
            return;
        }
        if (!$plot->loadMergedPlots()) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("claim.loadMergedPlotsError"));
            return;
        }
        $senderUUID = $sender->getUniqueId()->toString();
        if ($plot->getOwnerUUID() !== null) {
            if ($senderUUID !== $plot->getOwnerUUID()) {
                $sender->sendMessage($this->getPrefix() . $this->translateString("claim.plotAlreadyClaimed", [$this->getPlugin()->getProvider()->getPlayerNameByUUID($plot->getOwnerUUID()) ?? "ERROR"]));
                return;
            }
            $sender->sendMessage($this->getPrefix() . $this->translateString("claim.plotAlreadyClaimedBySender"));
            return;
        }

        $claimedPlots = $this->getPlugin()->getProvider()->getPlotsByOwnerUUID($senderUUID);
        if ($claimedPlots === null) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("claim.loadClaimedPlotsError"));
            return;
        }
        $claimedPlotsCount = count($claimedPlots);
        $maxPlots = $this->getMaxPlotsOfPlayer($sender);
        if ($claimedPlotsCount > $maxPlots) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("claim.plotLimitReached", [$claimedPlotsCount, $maxPlots]));
            return;
        }

        $economyProvider = $this->getPlugin()->getEconomyProvider();
        if ($economyProvider !== null) {
            $price = $economyProvider->getPrice(EconomyProvider::PRICE_CLAIM) ?? 0.0;
            if ($price > 0.0) {
                $money = $economyProvider->getMoney($sender);
                if ($money === null) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("claim.loadMoneyError"));
                    return;
                }
                if ($money < $price) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("claim.notEnoughMoney", [$economyProvider->getCurrency(), $economyProvider->parseMoneyToString($price), $economyProvider->parseMoneyToString($price - $money)]));
                    return;
                }
                if (!$economyProvider->removeMoney($sender, $price, "Paid " . $price . $economyProvider->getCurrency() . " to claim the plot " . $plot->toString() . ".")) {
                    $sender->sendMessage($this->getPrefix() . $this->translateString("claim.saveMoneyError"));
                    return;
                }
                $sender->sendMessage($this->getPrefix() . $this->translateString("claim.chargedMoney", [$economyProvider->getCurrency(), $economyProvider->parseMoneyToString($price)]));
            }
        }

        $plot->setOwnerUUID($senderUUID);
        $plot->setClaimTime((int) (round(microtime(true) * 1000)));
        if (!$this->getPlugin()->getProvider()->savePlot($plot)) {
            $sender->sendMessage($this->getPrefix() . $this->translateString("claim.saveError"));
            return;
        }

        $blockBorderOnClaim = $worldSettings->getBorderBlockOnClaim();
        $task = new PlotBorderChangeAsyncTask($worldSettings, $plot, $blockBorderOnClaim);
        $world = $sender->getWorld();
        $task->setWorld($world);
        $task->setClosure(
            function (int $elapsedTime, string $elapsedTimeString, array $result) use ($world, $sender, $blockBorderOnClaim) {
                [$plotCount, $plots] = $result;
                $plots = array_map(
                    function (BasePlot $plot) : string {
                        return $plot->toSmallString();
                    },
                    $plots
                );
                Server::getInstance()->getLogger()->debug(
                    "Changing plot border due to plot claim to " . $blockBorderOnClaim->getName() . " (ID:Meta: " . $blockBorderOnClaim->getId() . ":" . $blockBorderOnClaim->getMeta() . ") in world " . $world->getDisplayName() . " (folder: " . $world->getFolderName() . ") took " . $elapsedTimeString . " (" . $elapsedTime . "ms) for player " . $sender->getUniqueId()->toString() . " (" . $sender->getName() . ") for " . $plotCount . " plot" . ($plotCount > 1 ? "s" : "") . ": [" . implode(", ", $plots) . "]."
                );
            }
        );
        $this->getPlugin()->getServer()->getAsyncPool()->submitTask($task);

        $sender->sendMessage($this->getPrefix() . $this->translateString("claim.success", [$plot->toString(), $plot->toSmallString()]));
    }

    private function getMaxPlotsOfPlayer(Player $player) : int {
        if ($player->hasPermission("cplot.claimPlots.unlimited")) return PHP_INT_MAX;

        $player->recalculatePermissions();
        $permissions = $player->getEffectivePermissions();
        $permissions = array_filter(
            $permissions,
            function(string $name) : bool {
                return (str_starts_with($name, "cplot.claimPlots."));
            },
            ARRAY_FILTER_USE_KEY
        );
        if (count($permissions) === 0) return 0;

        krsort($permissions, SORT_FLAG_CASE | SORT_NATURAL);
        /** @var string $permissionName */
        /** @var Permission $permission */
        foreach ($permissions as $permissionName => $permission) {
            $maxPlots = substr($permissionName, 17);
            if (!is_numeric($maxPlots)) continue;
            return (int) $maxPlots;
        }
        return 0;
    }
}