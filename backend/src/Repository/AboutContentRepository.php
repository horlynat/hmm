<?php

namespace App\Repository;

use App\Entity\AboutContent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AboutContent>
 */
class AboutContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AboutContent::class);
    }

    /**
     * Retourne la ligne unique de contenu de la page "À propos", en la créant
     * avec des valeurs vides si elle n'existe pas encore.
     */
    public function getContent(): AboutContent
    {
        $content = $this->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $content) {
            $content = new AboutContent();
            $em = $this->getEntityManager();
            $em->persist($content);
            $em->flush();
        }

        return $content;
    }
}
