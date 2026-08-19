<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Représentation relationnelle des rôles Symfony natifs (ROLE_USER...
 * ROLE_SUPER_ADMIN) — sert de cible de clé étrangère à
 * PermissionDefinition::$defaultRole/$currentRole (auparavant de simples
 * chaînes). Ne remplace PAS User::$roles (colonne JSON, source de vérité
 * consultée par Symfony Security à chaque décision d'autorisation) : cette
 * table est une couche d'administration/traçabilité au-dessus, pas
 * l'enforcement lui-même.
 *
 * `rank` reflète l'ordre de role_hierarchy (config/packages/security.yaml) —
 * à garder synchronisé si celle-ci change. Les 6 lignes sont seedées par la
 * migration qui a introduit cette table ; ce catalogue n'a pas vocation à
 * être étendu depuis l'admin (créer un rôle ici sans l'ajouter à
 * role_hierarchy n'aurait aucun effet sur les autorisations réelles).
 */
#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'role')]
#[ORM\UniqueConstraint(columns: ['code'])]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    /** Code du rôle Symfony, ex: "ROLE_EDITOR". */
    #[ORM\Column(length: 30)]
    #[Groups(['api_admin'])]
    private string $code;

    /** Libellé humain, ex: "Éditeur". */
    #[ORM\Column(length: 100)]
    #[Groups(['api_admin'])]
    private string $label;

    /** Position dans la hiérarchie (0 = ROLE_USER, croissant) — cf. docblock de classe. */
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private int $rank;

    public function __construct(string $code, string $label, int $rank)
    {
        $this->code = $code;
        $this->label = $label;
        $this->rank = $rank;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getRank(): int
    {
        return $this->rank;
    }
}
