<?php

namespace Khinenw\RainbowRun;

use org\Khinenw\xcel\XcelNgien;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\plugin\PluginBase;

class RainbowMain extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		XcelNgien::registerGame(new \ReflectionClass(RainbowRun::class));
	}

	public function onPlayerMove(PlayerMoveEvent $event){
		if(!isset(XcelNgien::$players[$event->getPlayer()->getName()])) return;
		$xcelPlayer = XcelNgien::$players[$event->getPlayer()->getName()];
		$game = $xcelPlayer->getGame();
		if(!$game instanceof RainbowRun) return;
		$game->playerMove($xcelPlayer, $event);
	}
}
