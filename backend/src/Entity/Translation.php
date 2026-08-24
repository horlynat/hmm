<?php

namespace App\Entity;

use App\Repository\TranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Table de traduction générique : une ligne = une valeur traduite d'un
 * champ d'une entité bilingue (HomeContent, AboutContent, Project, Article),
 * pour une locale donnée. Remplace les colonnes "xxxEn" auparavant dupliquées
 * sur chaque entité (cf. migration Version20260824000000).
 *
 * `entityType` est le nom court de la classe (ex. "HomeContent"), pas le FQCN
 * complet — stable si l'entité est un jour déplacée de namespace.
 * `field` est le nom de propriété camelCase (ex. "heroSub"), pas le nom de
 * colonne SQL d'origine.
 *
 * Le français reste une colonne native sur chaque entité (source de vérité,
 * toujours présente, validation `NotBlank`) — seule la ou les traductions
 * passent par cette table. Voir App\Service\BilingualFieldReflector pour la
 * découverte par réflexion des paires de champs, et
 * App\EventListener\TranslationHydrationListener pour le chargement
 * automatique au postLoad.
 */
#[ORM\Entity(repositoryClass: TranslationRepository::class)]
#[ORM\Table(name: 'translation')]
#[ORM\UniqueConstraint(name: 'uniq_translation_target', columns: ['entity_type', 'entity_id', 'field', 'locale'])]
#[ORM\Index(name: 'idx_translation_entity', columns: ['entity_type', 'entity_id'])]
class Translation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $entityType;

    #[ORM\Column]
    private int $entityId;

    #[ORM\Column(length: 100)]
    private string $field;

    #[ORM\Column(length: 5)]
    private string $locale;

    /** Chaîne, ou tableau JSON-encodé pour les champs de type liste (ex. heroRoles). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $value = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;

        return $this;
    }
}
