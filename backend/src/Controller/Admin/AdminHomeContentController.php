<?php

namespace App\Controller\Admin;

use App\Entity\HomeContent;
use App\Entity\User;
use App\Form\HomeContentType;
use App\Repository\HomeContentRepository;
use App\Repository\TranslationRepository;
use App\Service\AuditLogger;
use App\Service\ContentAutoTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Édition du contenu narratif de la page d'accueil (hero, teaser à propos,
 * pitch freelance, appel à l'action) — ligne unique, cf. AdminConfigController.
 *
 * 🔒 Sécurité : réservé à ROLE_ADMIN.
 */
#[Route('/admin/content/home', name: 'admin_home_content_')]
class AdminHomeContentController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        HomeContentRepository $homeContentRepository,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        ContentAutoTranslator $autoTranslator,
        TranslationRepository $translationRepository,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $content = $homeContentRepository->getContent();
        // Capturé AVANT `handleRequest` : sert de référence pour détecter quels
        // champs français ont changé (cf. ContentAutoTranslator::syncTranslations).
        $originalData = $entityManager->getUnitOfWork()->getOriginalEntityData($content);
        $form = $this->createForm(HomeContentType::class, $content);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $content->setHeroRoles($this->parseLines((string) $form->get('heroRoles')->getData()));
            $rolesEn = $this->parseLines((string) $form->get('heroRolesEn')->getData());
            $content->setHeroRolesEn($rolesEn ?: null);

            // Traduction automatique fr -> en des champs laissés vides, ou dont
            // le français a changé sans que l'anglais ait été retouché à la
            // main dans cette même soumission — jamais de blocage si l'appel
            // Claude échoue, le contenu français reste la source de vérité.
            $translatedFields = $autoTranslator->syncTranslations($content, $originalData);

            $user = $this->getUser();
            $content->setUpdatedAt(new \DateTimeImmutable());
            $content->setUpdatedBy($user instanceof User ? $user : null);
            $entityManager->flush();

            $auditLogger->log(HomeContent::class, (int) $content->getId(), 'home_content', 'updated');
            $entityManager->flush();

            // Écrit les champs "xxxEn" (transitoires, non mappés Doctrine)
            // dans la table `translation` — après flush() pour disposer d'un
            // id garanti même sur une entité tout juste créée.
            $translationRepository->syncFromEntity($content);

            $message = 'Le contenu de la page d\'accueil a été mis à jour avec succès.';
            if ($translatedFields) {
                $message .= ' Traduit automatiquement : '.implode(', ', $translatedFields).'.';
            }
            $this->addFlash('success', $message);

            return $this->redirectToRoute('admin_home_content_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/home_content/index.html.twig', [
            'content' => $content,
            'form' => $form->createView(),
        ]);
    }

    /** @return string[] */
    private function parseLines(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw) ?: [])));
    }
}
