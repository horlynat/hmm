<?php

namespace App\Service;

use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Envoi du message de test d'un webhook d'intégration (back-office,
 * AdminIntegrationController::test) — URL saisie par un admin, donc requête
 * sortante à contenir (SSRF, audit 2026-11-20 P2) :
 * - NoPrivateNetworkHttpClient : l'IP est vérifiée au moment de la
 *   connexion (résolution DNS faite par le client lui-même, puis IP réelle
 *   de chaque connexion), ce qui ferme le contournement par DNS rebinding de
 *   la simple vérification préalable (gethostbyname) du contrôleur ;
 * - `max_redirects: 0` : une redirection (302 vers http://169.254.169.254/…)
 *   n'est jamais suivie — un webhook Slack ne redirige pas.
 */
final class WebhookTestSender
{
    private readonly HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = new NoPrivateNetworkHttpClient($httpClient);
    }

    /**
     * @return bool true si le webhook a répondu 2xx
     */
    public function send(string $url, string $text): bool
    {
        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => ['text' => $text],
                'timeout' => 10,
                'max_redirects' => 0,
            ]);

            return $response->getStatusCode() < 300;
        } catch (HttpClientExceptionInterface) {
            return false;
        }
    }
}
