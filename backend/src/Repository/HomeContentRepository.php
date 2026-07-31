<?php

namespace App\Repository;

use App\Entity\HomeContent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomeContent>
 */
class HomeContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomeContent::class);
    }

    /**
     * Retourne la ligne unique de contenu de la page d'accueil, en la créant
     * avec des valeurs vides si elle n'existe pas encore.
     */
    public function getContent(): HomeContent
    {
        $content = $this->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $content) {
            $content = new HomeContent();
            $em = $this->getEntityManager();
            $em->persist($content);
            $em->flush();
        }

        return $content;
    }
}
