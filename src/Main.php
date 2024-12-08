<?php

declare(strict_types=1);

namespace xBeastMode\BouncingArrows;

use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener{
    /**
     * Stores projectile motions (in this case arrows)
     * If the projectile hits a surface, it will store the last motion the projectile had
     *
     * @var Vector3[]
     */
    protected array $movingProjectileMotion = [];

    public function onEnable(): void{
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /**
     * @param ProjectileHitEvent $event
     *
     * @return void
     */
    public function onProjectileHit(ProjectileHitEvent $event): void{
        $projectile = $event->getEntity();

        if(isset($this->movingProjectileMotion[$projectile->getId()])){
            $this->bounceProjectile($projectile, $event->getRayTraceResult()->hitFace);
        }
    }

    /**
     * @priority LOWEST
     *
     * @param DataPacketSendEvent $event
     *
     * @return void
     */
    public function onPacketSend(DataPacketSendEvent $event): void{
        $packets = $event->getPackets();
        foreach($packets as $packet){
            if($packet instanceof SetActorMotionPacket){
                foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
                    $entity = $world->getEntity($packet->actorRuntimeId);
                    if($entity instanceof Arrow){
                        $this->movingProjectileMotion[$entity->getId()] = $entity->getMotion();
                        continue 2;
                    }
                }
            }
        }
    }

    /**
     * This method uses the @param Arrow $projectile
     * @param int $hitFace
     *
     * @return void
     * @see Main::getReflectionVector() to bounce the projectile off the hit surface
     *
     */
    public function bounceProjectile(Arrow $projectile, int $hitFace): void{
        $reflection = $this->getReflectionVector($projectile, $hitFace);

        $location = clone $projectile->getLocation();

        // adding a small fraction of the reflection to the arrow's position
        // to prevent it from instantly hitting the surface again when spawning
        $location->x += $reflection->x * 0.1;
        $location->y += $reflection->y * 0.1;
        $location->z += $reflection->z * 0.1;

        $entity = new Arrow($location, $projectile->getOwningEntity(), $projectile->isCritical());

        $entity->setMotion($reflection);
        $entity->spawnToAll();

        $projectile->flagForDespawn();
        unset($this->movingProjectileMotion[$projectile->getId()]);
    }

    /**
     * This method uses the [r = v − 2 ( v * n ) n] formula
     *
     * v = projectile's last motion before hitting a surface
     * n = dot product of the hit face (the side where the projectile hit the block)
     *
     * @param Arrow $projectile
     * @param int   $hitFace
     * @param float $drag
     *
     * @return Vector3
     */
    public function getReflectionVector(Arrow $projectile, int $hitFace, float $drag = 0.9): Vector3{
        $motion = $this->movingProjectileMotion[$projectile->getId()];
        if($motion->length() <= 0.01){
            return Vector3::zero();
        }

        $faceVector = new Vector3(...Facing::OFFSET[$hitFace]);

        $dotProduct = 2 * $motion->dot($faceVector);
        $normalVector = $faceVector->multiply($dotProduct);

        return $motion->subtractVector($normalVector)->multiply($drag);
    }
}