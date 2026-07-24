<?php

namespace App\Security\Voter;

use App\Entity\Article;

/**
 * Permissions sur les articles de blog.
 *
 * - VIEW          : Éditeur et plus.
 * - CREATE / EDIT : Éditeur et plus.
 * - DELETE        : Modérateur et plus (action irréversible).
 */
class ArticleVoter extends AbstractRoleVoter
{
    public const VIEW = 'ARTICLE_VIEW';
    public const CREATE = 'ARTICLE_CREATE';
    public const EDIT = 'ARTICLE_EDIT';
    public const DELETE = 'ARTICLE_DELETE';

    protected function getRequiredRole(string $attribute, mixed $subject): ?string
    {
        return match (true) {
            self::CREATE === $attribute && null === $subject => 'ROLE_EDITOR',
            self::VIEW === $attribute && ($subject instanceof Article || null === $subject) => 'ROLE_USER',
            self::EDIT === $attribute && $subject instanceof Article => 'ROLE_EDITOR',
            self::DELETE === $attribute && $subject instanceof Article => 'ROLE_MODERATOR',
            default => null,
        };
    }
}
