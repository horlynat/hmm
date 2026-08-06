<?php

namespace App\Repository;

use App\Entity\SystemSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemSetting>
 */
class SystemSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemSetting::class);
    }

    /**
     * Retourne la ligne unique de configuration système, en la créant avec des
     * valeurs par défaut si elle n'existe pas encore (première visite de la page).
     */
    public function getSettings(): SystemSetting
    {
        $settings = $this->createQueryBuilder('s')
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $settings) {
            $settings = new SystemSetting();
            $em = $this->getEntityManager();
            $em->persist($settings);
            $em->flush();
        }

        return $settings;
    }
}
