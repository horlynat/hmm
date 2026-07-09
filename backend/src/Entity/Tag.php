<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le nom du tag est obligatoire.")]
    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $name = null;

    #[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'tags')]
    #[Groups(['api_admin'])] // exposé seulement côté admin
    private Collection $articles;

    public function __construct()
    {
        $this->articles = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $articles): static
    {
        if (!$this->articles->contains($articles)) {
            $this->articles->add($articles);
        }

        return $this;
    }

    public function removeArticle(Article $articles): static
    {
        $this->articles->removeElement($articles);

        return $this;
    }
}
