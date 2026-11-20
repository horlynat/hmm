<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Paire de clés JWT jetable pour les tests fonctionnels (lexik) : jamais
// committée (var/ est ignoré), régénérée si absente — la CI n'a pas de
// config/jwt/*.pem et ne doit surtout pas en avoir. Cf. .env.test qui y pointe.
(static function (): void {
    $dir = dirname(__DIR__).'/var/jwt-test';
    $private = $dir.'/private.pem';
    $public = $dir.'/public.pem';
    if (is_file($private) && is_file($public)) {
        return;
    }

    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException(sprintf('Impossible de créer "%s".', $dir));
    }

    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    if (false === $key || !openssl_pkey_export($key, $privatePem, 'test-passphrase')) {
        throw new RuntimeException('Génération de la clé JWT de test impossible.');
    }
    $details = openssl_pkey_get_details($key);
    if (false === $details) {
        throw new RuntimeException('Lecture de la clé publique JWT de test impossible.');
    }

    file_put_contents($private, $privatePem);
    file_put_contents($public, $details['key']);
})();
