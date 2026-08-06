<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Invoice;
use App\Entity\Project;
use App\Entity\ProjectExpense;
use App\Entity\ProjectTask;
use App\Entity\User;
use App\Enum\NotificationPriorityEnum;
use App\Enum\TaskStatusEnum;
use App\Repository\NotificationPreferenceRepository;

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
        private readonly AccountLinkResolver $accountLinkResolver,
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
     * Ne notifie que sur les transitions qui demandent réellement une action
     * ou une information côté destinataire (terminée / bloquée) — TODO/EN
     * COURS restent silencieuses pour éviter le bruit sur chaque glisser-
     * déposer du tableau des tâches.
     */
    public function taskStatusChanged(ProjectTask $task): void
    {
        $status = $task->getStatus();
        if (TaskStatusEnum::DONE !== $status && TaskStatusEnum::BLOCKED !== $status) {
            return;
        }

        $assignee = $task->getAssignee();
        if (null === $assignee) {
            return;
        }

        $project = $task->getProject();
        $heading = TaskStatusEnum::DONE === $status ? 'Tâche terminée' : 'Tâche bloquée';
        $this->dispatch(
            [$assignee],
            TaskStatusEnum::BLOCKED === $status ? NotificationPriorityEnum::MEDIUM : NotificationPriorityEnum::LOW,
            $heading,
            [
                sprintf('La tâche « %s » sur le projet « %s » est passée au statut « %s ».', $task->getTitle(), $project->getTitle(), $status->getLabel()),
            ],
            $project,
        );
    }

    /**
     * Un collaborateur a mis à jour lui-même le statut d'une tâche qui lui
     * est assignée (self-service, cf. MeController::updateTaskStatus) —
     * distinct de taskStatusChanged() ci-dessus qui notifie l'assigné : ici
     * l'assigné EST l'auteur du changement, donc on notifie le responsable
     * du projet à la place.
     */
    public function taskStatusChangedByAssignee(ProjectTask $task): void
    {
        $project = $task->getProject();
        $owner = $project->getOwner();
        if ($owner === $task->getAssignee()) {
            return;
        }

        $this->dispatch(
            [$owner],
            NotificationPriorityEnum::LOW,
            'Mise à jour de tâche',
            [
                sprintf(
                    '%s a mis à jour la tâche « %s » sur le projet « %s » → %s.',
                    $task->getAssignee()?->getFullName() ?? $task->getAssignee()?->getEmail(),
                    $task->getTitle(),
                    $project->getTitle(),
                    $task->getStatus()->getLabel(),
                ),
            ],
            $project,
        );
    }

    /** Nouvelle facture transmise au client (hors facture initiale de conversion de devis, déjà couverte par projectAccepted()). */
    public function invoiceCreated(Invoice $invoice): void
    {
        $project = $invoice->getProject();
        $client = $project->getClient();
        if (null === $client) {
            return;
        }

        $this->dispatch(
            [$client],
            NotificationPriorityEnum::HIGH,
            'Nouvelle facture disponible',
            [
                sprintf(
                    'Une nouvelle facture (%s) de %s vous a été transmise sur le projet « %s », à régler avant le %s.',
                    $invoice->getNumber(),
                    $invoice->getFormattedAmount(),
                    $project->getTitle(),
                    $invoice->getDueDate()?->format('d/m/Y') ?? 'la date indiquée dans votre espace',
                ),
            ],
            $project,
        );
    }

    /** Confirmation de paiement envoyée au client une fois la facture marquée payée par l'admin. */
    public function invoicePaid(Invoice $invoice): void
    {
        $project = $invoice->getProject();
        $client = $project->getClient();
        if (null === $client) {
            return;
        }

        $this->dispatch(
            [$client],
            NotificationPriorityEnum::MEDIUM,
            'Paiement confirmé',
            [
                sprintf('Nous confirmons la bonne réception du paiement de la facture %s (%s) sur le projet « %s ». Merci !', $invoice->getNumber(), $invoice->getFormattedAmount(), $project->getTitle()),
            ],
            $project,
        );
    }

    /** Relance automatique pour une facture en retard — voir App\Command\RemindOverdueInvoicesCommand. */
    public function invoiceOverdueReminder(Invoice $invoice): void
    {
        $project = $invoice->getProject();
        $client = $project->getClient();
        if (null === $client) {
            return;
        }

        $this->dispatch(
            [$client],
            NotificationPriorityEnum::HIGH,
            'Facture en attente de règlement',
            [
                sprintf(
                    'La facture %s (%s) sur le projet « %s » était due le %s et n\'a pas encore été réglée.',
                    $invoice->getNumber(),
                    $invoice->getFormattedAmount(),
                    $project->getTitle(),
                    $invoice->getDueDate()?->format('d/m/Y') ?? '',
                ),
                'Si le paiement a déjà été effectué, merci d\'ignorer ce message — il sera mis à jour de votre côté sous peu.',
            ],
            $project,
        );
    }

    public function collaboratorRemoved(Project $project, User $collaborator): void
    {
        $this->dispatch(
            [$collaborator],
            NotificationPriorityEnum::MEDIUM,
            'Retrait d\'un projet',
            [
                sprintf('Vous ne faites désormais plus partie de l\'équipe du projet « %s ».', $project->getTitle()),
            ],
            $project,
        );
    }

    /**
     * Notifie le reste de l'équipe (client + collaborateurs + pilote) qu'un
     * nouveau message a été posté sur le fil de discussion du projet —
     * l'auteur lui-même est exclu des destinataires.
     */
    public function commentPosted(Comment $comment): void
    {
        $project = $comment->getProject();
        $author = $comment->getAuthor();

        $recipients = $project->getCollaborators()->toArray();
        $recipients[] = $project->getOwner();
        if (null !== $project->getClient()) {
            $recipients[] = $project->getClient();
        }
        $recipients = array_filter($recipients, static fn (User $user): bool => $user !== $author);

        $excerpt = mb_strlen($comment->getContent()) > 200
            ? mb_substr($comment->getContent(), 0, 200).'…'
            : $comment->getContent();

        $this->dispatch(
            $recipients,
            NotificationPriorityEnum::MEDIUM,
            'Nouveau message sur un projet',
            [
                sprintf('%s a posté un nouveau message sur le projet « %s » :', $author->getFullName() ?? $author->getEmail(), $project->getTitle()),
                sprintf('« %s »', $excerpt),
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
            // ouvrir : back-office pour un admin, espace /compte Next.js pour un
            // client/collaborateur (member_project_read reste une ancienne route
            // Symfony, plus utilisée depuis la bascule de l'espace membre en frontend).
            $actionUrl = $this->accountLinkResolver->resolve(
                $user,
                'admin_project_read',
                ['id' => $project->getId()],
                '/compte/projets/'.$project->getId(),
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
