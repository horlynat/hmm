<?php

namespace App\Tests\Entity;

use App\Entity\PermissionDefinition;
use App\Entity\Role;
use PHPUnit\Framework\TestCase;

final class PermissionDefinitionTest extends TestCase
{
    public function testCurrentRoleDefaultsToDefaultRoleAtConstruction(): void
    {
        $editor = new Role('ROLE_EDITOR', 'Éditeur', 1);
        $definition = new PermissionDefinition('ARTICLE_EDIT', 'Modifier un article', 'Articles', $editor);

        self::assertSame($editor, $definition->getCurrentRole());
        self::assertFalse($definition->isOverridden());
    }

    public function testIsOverriddenTrueOnceCurrentRoleDiffersFromDefault(): void
    {
        $editor = new Role('ROLE_EDITOR', 'Éditeur', 1);
        $moderator = new Role('ROLE_MODERATOR', 'Modérateur', 2);

        $definition = new PermissionDefinition('ARTICLE_EDIT', 'Modifier un article', 'Articles', $editor);
        $definition->setCurrentRole($moderator);

        self::assertTrue($definition->isOverridden());
        self::assertSame($editor, $definition->getDefaultRole());
    }

    public function testIsOverriddenFalseAgainAfterResetToDefault(): void
    {
        $editor = new Role('ROLE_EDITOR', 'Éditeur', 1);
        $moderator = new Role('ROLE_MODERATOR', 'Modérateur', 2);

        $definition = new PermissionDefinition('ARTICLE_EDIT', 'Modifier un article', 'Articles', $editor);
        $definition->setCurrentRole($moderator);
        $definition->setCurrentRole($editor);

        self::assertFalse($definition->isOverridden());
    }
}
