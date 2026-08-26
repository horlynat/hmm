<?php

namespace App\Controller\Admin;

use App\Service\GeminiTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Traduction fr <-> en d'un champ, à la volée, pendant la frappe dans un
 * formulaire bilingue du back-office (cf. FieldPair.html.twig +
 * assets/controllers/bilingual_field_controller.js). Lecture seule : ne
 * modifie jamais rien en base — l'enregistrement du formulaire reste le seul
 * point d'écriture (aucun filet de traduction automatique côté serveur : une
 * traduction en direct ratée laisse simplement le champ à compléter à la main).
 *
 * Protégé par la même session ROLE_ADMIN que le reste du back-office ; pas
 * de jeton CSRF explicite requis, la protection CSRF "same-origin" globale
 * de Symfony (framework.csrf_protection, cf. config/packages/csrf.yaml)
 * couvre déjà les requêtes fetch() JS same-origin.
 *
 * 🔒 Sécurité : réservé à ROLE_ADMIN.
 */
#[Route('/admin/translate', name: 'admin_translate_')]
class AdminTranslateController extends AbstractController
{
    #[Route('', name: 'field', methods: ['POST'])]
    public function field(
        Request $request,
        GeminiTranslator $translator,
        #[Autowire(service: 'limiter.admin_translate')] RateLimiterFactory $adminTranslateLimiter,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Un appel par pause de frappe (débounce ~900ms côté client) reste
        // rare en usage normal ; ce plafond ne protège que contre un
        // dysfonctionnement du debounce ou un usage anormal, pas un usage
        // légitime intensif.
        $limit = $adminTranslateLimiter->create((string) $this->getUser()?->getUserIdentifier());
        if (false === $limit->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Trop de requêtes de traduction, patientez un instant.'], 429);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Requête invalide.'], 400);
        }

        $text = is_string($payload['text'] ?? null) ? $payload['text'] : '';
        $targetLocale = is_string($payload['targetLocale'] ?? null) ? $payload['targetLocale'] : '';
        $sourceLocale = 'en' === $targetLocale ? 'fr' : 'en';

        if ('' === trim($text) || !in_array($targetLocale, ['fr', 'en'], true)) {
            return $this->json(['error' => 'Champs "text" et "targetLocale" (fr|en) requis.'], 400);
        }

        // Un texte trop long dépasse ce qu'un champ de formulaire porte
        // légitimement — protège le coût/latence plutôt qu'une vraie limite
        // fonctionnelle (les champs bilingues de ce back-office sont tous
        // des titres/paragraphes courts, jamais des articles entiers).
        if (mb_strlen($text) > 4000) {
            return $this->json(['error' => 'Texte trop long pour la traduction en direct.'], 400);
        }

        try {
            $translated = $translator->translate($text, $sourceLocale, $targetLocale);
        } catch (\Throwable) {
            return $this->json(['error' => 'Traduction indisponible pour le moment.'], 502);
        }

        return $this->json(['translated' => $translated]);
    }
}
