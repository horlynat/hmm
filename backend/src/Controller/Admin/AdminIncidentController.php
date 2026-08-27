<?php

namespace App\Controller\Admin;

use App\Entity\Incident;
use App\Entity\User;
use App\Enum\IncidentCategoryEnum;
use App\Enum\IncidentSeverityEnum;
use App\Enum\IncidentStatusEnum;
use App\Form\IncidentType;
use App\Repository\IncidentRepository;
use App\Security\Voter\IncidentVoter;
use App\Service\AuditLogger;
use App\Service\IncidentCsvExporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Journal d'incidents (App\Entity\Incident) — pas un doublon des runbooks
 * Markdown (docs/incident-auth.md, docs/incident-data-loss.md) : ceux-ci
 * documentent "que faire", cet espace trace "qu'est-ce qui est arrivé,
 * quand, combien de fois" (cf. docblock de l'entité).
 *
 * 🔒 Sécurité : VIEW/CREATE/EDIT réservés à ROLE_ADMIN, DELETE à
 * ROLE_SUPER_ADMIN (cf. IncidentVoter) — supprimer un incident casse
 * l'historique de récurrence que cet espace sert justement à construire.
 */
#[Route('/admin/incident', name: 'admin_incident_')]
final class AdminIncidentController extends AbstractController
{
    // =========================================================================
    // 📌 LISTE + STATISTIQUES DE RÉCURRENCE
    // =========================================================================

    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(Request $request, IncidentRepository $incidentRepository): Response
    {
        $this->denyAccessUnlessGranted(IncidentVoter::VIEW);

        $categoryFilter = (string) $request->query->get('category', '');
        $severityFilter = (string) $request->query->get('severity', '');
        $statusFilter = (string) $request->query->get('status', '');

        $queryBuilder = $this->buildFilteredQueryBuilder($incidentRepository, $categoryFilter, $severityFilter, $statusFilter);

        return $this->render('admin/incident/index.html.twig', [
            'incidents' => $queryBuilder->getQuery()->getResult(),
            'categories' => IncidentCategoryEnum::cases(),
            'severities' => IncidentSeverityEnum::cases(),
            'statuses' => IncidentStatusEnum::cases(),
            'filters' => [
                'category' => $categoryFilter,
                'severity' => $severityFilter,
                'status' => $statusFilter,
            ],
            'openCount' => $incidentRepository->countOpen(),
            'recurringCategories' => $incidentRepository->findRecurringCategories(),
        ]);
    }

    // =========================================================================
    // 📌 EXPORT CSV (mêmes filtres que la liste)
    // =========================================================================

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Request $request, IncidentRepository $incidentRepository, IncidentCsvExporter $exporter): Response
    {
        $this->denyAccessUnlessGranted(IncidentVoter::VIEW);

        $queryBuilder = $this->buildFilteredQueryBuilder(
            $incidentRepository,
            (string) $request->query->get('category', ''),
            (string) $request->query->get('severity', ''),
            (string) $request->query->get('status', ''),
        );

        $rows = (static function () use ($queryBuilder, $exporter): iterable {
            foreach ($queryBuilder->getQuery()->toIterable() as $incident) {
                yield $exporter->row($incident);
            }
        })();

        return $exporter->stream('incidents.csv', $rows);
    }

    private function buildFilteredQueryBuilder(
        IncidentRepository $incidentRepository,
        string $categoryFilter,
        string $severityFilter,
        string $statusFilter,
    ): QueryBuilder {
        $queryBuilder = $incidentRepository->createQueryBuilder('i')
            ->orderBy('i.detectedAt', 'DESC');

        $category = IncidentCategoryEnum::tryFrom($categoryFilter);
        if (null !== $category) {
            $queryBuilder->andWhere('i.category = :category')->setParameter('category', $category);
        }
        $severity = IncidentSeverityEnum::tryFrom($severityFilter);
        if (null !== $severity) {
            $queryBuilder->andWhere('i.severity = :severity')->setParameter('severity', $severity);
        }
        $status = IncidentStatusEnum::tryFrom($statusFilter);
        if (null !== $status) {
            $queryBuilder->andWhere('i.status = :status')->setParameter('status', $status);
        }

        return $queryBuilder;
    }

    // =========================================================================
    // 📌 CRÉATION
    // =========================================================================

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(IncidentVoter::CREATE);

        // detectedAt est déjà initialisée à "maintenant" par le constructeur
        // de Incident — le formulaire permet de la corriger si l'incident a
        // été détecté avant sa saisie.
        $incident = new Incident();

        $user = $this->getUser();
        if ($user instanceof User) {
            $incident->setReportedBy($user);
        }

        $form = $this->createForm(IncidentType::class, $incident);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($incident);
            $entityManager->flush();

            $auditLogger->log(Incident::class, $incident->getId(), $incident->getTitle(), 'created');
            $entityManager->flush();

            $this->addFlash('success', "L'incident a été enregistré.");

            return $this->redirectToRoute('admin_incident_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/incident/create.html.twig', [
            'incident' => $incident,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 CONSULTATION
    // =========================================================================

    #[Route('/{id}', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(Incident $incident): Response
    {
        $this->denyAccessUnlessGranted(IncidentVoter::VIEW, $incident);

        return $this->render('admin/incident/read.html.twig', [
            'incident' => $incident,
        ]);
    }

    // =========================================================================
    // 📌 MODIFICATION
    // =========================================================================

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Incident $incident, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(IncidentVoter::EDIT, $incident);

        $form = $this->createForm(IncidentType::class, $incident);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $auditLogger->log(Incident::class, $incident->getId(), $incident->getTitle(), 'updated');
            $entityManager->flush();

            $this->addFlash('success', "L'incident a été mis à jour.");

            return $this->redirectToRoute('admin_incident_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/incident/update.html.twig', [
            'incident' => $incident,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 SUPPRESSION (ROLE_SUPER_ADMIN uniquement, cf. IncidentVoter)
    // =========================================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Incident $incident, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted(IncidentVoter::DELETE, $incident);

        if ($this->isCsrfTokenValid('admin_incident_delete_'.$incident->getId(), $request->request->get('_token'))) {
            $auditLogger->log(Incident::class, $incident->getId(), $incident->getTitle(), 'deleted');
            $entityManager->remove($incident);
            $entityManager->flush();

            $this->addFlash('success', "L'incident a été supprimé.");
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');
        }

        return $this->redirectToRoute('admin_incident_index', [], Response::HTTP_SEE_OTHER);
    }
}
