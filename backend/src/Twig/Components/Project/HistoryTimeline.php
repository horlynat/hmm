<?php

namespace App\Twig\Components\Project;

use App\Entity\Project;
use App\Entity\ProjectHistory;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

// ✅ Harmonisation du nom et suppression de l'underscore du template
#[AsTwigComponent(
    name: 'project:history_timeline',
    template: 'components/project/history_timeline.html.twig'
)]
class HistoryTimeline
{
    public Project $project;

    /** @return ProjectHistory[] */
    public function getSortedHistories(): array
    {
        $histories = $this->project->getHistories()->toArray();
        usort($histories, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        return $histories;
    }
}
