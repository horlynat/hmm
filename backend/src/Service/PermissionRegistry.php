<?php

namespace App\Service;

use App\Repository\PermissionDefinitionRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Source unique de résolution "code de permission → rôle minimum requis",
 * consultée par AbstractRoleVoter::voteOnAttribute() pour chaque décision
 * d'autorisation. Point d'entrée unique du RBAC dynamique — cf. le docblock
 * de PermissionDefinition pour le périmètre exact (Voters éligibles).
 *
 * Garanties de sécurité (dans cet ordre, chacune protège la précédente) :
 * 1. Fail-safe, jamais fail-open : `$fallbackRole` (le seuil codé en dur du
 *    Voter appelant) est TOUJOURS le résultat si la base est vide, en panne,
 *    ou si aucune ligne n'existe pour ce code — jamais un accès plus large
 *    par défaut. Le code du Voter reste la vérité de dernier recours, la
 *    base n'est qu'une surcouche optionnelle.
 * 2. Liste noire de préfixes (isOverridable()) : les codes SECURITY_* et
 *    SETTINGS_* ne sont JAMAIS résolus dynamiquement, même si une ligne
 *    existait en base pour l'un d'eux (ex: insertion SQL directe, bug de
 *    seed) — ces permissions gouvernent la sécurité et les réglages système
 *    eux-mêmes ; les rendre éditables via l'interface qu'elles protègent
 *    créerait un chemin d'escalade de privilèges.
 * 3. Cache applicatif (6h, invalidé explicitement à chaque écriture par
 *    AdminSecurityPermissionController) : un contrôle d'autorisation a lieu
 *    à quasiment chaque requête admin, une lecture base par contrôle serait
 *    un coût inutile pour une donnée qui change rarement.
 */
class PermissionRegistry
{
    private const CACHE_KEY = 'permission_registry_map';
    private const CACHE_TTL = 21600; // 6h — invalidation explicite à l'écriture, TTL en filet de sécurité seulement.

    /** Préfixes jamais consultés en base, quelle que soit la donnée présente — cf. docblock de classe, point 2. */
    private const NON_OVERRIDABLE_PREFIXES = ['SECURITY_', 'SETTINGS_'];

    public function __construct(
        private readonly PermissionDefinitionRepository $repository,
        private readonly CacheInterface $cache,
    ) {
    }

    public static function isOverridable(string $code): bool
    {
        foreach (self::NON_OVERRIDABLE_PREFIXES as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rôle effectif à appliquer pour $code. Retourne $fallbackRole si $code
     * n'est pas overridable, absent du catalogue, ou en cas d'échec cache/DB.
     */
    public function resolveRole(string $code, string $fallbackRole): string
    {
        if (!self::isOverridable($code)) {
            return $fallbackRole;
        }

        try {
            $map = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
                $item->expiresAfter(self::CACHE_TTL);

                $map = [];
                foreach ($this->repository->findAllOrdered() as $definition) {
                    $map[$definition->getCode()] = $definition->getCurrentRole()->getCode();
                }

                return $map;
            });
        } catch (\Throwable) {
            // Cache ou base indisponible : on ne bloque jamais une décision
            // d'autorisation là-dessus, on retombe sur le seuil codé en dur.
            return $fallbackRole;
        }

        return $map[$code] ?? $fallbackRole;
    }

    /** Invalidation explicite — appelée par AdminSecurityPermissionController après chaque écriture. */
    public function invalidate(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }
}
