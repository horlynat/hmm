<?php

namespace App\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class JWTService
{
    // Algorithmes supportés
    private const SUPPORTED_ALGORITHMS = ['HS256', 'HS384', 'HS512'];
    private const DEFAULT_ALGORITHM = 'HS256';

    // Durées de validité
    private const DEFAULT_VALIDITY = 10800; // 3 heures en secondes
    private const AUTH_VALIDITY = 3600; // 1 heure pour les tokens d'authentification
    private const EMAIL_VERIFICATION_VALIDITY = 86400; // 24 heures pour la vérification d'email

    // Limite de taille pour éviter les attaques DoS
    private const MAX_PAYLOAD_SIZE = 4096; // 4 Ko

    public function __construct(
        #[Autowire('%app.jwtsecret%')]
        private string $secret,
        private ?LoggerInterface $logger = null
    ) {}

    // =============================================
    // Méthodes de génération de tokens
    // =============================================

    /**
     * Génère un JWT pour la vérification d'email.
     *
     * @param int $userId ID de l'utilisateur
     * @param int $validity Durée de validité en secondes (par défaut : 24h)
     * @return string Token JWT
     * @throws InvalidArgumentException Si l'ID utilisateur est invalide
     */
    public function generateEmailVerificationToken(int $userId, int $validity = self::EMAIL_VERIFICATION_VALIDITY): string
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('L\'ID utilisateur doit être un entier positif.');
        }

        $header = [
            'typ' => 'JWT',
            'alg' => self::DEFAULT_ALGORITHM,
        ];

        $payload = [
            'user_id' => $userId,
            'purpose' => 'email_verification',
        ];

        return $this->generate($header, $payload, $validity);
    }

    /**
     * Génère un JWT pour l'authentification (login).
     *
     * @param int $userId ID de l'utilisateur
     * @param array $roles Rôles de l'utilisateur (doit être un tableau de strings)
     * @return string Token JWT
     * @throws InvalidArgumentException Si les rôles ne sont pas valides
     */
    public function generateAuthToken(int $userId, array $roles = []): string
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('L\'ID utilisateur doit être un entier positif.');
        }

        // Validation des rôles
        foreach ($roles as $role) {
            if (!is_string($role)) {
                throw new InvalidArgumentException('Les rôles doivent être des chaînes de caractères.');
            }
        }

        $header = [
            'typ' => 'JWT',
            'alg' => self::DEFAULT_ALGORITHM,
        ];

        $payload = [
            'user_id' => $userId,
            'roles' => $roles,
            'purpose' => 'authentication',
        ];

        return $this->generate($header, $payload, self::AUTH_VALIDITY);
    }

    /**
     * Génère un JWT avec les paramètres donnés.
     *
     * @param array $header En-tête du JWT
     * @param array $payload Charge utile du JWT
     * @param int $validity Durée de validité en secondes
     * @return string Token JWT
     * @throws InvalidArgumentException Si les paramètres sont invalides
     * @throws JsonException Si l'encodage JSON échoue
     */
    public function generate(array $header, array $payload, int $validity = self::DEFAULT_VALIDITY): string
    {
        try {
            // Validation de l'algorithme
            if (!isset($header['alg'])) {
                $header['alg'] = self::DEFAULT_ALGORITHM;
            }

            if (!in_array($header['alg'], self::SUPPORTED_ALGORITHMS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Algorithme non supporté : %s. Algorithmes supportés : %s',
                    $header['alg'],
                    implode(', ', self::SUPPORTED_ALGORITHMS)
                ));
            }

            // Ajout des claims standard
            $now = new DateTimeImmutable();
            $payload['iat'] = $now->getTimestamp();
            $payload['exp'] = $now->getTimestamp() + $validity;
            $payload['jti'] = $this->generateJti(); // Identifiant unique

            // Vérification de la taille
            $jsonHeader = json_encode($header, JSON_THROW_ON_ERROR);
            $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

            if (strlen($jsonHeader) > self::MAX_PAYLOAD_SIZE || strlen($jsonPayload) > self::MAX_PAYLOAD_SIZE) {
                throw new InvalidArgumentException('Header ou payload trop grand.');
            }

            // Encodage
            $base64Header = $this->base64UrlEncode($jsonHeader);
            $base64Payload = $this->base64UrlEncode($jsonPayload);

            // Génération de la signature
            $signature = hash_hmac(
                'sha256',
                $base64Header . '.' . $base64Payload,
                $this->secret,
                true
            );

            $base64Signature = $this->base64UrlEncode($signature);

            return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Échec de l\'encodage JSON du JWT : ' . $e->getMessage());
        }
    }

    // =============================================
    // Méthodes de validation de tokens
    // =============================================

    /**
     * Vérifie si un token est valide (format, signature, expiration).
     *
     * @param string $token Token JWT à vérifier
     * @param string|null $expectedPurpose Purpose attendu (ex: 'email_verification' ou 'authentication')
     * @return bool True si le token est valide
     */
    public function isValid(string $token, ?string $expectedPurpose = null): bool
    {
        try {
            $this->validate($token, $expectedPurpose);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Valide un token et retourne son payload.
     *
     * @param string $token Token JWT à valider
     * @param string|null $expectedPurpose Purpose attendu (ex: 'email_verification' ou 'authentication')
     * @return array Payload du token
     * @throws InvalidArgumentException Si le token est invalide
     */
    public function validate(string $token, ?string $expectedPurpose = null): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Token JWT mal formé : doit contenir 3 parties.');
        }

        $header = $this->getHeader($token);
        $payload = $this->getPayload($token);

        // Vérifier l'algorithme
        if (!in_array($header['alg'] ?? '', self::SUPPORTED_ALGORITHMS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Algorithme non supporté : %s. Algorithmes supportés : %s',
                $header['alg'] ?? 'non défini',
                implode(', ', self::SUPPORTED_ALGORITHMS)
            ));
        }

        // Vérifier la signature
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac(
                'sha256',
                $parts[0] . '.' . $parts[1],
                $this->secret,
                true
            )
        );

        if ($parts[2] !== $expectedSignature) {
            throw new InvalidArgumentException('Signature JWT invalide.');
        }

        // Vérifier l'expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new InvalidArgumentException('Token JWT expiré.');
        }

        // Vérifier le purpose si spécifié
        if ($expectedPurpose !== null && ($payload['purpose'] ?? '') !== $expectedPurpose) {
            throw new InvalidArgumentException(sprintf(
                'Token invalide : purpose attendu "%s", obtenu "%s".',
                $expectedPurpose,
                $payload['purpose'] ?? 'non défini'
            ));
        }

        return $payload;
    }

    // =============================================
    // Méthodes utilitaires
    // =============================================

    /**
     * Récupère le payload d'un token (sans validation).
     *
     * @param string $token Token JWT
     * @return array Payload décodé
     * @throws InvalidArgumentException Si le token est mal formé
     */
    public function getPayload(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Token JWT mal formé.');
        }

        try {
            $payload = json_decode($this->base64UrlDecode($parts[1]), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new InvalidArgumentException('Payload JWT invalide.');
            }
            return $payload;
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Échec du décodage JSON du payload JWT : ' . $e->getMessage());
        }
    }

    /**
     * Récupère le header d'un token (sans validation).
     *
     * @param string $token Token JWT
     * @return array Header décodé
     * @throws InvalidArgumentException Si le token est mal formé
     */
    public function getHeader(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Token JWT mal formé.');
        }

        try {
            $header = json_decode($this->base64UrlDecode($parts[0]), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($header)) {
                throw new InvalidArgumentException('Header JWT invalide.');
            }
            return $header;
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Échec du décodage JSON du header JWT : ' . $e->getMessage());
        }
    }

    /**
     * Vérifie si un token a expiré.
     *
     * @param string $token Token JWT
     * @return bool True si le token a expiré
     */
    public function isExpired(string $token): bool
    {
        try {
            $payload = $this->getPayload($token);
            return isset($payload['exp']) && $payload['exp'] < time();
        } catch (InvalidArgumentException) {
            return true; // Par défaut, considérer comme expiré en cas d'erreur
        }
    }

    /**
     * Vérifie si un token est un token d'authentification.
     *
     * @param string $token Token JWT
     * @return bool True si c'est un token d'authentification
     */
    public function isAuthToken(string $token): bool
    {
        try {
            $payload = $this->getPayload($token);
            return ($payload['purpose'] ?? '') === 'authentication';
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Vérifie si un token est un token de vérification d'email.
     *
     * @param string $token Token JWT
     * @return bool True si c'est un token de vérification d'email
     */
    public function isEmailVerificationToken(string $token): bool
    {
        try {
            $payload = $this->getPayload($token);
            return ($payload['purpose'] ?? '') === 'email_verification';
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    // =============================================
    // Méthodes privées
    // =============================================

    /**
     * Génère un identifiant unique (JWT ID) pour le token.
     *
     * @return string Identifiant unique
     */
    private function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Encode en Base64URL (sans padding).
     *
     * @param string $data Données à encoder
     * @return string Données encodées en Base64URL
     */
    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Décode depuis Base64URL.
     *
     * @param string $data Données à décoder
     * @return string Données décodées
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }

    /**
     * Journalise une erreur.
     *
     * @param string $message Message d'erreur
     * @param array $context Contexte supplémentaire
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }
}