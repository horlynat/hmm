<?php

namespace App\Controller\Admin;

use App\Entity\AboutContent;
use App\Entity\User;
use App\Form\AboutContentType;
use App\Repository\AboutContentRepository;
use App\Repository\TranslationRepository;
use App\Service\AuditLogger;
use App\Service\MediaUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Édition du contenu narratif de la page "À propos" (hero, profil, bio,
 * vision, différenciateurs, à-côtés, appel à l'action) — ligne unique,
 * cf. AdminConfigController.
 *
 * 🔒 Sécurité : réservé à ROLE_ADMIN.
 */
#[Route('/admin/content/about', name: 'admin_about_content_')]
class AdminAboutContentController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AboutContentRepository $aboutContentRepository,
        EntityManagerInterface $entityManager,
        MediaUploader $uploader,
        AuditLogger $auditLogger,
        TranslationRepository $translationRepository,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $content = $aboutContentRepository->getContent();
        $form = $this->createForm(AboutContentType::class, $content);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $profileImageFile = $form->get('profileImageFile')->getData();
            if ($profileImageFile instanceof UploadedFile) {
                $result = $uploader->upload($profileImageFile, 'about');
                $content->setProfileImagePath($result['path']);
            }

            $content->setBeyondLanguages($this->parseLines((string) $form->get('beyondLanguages')->getData()));
            $languagesEn = $this->parseLines((string) $form->get('beyondLanguagesEn')->getData());
            $content->setBeyondLanguagesEn($languagesEn ?: null);

            $content->setBeyondInterests($this->parseLines((string) $form->get('beyondInterests')->getData()));
            $interestsEn = $this->parseLines((string) $form->get('beyondInterestsEn')->getData());
            $content->setBeyondInterestsEn($interestsEn ?: null);

            $user = $this->getUser();
            $content->setUpdatedAt(new \DateTimeImmutable());
            $content->setUpdatedBy($user instanceof User ? $user : null);
            $entityManager->flush();

            $auditLogger->log(AboutContent::class, (int) $content->getId(), 'about_content', 'updated');
            $entityManager->flush();

            // Écrit les champs "xxxEn" (transitoires, non mappés Doctrine)
            // dans la table `translation` — après flush() pour disposer d'un
            // id garanti même sur une entité tout juste créée.
            $translationRepository->syncFromEntity($content);

            $this->addFlash('success', 'Le contenu de la page "À propos" a été mis à jour avec succès.');

            return $this->redirectToRoute('admin_about_content_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/about_content/index.html.twig', [
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
