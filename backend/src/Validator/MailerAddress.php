<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Adresse que le mailer acceptera réellement (contre-audit B3).
 *
 * `Assert\Email` (mode html5, et même « strict ») accepte des adresses que
 * Symfony Mime refuse au moment de l'envoi (`.a@x`, `a..b@x`, `a.@x`,
 * partie locale entre guillemets…) : `RfcComplianceException` levée APRÈS
 * l'écriture en base → 500 anonyme, compte orphelin, puis 500 à chaque
 * « mot de passe oublié ». Cette contrainte applique exactement la même
 * vérification que le mailer (`new Address()`), donc aucun écart possible.
 * À poser à côté de `Assert\Email` sur toute adresse à laquelle on écrira.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class MailerAddress extends Constraint
{
    public string $message = "Cette adresse e-mail ne peut pas recevoir de message.";
}
