<?php

namespace App\Doctrine;

use App\Entity\Article;
use App\Entity\Project;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Sanitise le HTML rédigé dans l'éditeur riche (Quill) AVANT persistance —
 * corps d'article (Article::content/contentEn) et description de projet
 * (Project::description/descriptionEn).
 *
 * Point de passage unique : couvre à la fois les formulaires back-office
 * (App\Form\ArticleType / App\Form\ProjectType) et les écritures API Platform
 * (App\ApiResource\ArticleApiResource / ProjectApiResource, réservées à
 * ROLE_ADMIN). Le back-office rend ensuite ces champs via le filtre `raw`
 * (gabarits `read.html.twig` de l'admin) — sans ce garde-fou, un payload
 * injecté par un rédacteur ROLE_ADMIN s'exécutait pour tout autre admin
 * ouvrant la fiche (la CSP admin autorise 'unsafe-inline' sur script-src).
 * La sanitisation n'existait jusqu'ici que côté frontend Next.js
 * (frontend/src/lib/sanitize.ts).
 *
 * Enregistré explicitement en `doctrine.event_listener` (cf. config/services.yaml)
 * plutôt que via l'attribut AsDoctrineListener : même convention que les
 * autres listeners Doctrine du projet (TotpSecretEncryptionListener...).
 */
final class RichTextSanitizerListener
{
    /**
     * Champs HTML riche par entité — cf. assets/controllers/rich_text_controller.js
     * ("Article content/contentEn, Project description/descriptionEn").
     *
     * @var array<class-string, list<string>>
     */
    private const RICH_TEXT_FIELDS = [
        Article::class => ['content', 'contentEn'],
        Project::class => ['description', 'descriptionEn'],
    ];

    public function __construct(
        private readonly HtmlSanitizerInterface $richTextSanitizer,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        foreach ($this->fieldsFor($entity) as $field) {
            $getter = 'get'.ucfirst($field);
            $setter = 'set'.ucfirst($field);
            $value = $entity->{$getter}();

            if (\is_string($value) && '' !== $value) {
                $entity->{$setter}($this->richTextSanitizer->sanitize($value));
            }
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        foreach ($this->fieldsFor($entity) as $field) {
            if (!$args->hasChangedField($field)) {
                continue;
            }

            $new = $args->getNewValue($field);
            if (\is_string($new) && '' !== $new) {
                $args->setNewValue($field, $this->richTextSanitizer->sanitize($new));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function fieldsFor(object $entity): array
    {
        foreach (self::RICH_TEXT_FIELDS as $class => $fields) {
            if ($entity instanceof $class) {
                return $fields;
            }
        }

        return [];
    }
}
