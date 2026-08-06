<?php

namespace App\Controller\Admin;

use App\Entity\SystemSetting;
use App\Entity\User;
use App\Form\SystemSettingType;
use App\Repository\SystemSettingRepository;
use App\Security\Voter\SettingsVoter;
use App\Service\AuditLogger;
use App\Service\MediaUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Configuration système globale (branding, thème, langues).
 *
 * 🔒 Sécurité : réservé à SettingsVoter (ROLE_ADMIN et plus).
 */
#[Route('/admin/config', name: 'admin_config_')]
class AdminConfigController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SystemSettingRepository $systemSettingRepository,
        EntityManagerInterface $entityManager,
        MediaUploader $uploader,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::VIEW_CONFIG);

        $settings = $systemSettingRepository->getSettings();
        $form = $this->createForm(SystemSettingType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted(SettingsVoter::MANAGE_CONFIG);

            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile instanceof UploadedFile) {
                $result = $uploader->upload($logoFile, 'branding');
                $settings->setLogoPath($result['path']);
            }

            $user = $this->getUser();
            $settings->setUpdatedAt(new \DateTimeImmutable());
            $settings->setUpdatedBy($user instanceof User ? $user : null);
            $entityManager->flush();

            $auditLogger->log(SystemSetting::class, (int) $settings->getId(), $settings->getSiteName(), 'updated');
            $entityManager->flush();

            $this->addFlash('success', 'La configuration système a été mise à jour avec succès.');

            return $this->redirectToRoute('admin_config_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/config/index.html.twig', [
            'settings' => $settings,
            'form' => $form->createView(),
        ]);
    }
}
