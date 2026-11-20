<?php

namespace App\Twig\Components\Project;

use App\Entity\Project;
use App\Entity\ProjectExpense;
use App\Entity\User;
use App\Security\Voter\ProjectVoter;
use App\Service\ExpenseWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Liste réactive des dépenses d'un projet avec workflow d'approbation.
 * Toute la logique budgétaire est déléguée à App\Service\ExpenseWorkflow.
 *
 * P1 #6 (IDOR) : $project N'EST PLUS writable. Avant, un visiteur autorisé sur
 * UN projet pouvait renvoyer ce composant avec `"updated":{"project":<autre
 * id>}` — la ré-hydratation d'une LiveProp writable n'est jamais revérifiée
 * par un voter, contrairement au rendu initial (contrôlé par le contrôleur
 * parent). En plus de retirer writable, un hook #[PostMount] revérifie
 * PROJECT_VIEW à CHAQUE (re)montage du composant — y compris le rendu
 * initial — en défense en profondeur (ex: accès révoqué entre deux
 * affichages d'un même onglet resté ouvert).
 */
#[AsLiveComponent(
    name: 'project:expense_list',
    template: 'components/project/expense_list.html.twig'
)]
class ExpenseList extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public Project $project;

    public function __construct(private readonly Security $security)
    {
    }

    /**
     * Revalidation d'accès au MONTAGE initial du composant (le seul moment où
     * #[PostMount] s'exécute — les ré-affichages AJAX ultérieurs passent par
     * LiveComponentHydrator::hydrate(), qui n'appelle jamais ce hook ; c'est
     * pourquoi $project n'est plus writable, seul vrai rempart contre un
     * échange d'identité côté AJAX). Security est injecté au constructeur
     * (pas en paramètre de méthode) : ce hook est invoqué par réflexion
     * directe avec le seul tableau de données de montage, sans passer par le
     * résolveur d'arguments de Symfony — aucun service n'y est autowiré.
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

    #[LiveAction]
    public function approveExpense(#[LiveArg] int $expenseId, EntityManagerInterface $em, ExpenseWorkflow $workflow, \App\Service\ProjectNotifier $notifier): void
    {
        $this->denyAccessUnlessGranted(ProjectVoter::APPROVE_EXPENSE, $this->project);
        $expense = $this->findExpense($expenseId, $em);
        if (!$expense) {
            return;
        }

        try {
            $workflow->approve($expense, $this->currentUser());
            $notifier->expenseApproved($expense);
            $this->addFlash('success', 'Dépense approuvée.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }
    }

    #[LiveAction]
    public function rejectExpense(#[LiveArg] int $expenseId, EntityManagerInterface $em, ExpenseWorkflow $workflow, \App\Service\ProjectNotifier $notifier): void
    {
        $this->denyAccessUnlessGranted(ProjectVoter::APPROVE_EXPENSE, $this->project);
        $expense = $this->findExpense($expenseId, $em);
        if (!$expense) {
            return;
        }

        $wasApproved = $expense->isApproved();
        $workflow->reject($expense, $this->currentUser());
        // Ne notifie « refus » que pour une vraie décision de refus (pas une révocation d'approbation).
        if (!$wasApproved) {
            $notifier->expenseRejected($expense);
        }
        $this->addFlash('success', 'Dépense refusée.');
    }

    #[LiveAction]
    public function removeExpense(#[LiveArg] int $expenseId, EntityManagerInterface $em, ExpenseWorkflow $workflow): void
    {
        $this->denyAccessUnlessGranted(ProjectVoter::APPROVE_EXPENSE, $this->project);
        $expense = $this->findExpense($expenseId, $em);
        if (!$expense) {
            return;
        }

        $workflow->remove($expense, $this->currentUser());
        $this->addFlash('success', 'Dépense supprimée.');
    }

    private function findExpense(int $expenseId, EntityManagerInterface $em): ?ProjectExpense
    {
        $expense = $em->getRepository(ProjectExpense::class)->find($expenseId);

        return ($expense && $expense->getProject() === $this->project) ? $expense : null;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    /** Projet verrouillé (terminé/suspendu) : plus de mouvement de dépense. */
    public function isProjectLocked(): bool
    {
        return \in_array(
            $this->project->getStatus(),
            [\App\Enum\ProjectStatusEnum::COMPLETED, \App\Enum\ProjectStatusEnum::SUSPENDED],
            true,
        );
    }

    /** L'utilisateur courant peut-il approuver/refuser/supprimer des dépenses ? */
    public function canApprove(): bool
    {
        return $this->isGranted(ProjectVoter::APPROVE_EXPENSE, $this->project);
    }
}
