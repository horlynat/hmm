<?php

namespace App\Twig\Components\Project;

use App\Entity\Project;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

// ✅ Nom explicite en minuscules et chemin de template standardisé
#[AsLiveComponent(
    name: 'project:budget_summary',
    template: 'components/project/budget_summary.html.twig'
)]
class BudgetSummary
{
    use DefaultActionTrait;

    #[LiveProp]
    public Project $project;

    public function getPercentageSpent(): float
    {
        if ($this->project->getBudget() <= 0) {
            return 0.0;
        }
        return ($this->project->getSpent() / $this->project->getBudget()) * 100;
    }

    public function isBudgetCritical(): bool
    {
        $budget = (float) $this->project->getBudget();
        $spent = (float) $this->project->getSpent();
        
        if ($budget <= 0) {
            return false;
        }
        
        return ($spent > $budget) || (($budget - $spent) / $budget < 0.1);
    }
}