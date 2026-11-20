<?php

namespace App\Twig\Components\Project;

use App\Entity\Project;
use App\Security\Voter\ProjectVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

// ✅ Nom explicite en minuscules et chemin de template standardisé
#[AsLiveComponent(
    name: 'project:budget_summary',
    template: 'components/project/budget_summary.html.twig'
)]
class BudgetSummary
{
    use DefaultActionTrait;

    // Non writable (valeur par défaut de LiveProp) : ce composant est
    // atteignable en direct via POST /_components/project:budget_summary
    // (indépendamment de toute page), donc le contrôle PostMount ci-dessous
    // reste le seul rempart une fois l'identité "project" dans la requête —
    // la revalider à chaque (re)montage, pas seulement au premier rendu.
    #[LiveProp]
    public Project $project;

    public function __construct(private readonly Security $security)
    {
    }

    /**
     * Cf. docblock équivalent sur ExpenseList::checkAccess() : ce hook ne
     * couvre que le montage initial, Security doit être injecté au
     * constructeur (pas en paramètre — aucun autowiring ici).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PostMount]
    public function checkAccess(array $data): array
    {
        if (!$this->security->isGranted(ProjectVoter::VIEW, $this->project)) {
            throw new AccessDeniedException('Vous n\'avez pas accès à ce projet.');
        }

        return $data;
    }

    public function getPercentageSpent(): float
    {
        $budget = (float) $this->project->getBudget();
        $spent = (float) $this->project->getSpent();

        if ($budget <= 0) {
            return 0.0;
        }
        return ($spent / $budget) * 100;
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
