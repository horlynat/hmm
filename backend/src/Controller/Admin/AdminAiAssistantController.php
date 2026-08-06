<?php

namespace App\Controller\Admin;

use App\Entity\AiAssistantEntry;
use App\Entity\AiAssistantSettings;
use App\Form\AiAssistantEntryType;
use App\Form\AiAssistantSettingsType;
use App\Repository\AiAssistantEntryRepository;
use App\Repository\AiAssistantSettingsRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion du widget assistant IA : réglages fixes (accueil/réponse par
 * défaut, ligne unique) et entrées de FAQ (suggestions/mots-clés/réponses,
 * liste CRUD classique — cf. AdminSkillCategoryController).
 *
 * 🔒 Sécurité : réservé à ROLE_ADMIN.
 */
#[Route('/admin/ai-assistant', name: 'admin_ai_assistant_')]
final class AdminAiAssistantController extends AbstractController
{
    // =========================================================================
    // 📌 RÉGLAGES (accueil / réponse par défaut) — ligne unique
    // =========================================================================

    #[Route('/settings', name: 'settings', methods: ['GET', 'POST'])]
    public function settings(
        Request $request,
        AiAssistantSettingsRepository $settingsRepository,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $settings = $settingsRepository->getSettings();
        $form = $this->createForm(AiAssistantSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            $settings->setUpdatedAt(new \DateTimeImmutable());
            $settings->setUpdatedBy($user instanceof \App\Entity\User ? $user : null);
            $entityManager->flush();

            $auditLogger->log(AiAssistantSettings::class, (int) $settings->getId(), 'ai_assistant_settings', 'updated');
            $entityManager->flush();

            $this->addFlash('success', 'Les réglages de l\'assistant IA ont été mis à jour avec succès.');

            return $this->redirectToRoute('admin_ai_assistant_settings', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/ai_assistant/settings.html.twig', [
            'settings' => $settings,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 LISTE DES ENTRÉES DE FAQ
    // =========================================================================

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(AiAssistantEntryRepository $entryRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/ai_assistant/index.html.twig', [
            'entries' => $entryRepository->findAllOrdered(),
        ]);
    }

    // =========================================================================
    // 📌 CRÉATION D'UNE ENTRÉE
    // =========================================================================

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $entry = new AiAssistantEntry();
        $form = $this->createForm(AiAssistantEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entry->setKeywords($this->parseLines((string) $form->get('keywords')->getData()));
            $keywordsEn = $this->parseLines((string) $form->get('keywordsEn')->getData());
            $entry->setKeywordsEn($keywordsEn ?: null);

            $entityManager->persist($entry);
            $entityManager->flush();

            $auditLogger->log(AiAssistantEntry::class, $entry->getId(), $entry->getChipLabel(), 'created');
            $entityManager->flush();

            $this->addFlash('success', 'L\'entrée a été créée avec succès.');

            return $this->redirectToRoute('admin_ai_assistant_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/ai_assistant/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 MISE À JOUR D'UNE ENTRÉE
    // =========================================================================

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function update(Request $request, AiAssistantEntry $entry, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(AiAssistantEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entry->setKeywords($this->parseLines((string) $form->get('keywords')->getData()));
            $keywordsEn = $this->parseLines((string) $form->get('keywordsEn')->getData());
            $entry->setKeywordsEn($keywordsEn ?: null);

            $auditLogger->log(AiAssistantEntry::class, $entry->getId(), $entry->getChipLabel(), 'updated');
            $entityManager->flush();

            $this->addFlash('success', 'L\'entrée a été mise à jour avec succès.');

            return $this->redirectToRoute('admin_ai_assistant_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/ai_assistant/update.html.twig', [
            'entry' => $entry,
            'form' => $form->createView(),
        ]);
    }

    // =========================================================================
    // 📌 SUPPRESSION D'UNE ENTRÉE
    // =========================================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, AiAssistantEntry $entry, EntityManagerInterface $entityManager, AuditLogger $auditLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('admin_ai_assistant_delete_' . $entry->getId(), $request->request->get('_token'))) {
            $auditLogger->log(AiAssistantEntry::class, $entry->getId(), $entry->getChipLabel(), 'deleted');
            $entityManager->remove($entry);
            $entityManager->flush();

            $this->addFlash('success', 'L\'entrée a été supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_ai_assistant_index', [], Response::HTTP_SEE_OTHER);
    }

    /** @return string[] */
    private function parseLines(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw) ?: [])));
    }
}
