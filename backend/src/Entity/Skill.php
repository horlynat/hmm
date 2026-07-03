<?php

namespace App\Entity;

use App\Repository\SkillRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: SkillRepository::class)]
#[UniqueEntity(fields: ['name'], message: "Cette compétence existe déjà.")]
class Skill
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom de la compétence est obligatoire.")]
    #[Assert\Length(
        min: 2,
        minMessage: "Le nom de la compétence doit contenir au moins {{ limit }} caractères.",
        max: 255,
        maxMessage: "Le nom de la compétence ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotNull(message: "Le niveau est obligatoire.")]
    #[Assert\Range(
        min: 1,
        max: 10,
        notInRangeMessage: "Le niveau doit être compris entre {{ min }} et {{ max }}."
    )]
    private ?int $level = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'skill')]
    private Collection $projects;

    #[ORM\ManyToOne(inversedBy: 'skill')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "La catégorie de compétence est obligatoire.")]
    private ?SkillCategory $skillCategory = null;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getLevel(): ?int
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

    public function getSkillCategory(): ?SkillCategory
    {
        return $this->skillCategory;
    }

    public function setSkillCategory(?SkillCategory $skillCategory): static
    {
        $this->skillCategory = $skillCategory;
        return $this;
    }
}
