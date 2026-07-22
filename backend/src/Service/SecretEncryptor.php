<?php

namespace App\Service;

/**
 * Chiffrement symétrique (libsodium) des secrets d'intégration (clés API, tokens).
 *
 * La clé est dérivée de kernel.secret (APP_SECRET) : elle n'est donc jamais
 * stockée séparément, mais un changement d'APP_SECRET rend les secrets déjà
 * chiffrés en base indéchiffrables (à ressaisir).
 */
final class SecretEncryptor
{
    private readonly string $key;

    public function __construct(string $appSecret)
    {
        $this->key = hash('sha256', $appSecret, true);
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return base64_encode($nonce . $cipher);
    }

    public function decrypt(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);
        if (false === $decoded || \strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Secret invalide : encodage inattendu.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);

        if (false === $plaintext) {
            throw new \RuntimeException('Secret invalide : déchiffrement échoué (APP_SECRET a-t-il changé ?).');
        }

        return $plaintext;
    }
}
