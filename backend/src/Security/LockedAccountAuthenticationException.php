<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * Compte verrouillé (User::$lockedUntil) : levée AVANT la vérification du
 * mot de passe et présentée exactement comme un mauvais mot de passe
 * (même clé de message « Invalid credentials. ») — pendant un verrou, la
 * réponse ne doit jamais révéler si le mot de passe saisi était le bon
 * (P1 #10). La distinction n'existe que côté serveur, pour le journal.
 */
final class LockedAccountAuthenticationException extends BadCredentialsException
{
}
