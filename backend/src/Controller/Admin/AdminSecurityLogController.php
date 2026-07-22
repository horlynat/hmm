<?php

namespace App\Controller\Admin;

use App\Repository\FailedLoginAttemptRepository;
use App\Repository\LoginHistoryRepository;
use App\Security\Voter\SecurityVoter;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Journal des connexions : réussites (LoginHistory) et tentatives échouées
 * (FailedLoginAttempt).
 *
 * 🔒 Sécurité : réservé à SecurityVoter::VIEW_LOGS (ROLE_ADMIN et plus).
 */
#[Route('/admin/security/logs', name: 'admin_security_log_')]
class AdminSecurityLogController extends AbstractController
{
    private const LIMIT = 20;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        LoginHistoryRepository $loginHistoryRepository,
        FailedLoginAttemptRepository $failedLoginAttemptRepository,
    ): Response {
        $this->denyAccessUnlessGranted(SecurityVoter::VIEW_LOGS);

        $tab = $request->query->get('tab', 'success') === 'failed' ? 'failed' : 'success';
        $search = trim((string) $request->query->get('search', ''));
        $page = max(1, (int) $request->query->get('page', 1));

        if ('failed' === $tab) {
            $queryBuilder = $failedLoginAttemptRepository->createQueryBuilder('f')
                ->orderBy('f.createdAt', 'DESC');

            if ('' !== $search) {
                $queryBuilder->andWhere('f.email LIKE :search OR f.ip LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
            }
        } else {
            $queryBuilder = $loginHistoryRepository->createQueryBuilder('l')
                ->leftJoin('l.user', 'u')
                ->addSelect('u')
                ->orderBy('l.loginAt', 'DESC');

            if ('' !== $search) {
                $queryBuilder->andWhere('u.email LIKE :search OR l.ip LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
            }
        }

        $paginator = new Paginator($queryBuilder);
        $totalPages = max(1, (int) ceil($paginator->count() / self::LIMIT));
        $page = min($page, $totalPages);

        $entries = $paginator->getQuery()
            ->setFirstResult(($page - 1) * self::LIMIT)
            ->setMaxResults(self::LIMIT)
            ->getResult();

        return $this->render('admin/security/logs.html.twig', [
            'tab' => $tab,
            'entries' => $entries,
            'search' => $search,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }
}
