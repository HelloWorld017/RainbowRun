<?php

namespace Khinenw\RainbowRun;

use org\Khinenw\xcel\XcelGame;
use org\Khinenw\xcel\XcelNgien;
use org\Khinenw\xcel\XcelPlayer;
use pocketmine\block\Block;
use pocketmine\entity\Effect;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\utils\TextFormat;

class RainbowRun extends XcelGame{

	public $currentWave = [];
	public $zCount = 0;

	public static $defaultConfigs = [
		"need-players" => 2,
		"game-term" => 4200,
		"preparation-term" => 300,
		"wave-creation" => 50,
		"wave-speed" => 20,
		"y" => 3
	];

	public function removePrivilege(XcelPlayer $player){
		$player->getPlayer()->removeEffect(Effect::JUMP);
	}

	public function givePrivilege(XcelPlayer $player){
		$player->getPlayer()->addEffect(Effect::getEffect(Effect::JUMP)->setDuration(PHP_INT_MAX)->setAmplifier(2));
	}

	public function getTip(XcelPlayer $player){
		switch($this->currentStatus){
			case self::STATUS_NOT_STARTED:
				return TextFormat::GREEN . "플레이어를 기다리는 중 (" . $this->aliveCount . "/" . $this->configs["need-players"] . ")";

			case self::STATUS_PREPARING:
				return TextFormat::GREEN . "게임 준비 중 (" . ((($this->configs["preparation-term"]) - $this->roundTick) / 20) . "초 남음)";

			case self::STATUS_IN_GAME:
				$time = ($this->configs["game-term"] - $this->roundTick) / 20;
				$text = TextFormat::GREEN . "게임 진행 중 (" . floor($time / 60) . "분 " . ($time % 60) . "초 남음, " . $this->aliveCount . "명 생존 중)";
				return ($player->isAlive()) ? $text : (TextFormat::YELLOW . "관전중" . "\n" . $text);
		}

		return "Error on Server!";
	}

	public function explainGame(XcelPlayer $player){
		$player->getPlayer()->sendMessage(TextFormat::YELLOW . "올라오는 무지개색 블럭을 점프로 피하십시오!");
	}

	public function canPvP(XcelPlayer $attacker, XcelPlayer $victim){
		return false;
	}

	public function canBeDamaged(XcelPlayer $player, EntityDamageEvent $event){
		return false;
	}

	public function getPreparationPosition(XcelPlayer $player){
		return $this->getWorld()->getSpawnLocation();
	}

	public static function getUniqueGameName(){
		return "rainbowrun";
	}

	public static function getGameName(){
		return "Rainbow Run";
	}

	public function startGame(){
		parent::startGame();
		$this->zCount = ceil(count($this->players) / 2);

		$rainbow = [14, 1, 4, 5, 3, 11, 10];
		$rainbowIndex = 0;

		for($x = -1; $x <= 12; $x++){
			for($z = -$this->zCount; $z <= $this->zCount; $z++){
				$this->getWorld()->setBlock(new Position($x, $this->configs["y"], $z, $this->getWorld()), Block::get(Block::WOOL, $rainbow[$rainbowIndex]));
			}
			$rainbowIndex++;
			if($rainbowIndex >= 7) $rainbowIndex = 0;
		}

	}

	public function resetGame(){
		for($x = -1; $x <= 12; $x++){
			for($z = -$this->zCount; $z <= $this->zCount; $z++){
				$this->getWorld()->setBlock(new Position($x, $this->configs["y"], $z, $this->getWorld()), Block::get(Block::AIR));
			}
		}

		parent::resetGame();
		$this->getWorld()->setSpawnLocation(new Position(0, $this->configs["y"] + 2, 0, $this->getWorld()));

		$this->getWorld()->setBlock(new Position(0, $this->configs["y"], 0, $this->getWorld()), Block::get(Block::WOOD));
		$this->getWorld()->setBlock(new Position(1, $this->configs["y"], 0, $this->getWorld()), Block::get(Block::WOOD));
		$this->getWorld()->setBlock(new Position(-1, $this->configs["y"], 0, $this->getWorld()), Block::get(Block::WOOD));
		$this->getWorld()->setBlock(new Position(0, $this->configs["y"], 1, $this->getWorld()), Block::get(Block::WOOD));
		$this->getWorld()->setBlock(new Position(1, $this->configs["y"], 1, $this->getWorld()), Block::get(Block::WOOD));
		$this->getWorld()->setBlock(new Position(-1, $this->configs["y"], 1, $this->getWorld()), Block::get(Block::WOOD));
		$this->getWorld()->setBlock(new Position(0, $this->configs["y"], -1, $this->getWorld()), Block::get(Block::WOOD));
		$this->getWorld()->setBlock(new Position(1, $this->configs["y"], -1, $this->getWorld()), Block::get(Block::WOOD));
		$this->getWorld()->setBlock(new Position(-1, $this->configs["y"], -1, $this->getWorld()), Block::get(Block::WOOD));
	}

	public function afterTick(){
		if($this->currentStatus !== XcelGame::STATUS_IN_GAME) return;

		if($this->innerTick % $this->configs["wave-creation"] === 0){
			$this->currentWave[] = 12;
		}

		if($this->innerTick % $this->configs["wave-speed"] === 0){
			$this->updateWave();
		}
	}

	public function updateWave(){
		foreach($this->currentWave as $key => $x){
			for($z = -$this->zCount; $z <= $this->zCount; $z++){
				$this->getWorld()->setBlock(new Position($x, $this->configs["y"] + 1, $z, $this->getWorld()), Block::get(Block::AIR));
			}

			$this->currentWave[$key]--;

			$downBlock = $this->getWorld()->getBlock(new Position($this->currentWave[$key], $this->configs["y"], 0, $this->getWorld()));
			for($newZ = -$this->zCount; $newZ <= $this->zCount; $newZ++){
				$this->getWorld()->setBlock(new Position($this->currentWave[$key], $this->configs["y"] + 1, $newZ, $this->getWorld()), Block::get($downBlock->getId(), $downBlock->getDamage()));
			}

			foreach($this->players as $xcelPlayer){
				if($this->getWorld()->getBlock($xcelPlayer->getPlayer()->getPosition())->getId() === Block::WOOL){
					$this->failPlayer($xcelPlayer);
				}
			}

			if($this->currentWave[$key] <= -1){
				unset($this->currentWave[$key]);
			}
		}
	}

	public function playerMove(XcelPlayer $player, PlayerMoveEvent $event){
		if(!$player->isAlive()) return;
		if(!XcelNgien::isSameGame($player->getGame(), $this)) return;

		$oldTo = $event->getTo();
		$x = ($oldTo->getX() < -1) ? -1 : $oldTo->getX();
		$x = ($x > 2) ? 2 : $x;

		$event->setTo(new Location($x, $oldTo->getY(), $oldTo->getZ(), $oldTo->getYaw(), $oldTo->getPitch(), $oldTo->getLevel()));
	}
}
