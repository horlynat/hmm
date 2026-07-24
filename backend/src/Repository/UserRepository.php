<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return User[] Comptes ayant un rôle d'administration (ROLE_ADMIN ou ROLE_SUPER_ADMIN)
     */
    public function findAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :roleAdmin OR u.roles LIKE :roleSuperAdmin')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->setParameter('roleSuperAdmin', '%"ROLE_SUPER_ADMIN"%')
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[] Comptes ayant le rôle collaborateur (pros/freelances associés à des projets)
     */
    public function findCollaborators(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :roleEditor')
            ->andWhere('u.roles NOT LIKE :roleAdmin')
            ->andWhere('u.roles NOT LIKE :roleSuperAdmin')
            ->setParameter('roleEditor', '%"ROLE_EDITOR"%')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->setParameter('roleSuperAdmin', '%"ROLE_SUPER_ADMIN"%')
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[] Comptes clients "purs" (ni admin, ni collaborateur)
     */
    public function findClients(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles NOT LIKE :roleAdmin')
            ->andWhere('u.roles NOT LIKE :roleSuperAdmin')
            ->andWhere('u.roles NOT LIKE :roleEditor')
            ->andWhere('u.roles NOT LIKE :roleModerator')
            ->andWhere('u.roles NOT LIKE :roleManager')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->setParameter('roleSuperAdmin', '%"ROLE_SUPER_ADMIN"%')
            ->setParameter('roleEditor', '%"ROLE_EDITOR"%')
            ->setParameter('roleModerator', '%"ROLE_MODERATOR"%')
            ->setParameter('roleManager', '%"ROLE_MANAGER"%')
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Candidatures freelance en attente de revue : inscrites via le formulaire
     * public (donc avec des spécialités renseignées) mais pas encore promues
     * ROLE_EDITOR par un administrateur — cf. AdminCollaboratorController.
     *
     * @return User[]
     */
    public function findFreelanceCandidates(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles NOT LIKE :roleAdmin')
            ->andWhere('u.roles NOT LIKE :roleSuperAdmin')
            ->andWhere('u.roles NOT LIKE :roleEditor')
            ->andWhere('u.roles NOT LIKE :roleModerator')
            ->andWhere('u.roles NOT LIKE :roleManager')
            ->andWhere('u.specialties IS NOT NULL')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->setParameter('roleSuperAdmin', '%"ROLE_SUPER_ADMIN"%')
            ->setParameter('roleEditor', '%"ROLE_EDITOR"%')
            ->setParameter('roleModerator', '%"ROLE_MODERATOR"%')
            ->setParameter('roleManager', '%"ROLE_MANAGER"%')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[] Comptes dont le mot de passe n'a pas été renouvelé depuis $days jours
     */
    public function findWithStalePassword(int $days = 90): array
    {
        $threshold = new \DateTimeImmutable(sprintf('-%d days', $days));

        return $this->createQueryBuilder('u')
            ->andWhere('COALESCE(u.passwordChangedAt, u.createdAt) < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Comptes n'ayant pas la 2FA opérationnelle (case non cochée, ou cochée sans secret
     * TOTP confirmé — voir User::isTotpAuthenticationEnabled()).
     */
    public function countWithoutTwoFactor(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isTwoFactorEnabled = false OR u.totpSecret IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return User[] Comptes qui ne se sont pas connectés depuis $days jours (ou jamais)
     */
    public function findInactiveSince(int $days = 30): array
    {
        $threshold = new \DateTimeImmutable(sprintf('-%d days', $days));

        return $this->createQueryBuilder('u')
            ->andWhere('COALESCE(u.lastLoginAt, u.createdAt) < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('u.lastLoginAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
