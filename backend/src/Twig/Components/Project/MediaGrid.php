<?php

namespace App\Twig\Components\Project;

use App\Entity\Project;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

// ✅ Harmonisation du nom et suppression de l'underscore du template
#[AsTwigComponent(
    name: 'project:media_grid', 
    template: 'components/project/media_grid.html.twig'
)]
class MediaGrid
{
    public Project $project;
}
