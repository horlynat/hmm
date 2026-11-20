<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Historique de conversation envoyé par le client à l'assistant IA
 * (AiAssistantChatApiResource, AiContentSummaryApiResource) : rôles
 * strictement alternés user/assistant, et dernier tour toujours `assistant`
 * — la question courante est ajoutée ensuite en tant que `user`
 * (App\Service\ClaudeClient::ask), le message final envoyé à Claude n'est
 * donc JAMAIS un tour `assistant` (pas de « préremplissage » de la réponse
 * par l'appelant). Le premier tour peut être `assistant` : c'est le message
 * d'accueil affiché par le widget (cf. AiAssistantWidget.tsx).
 *
 * La forme de chaque tour (clés, longueurs, valeurs de `role`) reste
 * vérifiée par les contraintes Collection voisines ; celle-ci ne regarde que
 * l'enchaînement, et ignore les tours mal formés (déjà signalés ailleurs).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ConversationHistory extends Constraint
{
    public string $alternationMessage = "L'historique doit alterner strictement les tours utilisateur et assistant.";
    public string $lastTurnMessage = "Le dernier tour de l'historique doit être une réponse de l'assistant.";
}
