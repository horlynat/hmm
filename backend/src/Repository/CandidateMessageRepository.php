<?php

namespace App\Repository;

use App\Entity\CandidateMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CandidateMessage>
 */
class CandidateMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CandidateMessage::class);
    }

    /** @return CandidateMessage[] */
    public function findForCandidate(User $candidate): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.candidate = :candidate')
            ->setParameter('candidate', $candidate)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Messages admin non encore lus par ce candidat — badge de l'espace compte. */
    public function countUnreadForCandidate(User $candidate): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.candidate = :candidate')
            ->andWhere('m.fromAdmin = true')
            ->andWhere('m.read = false')
            ->setParameter('candidate', $candidate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Réponses candidat non encore lues par un admin — badge de la fiche candidat en back-office. */
    public function countUnreadFromCandidate(User $candidate): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.candidate = :candidate')
            ->andWhere('m.fromAdmin = false')
            ->andWhere('m.read = false')
            ->setParameter('candidate', $candidate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nombre de réponses candidat non lues, groupé par candidat — une seule
     * requête (GROUP BY) plutôt qu'un countUnreadFromCandidate() par ligne de
     * la liste des candidatures, pour éviter un N+1.
     *
     * @return array<int, int> candidate id => nombre de messages non lus
     */
    public function countUnreadFromCandidatesGroupedByCandidate(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.candidate) AS candidateId', 'COUNT(m.id) AS unread')
            ->andWhere('m.fromAdmin = false')
            ->andWhere('m.read = false')
            ->groupBy('m.candidate')
            ->getQuery()
            ->getResult();

        return array_column($rows, 'unread', 'candidateId');
    }

    /** Marque comme lus tous les messages du candidat dans le sens donné (appelé quand l'un des deux côtés consulte le fil). */
    public function markReadFor(User $candidate, bool $fromAdmin): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.read', 'true')
            ->andWhere('m.candidate = :candidate')
            ->andWhere('m.fromAdmin = :fromAdmin')
            ->andWhere('m.read = false')
            ->setParameter('candidate', $candidate)
            ->setParameter('fromAdmin', $fromAdmin)
            ->getQuery()
            ->execute();
    }
}
