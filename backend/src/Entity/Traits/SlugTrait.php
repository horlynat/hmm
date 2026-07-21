<?php

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Serializer\Attribute\Groups;

trait SlugTrait
{
    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['title'])]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $slug = null;

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    /**
     * Génère automatiquement un slug à partir d'une chaîne donnée
     */
    public function generateSlug(string $source): void
    {
        $slugger = new AsciiSlugger();
        $this->slug = strtolower($slugger->slug($source)->toString());
    }
}