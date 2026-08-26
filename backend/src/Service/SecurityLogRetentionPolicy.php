<?php

namespace App\Service;

/**
 * Durées de rétention du journal de connexions — source unique partagée entre
 * la commande cron (SecurityLogPurgeCommand) et le bouton de purge manuelle
 * (AdminSecurityLogController::purge()), pour ne jamais avoir deux valeurs
 * qui divergent silencieusement.
 *
 * Deux durées distinctes, pas une seule :
 * - LoginHistory (connexions réussies) : valeur d'audit long terme (qui s'est
 *   connecté, quand, depuis où) et volumétrie faible (une ligne par connexion
 *   d'un utilisateur légitime) → rétention longue.
 * - FailedLoginAttempt (tentatives échouées) : volumétrie potentiellement
 *   élevée (bruteforce, bots) et valeur d'audit qui décroît vite passé
 *   quelques semaines → même rétention que les logs de l'assistant IA
 *   (AiAssistantPurgeLogsCommand::RETENTION_DAYS), déjà le standard du projet
 *   pour ce type de données volumineuses/peu sensibles individuellement.
 */
final class SecurityLogRetentionPolicy
{
    public const LOGIN_HISTORY_RETENTION_DAYS = 365;
    public const FAILED_ATTEMPT_RETENTION_DAYS = 90;
}
