<?php

namespace App\Controller\Admin;

use App\Entity\PermissionDefinition;
use App\Entity\User;
use App\Repository\PermissionDefinitionRepository;
use App\Repository\RoleRepository;
use App\Security\Voter\SecurityVoter;
use App\Service\AuditLogger;
use App\Service\PermissionRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Édition du catalogue de permissions "métier" pilotées en base (cf.
 * PermissionDefinition pour le périmètre exact et pourquoi certains Voters
 * en sont exclus). Chaque changement ici peut affecter QUI peut faire QUOI
 * sur le reste du back-office — la surface la plus sensible de l'admin.
 *
 * 🔒 Sécurité : réservé à SecurityVoter::MANAGE_PERMISSIONS, verrouillé à
 * ROLE_SUPER_ADMIN (au-dessus du ROLE_ADMIN qui suffit pour le reste de la
 * section Sécurité) — cf. le docblock de cette constante.
 */
#[Route('/admin/security/permissions', name: 'admin_security_permission_')]
class AdminSecurityPermissionController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(PermissionDefinitionRepository $repository, RoleRepository $roleRepository): Response
    {
        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_PERMISSIONS);

        $grouped = [];
        foreach ($repository->findAllOrdered() as $definition) {
            $grouped[$definition->getCategory()][] = $definition;
        }

        return $this->render('admin/security/permissions.html.twig', [
            'grouped' => $grouped,
            'roles' => $roleRepository->findAllOrderedByRank(),
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(
        int $id,
        Request $request,
        PermissionDefinitionRepository $repository,
        RoleRepository $roleRepository,
        EntityManagerInterface $entityManager,
        PermissionRegistry $permissionRegistry,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_PERMISSIONS);

        $definition = $repository->find($id);
        if (!$definition) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('admin_security_permission_update_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_security_permission_index');
        }

        $newRoleCode = (string) $request->request->get('role', '');
        $newRole = $roleRepository->findOneByCode($newRoleCode);
        if (!$newRole) {
            $this->addFlash('error', 'Rôle invalide.');

            return $this->redirectToRoute('admin_security_permission_index');
        }

        $previousRole = $definition->getCurrentRole();
        if ($newRoleCode === $previousRole->getCode()) {
            return $this->redirectToRoute('admin_security_permission_index');
        }

        $user = $this->getUser();
        $definition->setCurrentRole($newRole);
        $definition->setUpdatedAt(new \DateTimeImmutable());
        $definition->setUpdatedBy($user instanceof User ? $user : null);

        $auditLogger->log(
            PermissionDefinition::class,
            (int) $definition->getId(),
            $definition->getCode(),
            'permission_role_changed',
            sprintf('%s : %s → %s', $definition->getCode(), $previousRole->getCode(), $newRoleCode),
        );
        $entityManager->flush();
        $permissionRegistry->invalidate();

        $this->addFlash('success', sprintf('%s requiert désormais %s.', $definition->getLabel(), $newRoleCode));

        return $this->redirectToRoute('admin_security_permission_index');
    }

    #[Route('/{id}/reset', name: 'reset', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reset(
        int $id,
        Request $request,
        PermissionDefinitionRepository $repository,
        EntityManagerInterface $entityManager,
        PermissionRegistry $permissionRegistry,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_PERMISSIONS);

        $definition = $repository->find($id);
        if (!$definition) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('admin_security_permission_reset_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_security_permission_index');
        }

        if (!$definition->isOverridden()) {
            return $this->redirectToRoute('admin_security_permission_index');
        }

        $previousRole = $definition->getCurrentRole();
        $user = $this->getUser();
        $definition->setCurrentRole($definition->getDefaultRole());
        $definition->setUpdatedAt(new \DateTimeImmutable());
        $definition->setUpdatedBy($user instanceof User ? $user : null);

        $auditLogger->log(
            PermissionDefinition::class,
            (int) $definition->getId(),
            $definition->getCode(),
            'permission_role_reset',
            sprintf('%s : %s → %s (valeur d\'origine)', $definition->getCode(), $previousRole->getCode(), $definition->getDefaultRole()->getCode()),
        );
        $entityManager->flush();
        $permissionRegistry->invalidate();

        $this->addFlash('success', sprintf('%s réinitialisée à sa valeur d\'origine (%s).', $definition->getLabel(), $definition->getDefaultRole()->getCode()));

        return $this->redirectToRoute('admin_security_permission_index');
    }
}
