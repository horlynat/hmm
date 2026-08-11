<?php

namespace App\Service;

/**
 * Heuristique de choix entre Sonnet (raisonnement/argumentation poussée,
 * qualification de projet) et Haiku (questions simples type FAQ), pour
 * optimiser le coût sans dégrader l'expérience sur les échanges à enjeu
 * commercial (cf. §3.3 du document d'architecture assistant IA).
 */
final class AiAssistantModelSelector
{
    private const COMMERCIAL_KEYWORDS = [
        'devis', 'tarif', 'prix', 'budget', 'projet', 'collaborer', 'collaboration',
        'disponible', 'disponibilité', 'embaucher', 'recruter', 'freelance', 'mission',
        'quote', 'price', 'pricing', 'hire', 'available', 'availability', 'project',
    ];

    private const LONG_QUESTION_THRESHOLD = 180;

    public function useSonnet(string $question): bool
    {
        if (mb_strlen($question) > self::LONG_QUESTION_THRESHOLD) {
            return true;
        }

        $lower = mb_strtolower($question);
        foreach (self::COMMERCIAL_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
