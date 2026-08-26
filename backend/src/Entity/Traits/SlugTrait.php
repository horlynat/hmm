<?php

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\String\Slugger\AsciiSlugger;

trait SlugTrait
{
    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['api_public', 'api_admin'])]
    // protected (pas private) — cf. commentaire identique dans App\Entity\User :
    // Project utilise #[UniqueEntity(fields: ['slug'])], dont le validateur
    // reflète l'objet réellement validé (ProjectApiResource, une sous-classe) ;
    // une propriété private du parent (même via un trait — elle appartient
    // toujours à la classe qui l'utilise) y est invisible.
    protected string $slug = '';

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Génère automatiquement un slug à partir d'une chaîne donnée.
     */
    public function generateSlug(string $source): void
    {
        $slugger = new AsciiSlugger();
        $this->slug = strtolower($slugger->slug($source)->toString());
    }
}
