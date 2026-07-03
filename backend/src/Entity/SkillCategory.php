<?php

namespace App\Entity;

use App\Repository\SkillCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: SkillCategoryRepository::class)]
#[UniqueEntity(fields: ['name'], message: "Cette catégorie existe déjà.")]
class SkillCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le nom de la catégorie est obligatoire.")]
    #[Assert\Length(
        min: 3,
        minMessage: "Le nom de la catégorie doit contenir au moins {{ limit }} caractères.",
        max: 100,
        maxMessage: "Le nom de la catégorie ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $name = null;

    /**
     * @var Collection<int, Skill>
     */
    #[ORM\OneToMany(targetEntity: Skill::class, mappedBy: 'skillCategory')]
    private Collection $skill;

    public function __construct()
    {
        $this->skill = new ArrayCollection();
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

    /**
     * @return Collection<int, Skill>
     */
    public function getSkill(): Collection
    {
        return $this->skill;
    }

    public function addSkill(Skill $skill): static
    {
        if (!$this->skill->contains($skill)) {
            $this->skill->add($skill);
            $skill->setSkillCategory($this);
        }

        return $this;
    }

    public function removeSkill(Skill $skill): static
    {
        if ($this->skill->removeElement($skill)) {
            if ($skill->getSkillCategory() === $this) {
                $skill->setSkillCategory(null);
            }
        }

        return $this;
    }
}
