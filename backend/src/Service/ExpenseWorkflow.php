<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\ProjectExpense;
use App\Entity\User;
use App\Enum\ExpenseStatusEnum;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Workflow métier des dépenses de projet — centralise les règles pour qu'aucune
 * dépense n'échappe au contrôle budgétaire (« on ne dépense pas sans argent »).
 *
 * Invariants garantis :
 * - Une dépense ne peut être créée que si le projet a un budget (> 0).
 * - Seules les dépenses APPROUVÉES comptent dans `Project::spent`.
 * - À l'approbation, blocage strict si le montant dépasse le solde disponible.
 * - `spent` est toujours recalculé depuis les dépenses approuvées (source de vérité).
 *
 * Les violations de règle lèvent une \DomainException (message destiné à l'admin),
 * que le contrôleur transforme en flash + redirection.
 */
final class ExpenseWorkflow
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Soumet une nouvelle dépense (statut PENDING) — n'impacte pas encore le budget.
     *
     * @throws \DomainException si le projet n'a pas de budget défini
     */
    public function submit(Project $project, ProjectExpense $expense, User $author): void
    {
        if (!$project->hasBudget()) {
            throw new \DomainException('Définissez d\'abord un budget (> 0) pour ce projet avant d\'ajouter une dépense.');
        }

        $expense->setStatus(ExpenseStatusEnum::PENDING)->setUser($author);
        if (null === $expense->getSpentAt()) {
            $expense->setSpentAt(new \DateTimeImmutable('today'));
        }

        $project->attachExpense($expense, $author);
        $this->em->persist($expense);
        $this->em->flush();
    }

    /**
     * Approuve une dépense — blocage strict : refuse si elle dépasse le disponible.
     *
     * @throws \DomainException si le solde disponible est insuffisant
     */
    public function approve(ProjectExpense $expense, User $approver): void
    {
        if ($expense->isApproved()) {
            return;
        }

        $project = $expense->getProject();

        // Blocage strict : le disponible (budget − dépensé approuvé) doit couvrir la dépense.
        if (bccomp($expense->getAmount(), $project->getAvailableBudget(), 2) > 0) {
            throw new \DomainException(sprintf(
                'Budget insuffisant : dépense de %s alors que le solde disponible n\'est que de %s.',
                $expense->getFormattedAmount(),
                $project->getFormattedRemainingBudget(),
            ));
        }

        $expense->setStatus(ExpenseStatusEnum::APPROVED)
            ->setApprovedBy($approver)
            ->setApprovedAt(new \DateTimeImmutable());

        $project->recalculateSpent();

        // Séparation des tâches : ne bloque jamais (cf. Invoice::wasCreatedAndMarkedPaidBySamePerson()),
        // seulement tracé sous une action distincte pour rester repérable dans l'historique/audit.
        $project->addToHistory(
            $expense->wasSubmittedAndApprovedBySamePerson() ? 'expense_approved_by_submitter' : 'expense_approved',
            $approver,
            sprintf(
                'Dépense approuvée: %s (%s)',
                $expense->getFormattedAmount(),
                $expense->getCategory()->getLabel(),
            ),
        );

        $this->em->flush();
    }

    /**
     * Refuse une dépense (ou révoque une dépense approuvée : le budget est alors libéré).
     */
    public function reject(ProjectExpense $expense, User $approver, ?string $reason = null): void
    {
        $wasApproved = $expense->isApproved();

        $expense->setStatus(ExpenseStatusEnum::REJECTED)
            ->setApprovedBy($approver)
            ->setApprovedAt(new \DateTimeImmutable());

        $project = $expense->getProject();
        if ($wasApproved) {
            $project->recalculateSpent();
        }

        $project->addToHistory('expense_rejected', $approver, trim(sprintf(
            'Dépense refusée: %s%s',
            $expense->getFormattedAmount(),
            $reason ? ' — ' . $reason : '',
        )));

        $this->em->flush();
    }

    /**
     * Applique une modification d'une dépense déjà persistée (le formulaire a muté l'entité).
     * Recalcule `spent` si la dépense est approuvée et garantit l'invariant budgétaire.
     *
     * @throws \DomainException si, après modification, une dépense approuvée fait dépasser le budget
     */
    public function update(ProjectExpense $expense, User $actor): void
    {
        $project = $expense->getProject();

        // Une dépense approuvée compte dans `spent` : on recalcule et on vérifie l'invariant.
        if ($expense->isApproved()) {
            $project->recalculateSpent();

            if ($project->isOverBudget()) {
                // On ne flushe pas : la mutation en mémoire ne sera pas persistée.
                throw new \DomainException(sprintf(
                    'Budget dépassé : le total des dépenses approuvées (%s) excéderait le budget (%s).',
                    $project->getFormattedSpent(),
                    $project->getFormattedBudget(),
                ));
            }
        }

        $project->addToHistory('expense_updated', $actor, sprintf(
            'Dépense modifiée: %s (%s)',
            $expense->getFormattedAmount(),
            $expense->getCategory()->getLabel(),
        ));

        $this->em->flush();
    }

    /**
     * Supprime définitivement une dépense (libère le budget si elle était approuvée).
     */
    public function remove(ProjectExpense $expense, User $actor): void
    {
        $project = $expense->getProject();
        $project->removeProjectExpense($expense); // recalcule spent + journalise
        $this->em->remove($expense);
        $this->em->flush();
    }
}
