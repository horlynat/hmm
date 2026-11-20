<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Défense en profondeur (P0 #4) : sur l'hôte public de l'API
 * (api.horlynat.com), seule la surface publique est servie — jamais le
 * back-office (/login, /admin, /2fa, /profile, /register, /_components…),
 * qui n'est censé être joignable que derrière Cloudflare Access sur l'hôte
 * back-office (dark.horlynat.com). Le routeur Traefik est la première
 * barrière (PathPrefix) ; ce listener garantit la même règle si Traefik est
 * mal configuré, contourné, ou si l'origine est atteinte directement.
 *
 * Surface autorisée sur l'hôte API — alignée EXACTEMENT sur les préfixes
 * auxquels le routeur Traefik `api` restreint déjà ce service (lot infra,
 * `Host(api.horlynat.com) && (PathPrefix(/api) || PathPrefix(/uploads) ||
 * PathPrefix(/newsletter))`) : ce listener ne fait que reproduire côté
 * applicatif la même liste, pour rester vrai même si Traefik est mal
 * configuré, contourné, ou si l'origine est atteinte directement.
 *  - /api/**        : API Platform + contrôleurs JSON (frontend Next.js) ;
 *  - /uploads/**    : médias publics (next/image, cf. remotePatterns) ;
 *  - /newsletter/** : liens de confirmation/désinscription cliqués depuis un
 *                     client mail par un visiteur sans compte.
 * Tout le reste ATTEINT PAR PHP (back-office, /_components…) répond 404 ici.
 *
 * Limite assumée : en prod, Caddy (`php_server` + `file_server`, cf.
 * frankenphp/Caddyfile) sert DIRECTEMENT tout fichier existant de public/
 * (`/build/**`, `/bundles/**`, `/uploads/**`, et tout autre fichier statique)
 * sans jamais passer par PHP — ce listener ne les voit donc pas. Pour ces
 * chemins, la seule barrière sur l'hôte API est le routeur Traefik.
 *
 * Réponse : 404 nue, identique pour toute route existante ou non (aucune
 * indication que le back-office existe sur ce serveur). Priorité 256 : avant
 * le routeur (32), les firewalls (8) et la session.
 *
 * Inactif tant que PUBLIC_API_HOSTS est vide (dev, tests).
 */
final class PublicApiHostRestrictionSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_PATH = '#^/(?:api|uploads|newsletter)(?:/|$)#';

    /** @var list<string> */
    private readonly array $apiHosts;

    public function __construct(
        #[Autowire('%env(default::PUBLIC_API_HOSTS)%')]
        ?string $publicApiHosts,
    ) {
        $this->apiHosts = self::parseHostList($publicApiHosts);
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 256]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ([] === $this->apiHosts || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!\in_array(self::normalizeHost($request->getHost()), $this->apiHosts, true)) {
            return;
        }

        // Liste blanche sur le chemin BRUT (non décodé) : une variante encodée
        // (/%61dmin, /api%2F..%2Fadmin…) ou doublée (//admin) ne correspond à
        // aucun préfixe autorisé et tombe donc dans le refus par défaut. Le
        // routeur ne résout pas les segments "..", on les refuse quand même
        // explicitement (aucune URL légitime n'en contient).
        $path = $request->getPathInfo();
        if (1 === preg_match(self::ALLOWED_PATH, $path) && !str_contains($path, '/../')) {
            return;
        }

        $event->setResponse(new Response('Not Found', Response::HTTP_NOT_FOUND, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]));
    }

    /**
     * @return list<string>
     */
    public static function parseHostList(?string $raw): array
    {
        if (null === $raw || '' === trim($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $host): string => self::normalizeHost($host),
            explode(',', $raw),
        ), static fn (string $host): bool => '' !== $host));
    }

    /**
     * Forme canonique d'un nom d'hôte pour la comparaison : minuscules, sans
     * espaces, sans point final. `api.horlynat.com.` (FQDN absolu) désigne le
     * MÊME hôte que `api.horlynat.com` pour le DNS et pour Traefik : sans ce
     * rtrim, `GET http://api.horlynat.com./login` échappait à la liste et
     * servait le back-office (contre-audit P0 #4).
     */
    public static function normalizeHost(string $host): string
    {
        return rtrim(strtolower(trim($host)), '.');
    }
}
