<?php

namespace App\Service;

/**
 * Détection heuristique best-effort de prompt injection sur l'input
 * utilisateur, avant tout appel Claude — coupe court immédiatement si un
 * motif suspect est détecté (économise le coût de l'appel). Défense en
 * profondeur, pas un filtre exhaustif : le vrai garde-fou est la séparation
 * stricte system/user dans App\Service\ClaudeClient + le system prompt
 * structuré (cf. §4.5 du document d'architecture assistant IA).
 */
final class AiAssistantInputGuard
{
    private const SUSPICIOUS_PATTERNS = [
        '/ignor(e|es|ez)\s+(tes|vos|les)?\s*instructions?/i',
        '/oublie\s+(tes|vos|les)?\s*instructions?/i',
        '/tu\s+es\s+maintenant/i',
        '/you\s+are\s+now/i',
        '/ignore\s+(previous|all|your)\s+instructions?/i',
        '/reveal\s+(your\s+)?(system\s+)?prompt/i',
        '/révèle\s+(ton|le)\s+(system\s+)?prompt/i',
        '/act\s+as\s+(a|an)\s+\w+/i',
        '/jailbreak/i',
        '/\bDAN\b/',
        '/donne[- ]moi\s+(ta|la)\s+clé\s+api/i',
        '/give\s+me\s+(your|the)\s+api\s+key/i',
    ];

    public function isSuspicious(string $question): bool
    {
        // Bloc base64 anormalement long (>200 caractères) : rien de légitime dans une question de portfolio n'en a besoin.
        if (preg_match('/[A-Za-z0-9+\/]{200,}={0,2}/', $question)) {
            return true;
        }

        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $question)) {
                return true;
            }
        }

        return false;
    }
}
