<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\plots;

use ColinHDev\CPlot\player\PlayerData;

class PlotRate {

    private string $rate;
    private PlayerData $playerData;
    private int $rateTime;
    private ?string $comment;

    public function __construct(string $rate, PlayerData $playerData, int $rateTime, ?string $comment = null) {
        $this->rate = $rate;
        $this->playerData = $playerData;
        $this->rateTime = $rateTime;
        $this->comment = $comment;
    }

    public function getRate() : string {
        return $this->rate;
    }

    public function getPlayerData() : PlayerData {
        return $this->playerData;
    }

    public function getRateTime() : int {
        return $this->rateTime;
    }

    public function getComment() : ?string {
        return $this->comment;
    }

    public function toString() : string {
        return $this->playerData->getPlayerUUID() . ";" . $this->rateTime;
    }

    /**
     * @phpstan-return array{rate: string, playerData: string, rateTime: int, comment: string|null}
     */
    public function __serialize() : array {
        return [
            "rate" => $this->rate,
            "playerData" => serialize($this->playerData),
            "rateTime" => $this->rateTime,
            "comment" => $this->comment
        ];
    }

    /**
     * @phpstan-param array{rate: string, playerData: string, rateTime: int, comment: string|null} $data
     */
    public function __unserialize(array $data) : void {
        $this->rate = $data["rate"];
        $this->playerData = unserialize($data["playerData"], ["allowed_classes" => [PlayerData::class]]);
        $this->rateTime = $data["rateTime"];
        $this->comment = $data["comment"];
    }
}