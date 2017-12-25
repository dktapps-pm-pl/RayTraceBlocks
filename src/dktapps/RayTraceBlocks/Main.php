<?php

namespace dktapps\RayTraceBlocks;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Level;
use pocketmine\level\MovingObjectPosition;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\plugin\PluginBase;


class Main extends PluginBase implements Listener{

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_AIR){
			$start = $event->getPlayer()->asVector3()->add(0, $event->getPlayer()->getEyeHeight(), 0);
			$rad = 50;
			$direction = $event->getPlayer()->getDirectionVector();
			$end = $start->add($direction->multiply($rad));

			$startTime = microtime(true);
			$res = $this->raycast($event->getPlayer()->getLevel(), $start, $end, $rad);
			$endTime = (microtime(true) - $startTime) * 1000;

			if($res === null){
				$event->getPlayer()->sendMessage("out of bounds ($endTime ms)");
			}else{
				$event->getPlayer()->sendMessage("hit block $res->blockX $res->blockY $res->blockZ ($endTime ms)");
				$level = $event->getPlayer()->getLevel();

				$level->addParticle(new HugeExplodeParticle($res->hitVector));
				$level->broadcastLevelSoundEvent($res->hitVector, LevelSoundEventPacket::SOUND_EXPLODE);
				$level->setBlock(new Vector3($res->blockX, $res->blockY, $res->blockZ), BlockFactory::get(Block::GLASS), true, true);
			}
		}
	}

	/**
	 * Performs a ray trace between the start and end coordinates. Returns a hit result if the ray trace is intercepted
	 * by a block with more than zero collision boxes, or null if the ray trace went the full distance.
	 *
	 * This is an implementation of the algorithm described in the link below.
	 * @link http://www.cse.yorku.ca/~amana/research/grid.pdf
	 *
	 * @param Level   $level
	 * @param Vector3 $start
	 * @param Vector3 $end
	 * @param float   $radius
	 *
	 * @return null|MovingObjectPosition
	 */
	public function raycast(Level $level, Vector3 $start, Vector3 $end, float $radius = 50) : ?MovingObjectPosition{
		$currentBlock = $start->floor();

		$directionVector = $end->subtract($start)->normalize();
		if($directionVector->lengthSquared() <= 0){
			throw new \InvalidArgumentException("Start and end points are the same, giving a zero direction vector");
		}

		$stepX = $directionVector->x <=> 0;
		$stepY = $directionVector->y <=> 0;
		$stepZ = $directionVector->z <=> 0;

		//Initialize the step accumulation variables depending how far into the current block the start position is. If
		//the start position is on the corner of the block, these will be zero.
		$tMaxX = $this->rayTraceDistanceToBoundary($start->x, $directionVector->x);
		$tMaxY = $this->rayTraceDistanceToBoundary($start->y, $directionVector->y);
		$tMaxZ = $this->rayTraceDistanceToBoundary($start->z, $directionVector->z);

		//The change in t on each axis when taking a step on that axis (always positive).
		$tDeltaX = $directionVector->x == 0 ? 0 : $stepX / $directionVector->x;
		$tDeltaY = $directionVector->y == 0 ? 0 : $stepY / $directionVector->y;
		$tDeltaZ = $directionVector->z == 0 ? 0 : $stepZ / $directionVector->z;

		while(true){
			$block = $level->getBlock($currentBlock);
			$hit = $block->calculateIntercept($start, $end);
			if($hit !== null){
				return $hit;
			}

			// tMaxX stores the t-value at which we cross a cube boundary along the
			// X axis, and similarly for Y and Z. Therefore, choosing the least tMax
			// chooses the closest cube boundary.
			if($tMaxX < $tMaxY and $tMaxX < $tMaxZ){
				if($tMaxX > $radius){
					break;
				}
				$currentBlock->x += $stepX;
				$tMaxX += $tDeltaX;
			}elseif($tMaxY < $tMaxZ){
				if($tMaxY > $radius){
					break;
				}
				$currentBlock->y += $stepY;
				$tMaxY += $tDeltaY;
			}else{
				if($tMaxZ > $radius){
					break;
				}
				$currentBlock->z += $stepZ;
				$tMaxZ += $tDeltaZ;
			}
		}

		return null;
	}


	/**
	 * Returns the distance that must be travelled on an axis from the start point with the direction vector component to
	 * cross a block boundary.
	 *
	 * For example, given an X coordinate inside a block and the X component of a direction vector, will return the distance
	 * travelled by that direction component to reach a block with a different X coordinate.
	 *
	 * Find the smallest positive t such that s+t*ds is an integer.
	 *
	 * @param float $s Starting coordinate
	 * @param float $ds Direction vector component of the relevant axis
	 *
	 * @return float Distance along the ray trace that must be travelled to cross a boundary.
	 */
	private function rayTraceDistanceToBoundary(float $s, float $ds) : float{
		if($ds == 0){
			return INF;
		}

		if($ds < 0){
			$s = -$s;
			$ds = -$ds;

			if(floor($s) == $s){ //exactly at coordinate, will leave the coordinate immediately by moving negatively
				return 0;
			}
		}

		// problem is now s+t*ds = 1
		return (1 - ($s - floor($s))) / $ds;
	}
}
