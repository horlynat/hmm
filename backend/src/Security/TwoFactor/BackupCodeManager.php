<?php

namespace App\Security\TwoFactor;

use App\Entity\User;

/**
 * Génère, vérifie et invalide les codes de récupération 2FA.
 *
 * Les codes sont générés aléatoirement (haute entropie) et affichés une seule
 * fois à l'utilisateur ; seule leur empreinte SHA-256 est stockée en base
 * (User::backupCodes). Un hash rapide suffit ici — contrairement à un mot de
 * passe choisi par un humain, un code aléatoire de 40 bits n'est pas
 * attaquable par dictionnaire — et la comparaison se fait en temps constant
 * (hash_equals) pour ne pas fuiter d'information par timing.
 *
 * Chaque code est à usage unique : il est retiré de la liste dès qu'il est
 * consommé (voir BackupCodeAwareTwoFactorProvider).
 */
final class BackupCodeManager
{
    public const CODE_COUNT = 10;

    /**
     * Génère un nouveau lot de codes, stocke leurs empreintes sur l'utilisateur
     * (écrase tout lot précédent) et retourne les codes EN CLAIR — à afficher
     * une seule fois, ils ne pourront plus jamais être récupérés ensuite.
     *
     * @return list<string> codes en clair, format « xxxx-xxxx » (hex)
     */
    public function generate(User $user): array
    {
        $plainCodes = [];
        $hashes = [];

        for ($i = 0; $i < self::CODE_COUNT; ++$i) {
            $raw = bin2hex(random_bytes(4));
            $plainCodes[] = $this->formatCode($raw);
            // On hache la forme normalisée (sans tiret) pour que la validation,
            // qui normalise aussi la saisie, retrouve la même empreinte.
            $hashes[] = $this->hash($this->normalize($raw));
        }

        $user->setBackupCodes($hashes);

        return $plainCodes;
    }

    /**
     * Indique si le code fourni (en clair) correspond à un code de récupération
     * encore valide de l'utilisateur.
     */
    public function isValid(User $user, string $code): bool
    {
        $candidate = $this->hash($this->normalize($code));

        foreach ($user->getBackupCodes() as $stored) {
            if (hash_equals($stored, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Consomme (retire définitivement) le code fourni. Sans effet si le code
     * n'est pas/plus valide.
     */
    public function invalidate(User $user, string $code): void
    {
        $candidate = $this->hash($this->normalize($code));

        $remaining = array_filter(
            $user->getBackupCodes(),
            static fn (string $stored): bool => !hash_equals($stored, $candidate),
        );

        $user->setBackupCodes(array_values($remaining));
    }

    public function countRemaining(User $user): int
    {
        return \count($user->getBackupCodes());
    }

    private function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    private function formatCode(string $hex): string
    {
        return substr($hex, 0, 4).'-'.substr($hex, 4, 4);
    }

    /**
     * Tolère les espaces et l'absence de tiret dans la saisie utilisateur.
     */
    private function normalize(string $code): string
    {
        return strtolower(str_replace([' ', '-'], '', trim($code)));
    }
}
