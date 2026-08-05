<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Project;
use App\Entity\ProjectExpense;
use App\Entity\ProjectTask;
use App\Entity\User;
use App\Enum\NotificationPriorityEnum;
use App\Repository\NotificationPreferenceRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Notifie les parties prenantes d'un projet (client, collaborateurs, pilote) des
 * événements clés, par email asynchrone. Respecte les préférences globales de
 * notification par niveau d'importance (App\Entity\NotificationPreference).
 *
 * Les emails sont volontairement déclenchés depuis la couche applicative
 * (contrôleur / composant Live), après réussite de l'action métier — le workflow
 * de domaine (ExpenseWorkflow) reste pur et testable sans effet de bord.
 */
final class ProjectNotifier
{
    public function __construct(
        private readonly EmailManager $emailManager,
        private readonly NotificationPreferenceRepository $preferences,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function expenseSubmitted(ProjectExpense $expense): void
    {
        $project = $expense->getProject();
        $this->dispatch(
            [$project->getOwner()],
            NotificationPriorityEnum::MEDIUM,
            'Nouvelle dépense à approuver',
            [
                sprintf('Une dépense de %s (%s) a été soumise sur le projet « %s ».', $expense->getFormattedAmount(), $expense->getCategory()->getLabel(), $project->getTitle()),
                'Elle est en attente d\'approbation et n\'impacte pas encore le budget.',
            ],
            $project,
        );
    }

    public function expenseApproved(ProjectExpense $expense): void
    {
        $project = $expense->getProject();
        $this->dispatch(
            [$expense->getUser()],
            NotificationPriorityEnum::MEDIUM,
            'Dépense approuvée',
            [
                sprintf('Votre dépense de %s (%s) sur le projet « %s » a été approuvée.', $expense->getFormattedAmount(), $expense->getCategory()->getLabel(), $project->getTitle()),
                sprintf('Solde budgétaire disponible : %s.', $project->getFormattedRemainingBudget()),
            ],
            $project,
        );
    }

    public function expenseRejected(ProjectExpense $expense): void
    {
        $project = $expense->getProject();
        $this->dispatch(
            [$expense->getUser()],
            NotificationPriorityEnum::MEDIUM,
            'Dépense refusée',
            [
                sprintf('Votre dépense de %s (%s) sur le projet « %s » a été refusée.', $expense->getFormattedAmount(), $expense->getCategory()->getLabel(), $project->getTitle()),
            ],
            $project,
        );
    }

    public function collaboratorAdded(Project $project, User $collaborator): void
    {
        $this->dispatch(
            [$collaborator],
            NotificationPriorityEnum::MEDIUM,
            'Vous avez été ajouté à un projet',
            [
                sprintf('Vous faites désormais partie de l\'équipe du projet « %s ».', $project->getTitle()),
            ],
            $project,
        );
    }

    /**
     * Notifie le client que sa demande a été validée et transformée en projet
     * suivi — avec, le cas échéant, la facture initiale qui vient d'être émise.
     */
    public function projectAccepted(Project $project, ?Invoice $invoice): void
    {
        $client = $project->getClient();
        if (null === $client) {
            return;
        }

        $lines = [
            sprintf('Bonne nouvelle : votre demande a été validée et votre projet « %s » est désormais suivi dans votre espace.', $project->getTitle()),
        ];

        if (null !== $invoice) {
            $lines[] = sprintf(
                'Une facture (%s) de %s vous a été émise, à régler avant le %s. Vous pouvez la consulter à tout moment dans votre espace, rubrique « Mes factures ».',
                $invoice->getNumber(),
                $invoice->getFormattedAmount(),
                $invoice->getDueDate()?->format('d/m/Y') ?? 'la date indiquée dans votre espace',
            );
        }

        $this->dispatch([$client], NotificationPriorityEnum::HIGH, 'Votre projet a été validé', $lines, $project);
    }

    /** Le client a confirmé être d'accord avec le montant d'une facture — notifie le pilote du projet. */
    public function invoiceValidated(Invoice $invoice): void
    {
        $project = $invoice->getProject();
        $this->dispatch(
            [$project->getOwner()],
            NotificationPriorityEnum::MEDIUM,
            'Facture validée par le client',
            [
                sprintf('Le client a confirmé être d\'accord avec le montant de la facture %s (%s) sur le projet « %s ».', $invoice->getNumber(), $invoice->getFormattedAmount(), $project->getTitle()),
            ],
            $project,
        );
    }

    /** Le client demande une révision du budget d'une facture — notifie le pilote du projet, message du client en pièce jointe du fil de discussion. */
    public function invoiceRevisionRequested(Invoice $invoice, string $clientMessage): void
    {
        $project = $invoice->getProject();
        $this->dispatch(
            [$project->getOwner()],
            NotificationPriorityEnum::HIGH,
            'Révision de facture demandée',
            [
                sprintf('Le client demande une révision du montant de la facture %s (%s) sur le projet « %s ».', $invoice->getNumber(), $invoice->getFormattedAmount(), $project->getTitle()),
                sprintf('Message du client : « %s »', $clientMessage),
                'Le détail est aussi disponible dans le fil de discussion du projet.',
            ],
            $project,
        );
    }

    public function statusChanged(Project $project, string $oldLabel, string $newLabel): void
    {
        $recipients = $project->getCollaborators()->toArray();
        if (null !== $project->getClient()) {
            $recipients[] = $project->getClient();
        }

        $this->dispatch(
            $recipients,
            NotificationPriorityEnum::MEDIUM,
            'Changement de statut de projet',
            [
                sprintf('Le statut du projet « %s » est passé de « %s » à « %s ».', $project->getTitle(), $oldLabel, $newLabel),
            ],
            $project,
        );
    }

    public function taskAssigned(ProjectTask $task): void
    {
        $assignee = $task->getAssignee();
        if (null === $assignee) {
            return;
        }

        $project = $task->getProject();
        $this->dispatch(
            [$assignee],
            NotificationPriorityEnum::LOW,
            'Nouvelle tâche assignée',
            [
                sprintf('La tâche « %s » vous a été assignée sur le projet « %s ».', $task->getTitle(), $project->getTitle()),
                null !== $task->getDueDate() ? sprintf('Échéance : %s.', $task->getDueDate()->format('d/m/Y')) : 'Sans échéance.',
            ],
            $project,
        );
    }

    /**
     * Envoie l'email à chaque destinataire unique, si le niveau d'importance
     * autorise l'email (préférence globale). Génère un lien absolu vers le projet.
     *
     * @param array<int, User|null> $recipients
     * @param array<int, string>    $lines
     */
    private function dispatch(array $recipients, NotificationPriorityEnum $priority, string $heading, array $lines, Project $project): void
    {
        $preference = $this->preferences->findByPriority($priority);
        if (null !== $preference && !$preference->isEmailEnabled()) {
            return;
        }

        $seen = [];
        foreach ($recipients as $user) {
            if (!$user instanceof User) {
                continue;
            }
            $email = $user->getEmail();
            if ('' === $email || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;

            // Chaque destinataire reçoit un lien vers une vue qu'il peut réellement
            // ouvrir : back-office pour un admin, espace membre pour un client/collaborateur.
            $isAdmin = \in_array('ROLE_ADMIN', $user->getRoles(), true);
            $actionUrl = $this->urlGenerator->generate(
                $isAdmin ? 'admin_project_read' : 'member_project_read',
                ['id' => $project->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->emailManager->sendAsync(
                to: $email,
                subject: $heading,
                template: 'project_notification',
                context: [
                    'heading' => $heading,
                    'recipientName' => $user->getFullName() ?? $email,
                    'lines' => $lines,
                    'actionUrl' => $actionUrl,
                    'actionLabel' => 'Ouvrir le projet',
                    'projectTitle' => $project->getTitle(),
                ],
            );
        }
    }
}
