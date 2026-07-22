<?php

namespace App\Twig\Components\Project;

use App\Entity\Project;
use App\Entity\ProjectExpense;
use App\Enum\ProjectStatusEnum;
use App\Security\Voter\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

// ✅ Harmonisation du nom et suppression de l'underscore du template
#[AsLiveComponent(
    name: 'project:expense_list', 
    template: 'components/project/expense_list.html.twig'
)]
class ExpenseList extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public Project $project;

    #[LiveAction]
    public function removeExpense(#[LiveArg] int $expenseId, EntityManagerInterface $entityManager): void
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $this->project);

        if ($this->isProjectLocked()) {
            throw new \LogicException("Le projet est verrouillé.");
        }

        $expense = $entityManager->getRepository(ProjectExpense::class)->find($expenseId);

        if ($expense && $expense->getProject() === $this->project) {
            $this->project->removeProjectExpense($expense);
            $entityManager->remove($expense);
            $entityManager->flush();

            $this->addFlash('success', 'Dépense retirée.');
        }
    }

    public function isProjectLocked(): bool
    {
        return in_array($this->project->getStatus(), [ProjectStatusEnum::COMPLETED, ProjectStatusEnum::SUSPENDED], true);
    }
}
