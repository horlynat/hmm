<?php

namespace App\Controller\Admin;

use App\Security\Voter\SettingsVoter;
use App\Service\DatabaseBackupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sauvegardes & restauration de la base de données (mysqldump / mysql, cf.
 * DatabaseBackupService).
 *
 * 🔒 Sécurité : consultation, création et téléchargement réservés à
 * SettingsVoter (ROLE_ADMIN et plus). Suppression et restauration réservées à
 * ROLE_SUPER_ADMIN : une restauration écrase l'intégralité de la base en
 * production, ce n'est pas une action anodine.
 */
#[Route('/admin/backup', name: 'admin_backup_')]
class AdminBackupController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(DatabaseBackupService $backupService): Response
    {
        $this->denyAccessUnlessGranted(SettingsVoter::VIEW_BACKUPS);

        return $this->render('admin/backup/index.html.twig', [
            'backups' => $backupService->list(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request, DatabaseBackupService $backupService): Response
    {
        $this->denyAccessUnlessGranted(SettingsVoter::CREATE_BACKUP);

        if (!$this->isCsrfTokenValid('admin_backup_create', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_backup_index');
        }

        try {
            $filename = $backupService->create();
            $this->addFlash('success', sprintf('Sauvegarde créée : %s', $filename));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Échec de la sauvegarde : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_backup_index');
    }

    #[Route('/{filename}/download', name: 'download', methods: ['GET'])]
    public function download(string $filename, DatabaseBackupService $backupService): Response
    {
        $this->denyAccessUnlessGranted(SettingsVoter::DOWNLOAD_BACKUP);

        try {
            $filepath = $backupService->getPath($filename);
        } catch (\Throwable) {
            throw $this->createNotFoundException('Sauvegarde introuvable.');
        }

        $response = new BinaryFileResponse($filepath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

        return $response;
    }

    #[Route('/{filename}/delete', name: 'delete', methods: ['POST'])]
    public function delete(string $filename, Request $request, DatabaseBackupService $backupService): Response
    {
        $this->denyAccessUnlessGranted(SettingsVoter::DELETE_BACKUP);

        if (!$this->isCsrfTokenValid('admin_backup_delete_' . $filename, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_backup_index');
        }

        try {
            $backupService->delete($filename);
            $this->addFlash('success', sprintf('Sauvegarde "%s" supprimée.', $filename));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Échec de la suppression : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_backup_index');
    }

    #[Route('/{filename}/restore', name: 'restore', methods: ['POST'])]
    public function restore(string $filename, Request $request, DatabaseBackupService $backupService): Response
    {
        $this->denyAccessUnlessGranted(SettingsVoter::RESTORE_BACKUP);

        if (!$this->isCsrfTokenValid('admin_backup_restore_' . $filename, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_backup_index');
        }

        // Garde-fou supplémentaire : l'action est irréversible (écrase toute la base),
        // on exige la saisie exacte du nom du fichier en confirmation (cf. formulaire).
        if ($request->request->get('confirm_filename') !== $filename) {
            $this->addFlash('error', 'Confirmation invalide : le nom du fichier saisi ne correspond pas. Restauration annulée.');

            return $this->redirectToRoute('admin_backup_index');
        }

        try {
            $backupService->restore($filename);
            $this->addFlash('success', sprintf('Base de données restaurée depuis "%s".', $filename));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Échec de la restauration : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_backup_index');
    }
}
