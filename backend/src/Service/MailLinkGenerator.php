<?php

namespace App\Service;

use App\EventSubscriber\PublicApiHostRestrictionSubscriber;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * Seule façon autorisée de produire une URL absolue destinée à sortir de
 * l'application (e-mail, notification…) vers une route Symfony.
 *
 * Pourquoi pas `UrlGeneratorInterface::ABSOLUTE_URL` directement : en contexte
 * HTTP, le routeur prend le schéma/hôte/port de la REQUÊTE COURANTE — donc,
 * derrière un proxy de confiance, ce que le client a mis dans Host /
 * X-Forwarded-Host. Un `POST /api/forgot-password` anonyme avec
 * `X-Forwarded-Host: evil.tld` faisait recevoir à un vrai administrateur un
 * lien de réinitialisation pointant vers evil.tld (jeton volé au premier clic).
 *
 * Ici, la base est TOUJOURS une valeur de configuration :
 * - publicUrl()     : DEFAULT_URI — hôte public de l'API (ex.
 *                     https://api.horlynat.com), pour les liens cliqués par
 *                     des visiteurs sans compte back-office (newsletter) ;
 * - backOfficeUrl() : BACKOFFICE_URL — hôte du back-office (ex.
 *                     https://dark.horlynat.com), pour les liens destinés aux
 *                     comptes ROLE_ADMIN+ (réinitialisation, vérification,
 *                     profil…). Obligatoire en prod (exception sinon) ;
 *                     retombe sur DEFAULT_URI hors prod.
 *
 * Le contexte du routeur est restauré dans tous les cas (finally) : le
 * service est partagé entre requêtes en mode worker FrankenPHP.
 */
final class MailLinkGenerator
{
    public function __construct(
        private readonly RouterInterface $router,
        #[Autowire(env: 'DEFAULT_URI')]
        private readonly string $publicBaseUrl,
        #[Autowire('%env(default::BACKOFFICE_URL)%')]
        private readonly ?string $backOfficeBaseUrl,
        #[Autowire('%env(default::PUBLIC_API_HOSTS)%')]
        private readonly ?string $publicApiHosts,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function publicUrl(string $route, array $parameters = []): string
    {
        return $this->generate($this->publicBaseUrl, $route, $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function backOfficeUrl(string $route, array $parameters = []): string
    {
        return $this->generate($this->backOfficeBase(), $route, $parameters);
    }

    /**
     * Base des liens back-office.
     *
     * En prod, échec FRANC (exception, donc aucun e-mail envoyé) plutôt qu'un
     * repli silencieux si BACKOFFICE_URL est vide ou désigne un hôte de
     * PUBLIC_API_HOSTS : un lien admin vers l'hôte API mènerait à une 404
     * (P0 #4) — ou, si cette restriction sautait, vers un back-office hors
     * Cloudflare Access. Hors prod (dev/test), repli sur DEFAULT_URI.
     */
    private function backOfficeBase(): string
    {
        $base = trim((string) $this->backOfficeBaseUrl);
        $isProd = 'prod' === $this->environment;

        if ('' === $base) {
            if ($isProd) {
                throw new \LogicException('BACKOFFICE_URL doit être défini en production (base des liens e-mail des comptes back-office, ex. https://dark.horlynat.com).');
            }

            return $this->publicBaseUrl;
        }

        $host = PublicApiHostRestrictionSubscriber::normalizeHost((string) parse_url($base, \PHP_URL_HOST));
        if ($isProd && \in_array($host, PublicApiHostRestrictionSubscriber::parseHostList($this->publicApiHosts), true)) {
            throw new \LogicException(sprintf('BACKOFFICE_URL (%s) ne doit pas désigner un hôte de PUBLIC_API_HOSTS : le back-office n\'y est pas servi.', $base));
        }

        return $base;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function generate(string $baseUrl, string $route, array $parameters): string
    {
        $context = self::contextFromBaseUrl($baseUrl);

        $previous = $this->router->getContext();
        $this->router->setContext($context);
        try {
            return $this->router->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        } finally {
            $this->router->setContext($previous);
        }
    }

    /**
     * Refuse toute base qui ne serait pas une URL http(s) absolue : mieux vaut
     * un e-mail non envoyé (exception visible) qu'un lien vers un hôte
     * imprévu ou relatif.
     */
    public static function contextFromBaseUrl(string $baseUrl): RequestContext
    {
        $parts = parse_url(trim($baseUrl));
        if (false === $parts || !isset($parts['scheme'], $parts['host']) || !\in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new \LogicException(sprintf('Base d\'URL invalide pour les liens sortants : "%s" (attendu : http(s)://hôte[:port][/chemin]).', $baseUrl));
        }

        return RequestContext::fromUri(rtrim(trim($baseUrl), '/'));
    }
}
