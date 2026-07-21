<?php

namespace App\Twig\Components\Project;

use App\Enum\ProjectStatusEnum;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

// ✅ Harmonisation du nom et suppression de l'underscore du template
#[AsTwigComponent(
    name: 'project:badge_status', 
    template: 'components/project/badge_status.html.twig'
)]
class BadgeStatus
{
    public ProjectStatusEnum $status;

    public function getIcon(): string
    {
        return match($this->status) {
            ProjectStatusEnum::COMPLETED => '✓',
            ProjectStatusEnum::SUSPENDED => '⏸',
            ProjectStatusEnum::IN_PROGRESS => '⚡',
            default => "ℹ",
        };
    }
}
