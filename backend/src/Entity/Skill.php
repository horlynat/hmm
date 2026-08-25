<?php

namespace App\Entity;

use App\Repository\SkillRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SkillRepository::class)]
// entityClass explicite : sans lui, UniqueEntityValidator résout le repository
// via get_class($value), qui vaut App\ApiResource\SkillApiResource lors d'un
// POST/PUT via l'API — une classe non mappée Doctrine (seul ce parent l'est).
// Ça fait échouer TOUTE requête avec un 500 ("Unable to find the object
// manager..."), pas seulement les doublons — constaté en pratique, même
// correctif que App\Entity\User (cf. son #[UniqueEntity]).
#[UniqueEntity(fields: ['name'], message: 'Cette compétence existe déjà.', entityClass: Skill::class)]
class Skill
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de la compétence est obligatoire.')]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    // protected (pas private) — cf. commentaire identique dans App\Entity\User :
    // UniqueEntityValidator reflète l'objet réellement validé (SkillApiResource,
    // une sous-classe), une propriété private du parent y est invisible.
    protected string $name = '';

    /** Nom en anglais — optionnel, retombe sur `name` (FR) côté frontend si vide. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $nameEn = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotNull(message: 'Le niveau est obligatoire.')]
    #[Assert\Range(min: 1, max: 10)]
    #[Groups(['api_public', 'api_admin'])]
    private int $level = 1;

    /** @var Collection<int, Project> */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'skills')]
    #[Groups(['api_admin'])] // exposé seulement côté admin
    private Collection $projects;

    #[ORM\ManyToOne(inversedBy: 'skill')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La catégorie de compétence est obligatoire.')]
    // Public depuis peu : permet au frontend de grouper les compétences par
    // catégorie (cf. src/lib/api/skills.ts côté frontend). Pas de risque de
    // cycle : SkillCategory::$skill (la collection inverse) reste api_admin.
    #[Groups(['api_public', 'api_admin'])]
    private SkillCategory $skillCategory;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function setNameEn(?string $nameEn): static
    {
        $this->nameEn = $nameEn;

        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->addSkill($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            $project->removeSkill($this);
        }

        return $this;
    }

    public function getSkillCategory(): SkillCategory
    {
        return $this->skillCategory;
    }

    public function setSkillCategory(SkillCategory $skillCategory): static
    {
        $this->skillCategory = $skillCategory;

        return $this;
    }
}
