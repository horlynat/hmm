<?php

namespace App\Tests\Service;

use App\Entity\PermissionDefinition;
use App\Entity\Role;
use App\Repository\PermissionDefinitionRepository;
use App\Service\PermissionRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class PermissionRegistryTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function nonOverridablePrefixProvider(): iterable
    {
        yield 'SECURITY_' => ['SECURITY_MANAGE_LOGS'];
        yield 'SETTINGS_' => ['SETTINGS_RESTORE_BACKUP'];
    }

    #[DataProvider('nonOverridablePrefixProvider')]
    public function testIsOverridableRejectsGovernancePrefixes(string $code): void
    {
        self::assertFalse(PermissionRegistry::isOverridable($code));
    }

    public function testIsOverridableAcceptsBusinessPermission(): void
    {
        self::assertTrue(PermissionRegistry::isOverridable('ARTICLE_EDIT'));
    }

    /**
     * Garde-fou central : même si une ligne existait en base pour un code
     * gouvernance (préfixe SECURITY_ ou SETTINGS_, insertion directe, bug de
     * seed), le registre ne doit JAMAIS la consulter — sinon la permission
     * qui gouverne ce système deviendrait elle-même modifiable depuis
     * l'interface qu'elle protège.
     */
    public function testResolveRoleNeverConsultsCacheForNonOverridableCode(): void
    {
        $repository = $this->createMock(PermissionDefinitionRepository::class);
        $repository->expects($this->never())->method('findAllOrdered');

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');

        $registry = new PermissionRegistry($repository, $cache);

        self::assertSame('ROLE_ADMIN', $registry->resolveRole('SECURITY_MANAGE_LOGS', 'ROLE_ADMIN'));
    }

    public function testResolveRoleReturnsDbOverrideWhenPresent(): void
    {
        $definition = new PermissionDefinition('ARTICLE_EDIT', 'Modifier un article', 'Articles', new Role('ROLE_EDITOR', 'Éditeur', 1));
        $definition->setCurrentRole(new Role('ROLE_MODERATOR', 'Modérateur', 2));

        $repository = $this->createStub(PermissionDefinitionRepository::class);
        $repository->method('findAllOrdered')->willReturn([$definition]);

        $cache = $this->fakeCache();

        $registry = new PermissionRegistry($repository, $cache);

        self::assertSame('ROLE_MODERATOR', $registry->resolveRole('ARTICLE_EDIT', 'ROLE_EDITOR'));
    }

    public function testResolveRoleFallsBackWhenCodeAbsentFromCatalog(): void
    {
        $repository = $this->createStub(PermissionDefinitionRepository::class);
        $repository->method('findAllOrdered')->willReturn([]);

        $registry = new PermissionRegistry($repository, $this->fakeCache());

        self::assertSame('ROLE_EDITOR', $registry->resolveRole('ARTICLE_EDIT', 'ROLE_EDITOR'));
    }

    /**
     * Fail-safe, jamais fail-open : une base/cache en panne ne doit jamais
     * élargir un accès, elle retombe sur le seuil codé en dur du Voter.
     */
    public function testResolveRoleFallsBackOnCacheFailure(): void
    {
        $repository = $this->createStub(PermissionDefinitionRepository::class);

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willThrowException(new \RuntimeException('cache down'));

        $registry = new PermissionRegistry($repository, $cache);

        self::assertSame('ROLE_EDITOR', $registry->resolveRole('ARTICLE_EDIT', 'ROLE_EDITOR'));
    }

    public function testInvalidateDeletesCacheKey(): void
    {
        $repository = $this->createStub(PermissionDefinitionRepository::class);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('delete')->with('permission_registry_map');

        $registry = new PermissionRegistry($repository, $cache);
        $registry->invalidate();
    }

    private function fakeCache(): CacheInterface
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(
            fn (string $key, callable $callback) => $callback($this->createStub(ItemInterface::class))
        );

        return $cache;
    }
}
