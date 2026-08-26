<?php

namespace App\Entity;

use App\Enum\InvoiceStatusEnum;
use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Facture émise au client pour un projet. Distincte de ProjectExpense (dépense
 * interne côté prestataire) : une Invoice représente ce que le client doit
 * payer, pas comment le budget alloué est consommé en interne.
 */
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoice')]
#[ORM\Index(columns: ['project_id'], name: 'idx_invoice_project')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['number'], message: 'Ce numéro de facture existe déjà.')]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['api_admin'])]
    private Project $project;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Le numéro de facture est obligatoire.')]
    #[Assert\Length(max: 50)]
    #[Groups(['api_admin'])]
    private string $number = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
    #[Assert\Length(max: 255)]
    #[Groups(['api_admin'])]
    private string $label = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire.')]
    #[Assert\Positive(message: 'Le montant doit être strictement positif.')]
    #[Groups(['api_admin'])]
    private string $amount = '0.00';

    #[ORM\Column(length: 10)]
    #[Groups(['api_admin'])]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'string', enumType: InvoiceStatusEnum::class)]
    #[Groups(['api_admin'])]
    private InvoiceStatusEnum $status = InvoiceStatusEnum::PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['api_admin'])]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $paidAt = null;

    /** Date à laquelle le client a confirmé être d'accord avec le montant — indépendant du paiement lui-même. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $validatedAt = null;

    /** Dernière relance automatique envoyée pour retard de paiement — voir App\Command\RemindOverdueInvoicesCommand (throttle : une relance au plus tous les 7 jours). */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    /**
     * Séparation des tâches (SoD) : traçabilité de qui a créé la facture et
     * qui l'a marquée payée — nullable (comptes supprimés, factures
     * antérieures à ce champ). Ne bloque jamais l'action même si c'est le
     * même compte (cf. AdminInvoiceController::markPaid()) : une petite
     * structure peut n'avoir qu'un seul opérateur habilité, un blocage
     * strict la rendrait inutilisable. Seulement tracé et visible (badge +
     * audit), pour permettre une revue humaine a posteriori.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['api_admin'])]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'marked_paid_by_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['api_admin'])]
    private ?User $markedPaidBy = null;

    public function __construct()
    {
        $this->issuedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setProject(Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getStatus(): InvoiceStatusEnum
    {
        return $this->status;
    }

    public function setStatus(InvoiceStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(\DateTimeImmutable $issuedAt): static
    {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function markPaid(): static
    {
        $this->status = InvoiceStatusEnum::PAID;
        $this->paidAt = new \DateTimeImmutable();

        return $this;
    }

    public function markUnpaid(): static
    {
        $this->status = InvoiceStatusEnum::PENDING;
        $this->paidAt = null;

        return $this;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function isValidated(): bool
    {
        return null !== $this->validatedAt;
    }

    /** Le client confirme être d'accord avec le montant — ne change pas le statut de paiement. */
    public function markValidated(): static
    {
        $this->validatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** Le client demande une révision du budget — remet à zéro une éventuelle validation précédente. */
    public function markRevisionRequested(): static
    {
        $this->status = InvoiceStatusEnum::REVISION_REQUESTED;
        $this->validatedAt = null;

        return $this;
    }

    public function isPaid(): bool
    {
        return InvoiceStatusEnum::PAID === $this->status;
    }

    /** Facture en attente et échéance dépassée. */
    public function isOverdue(): bool
    {
        return InvoiceStatusEnum::PENDING === $this->status
            && null !== $this->dueDate
            && $this->dueDate < new \DateTimeImmutable('today');
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function markReminderSent(): static
    {
        $this->reminderSentAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getMarkedPaidBy(): ?User
    {
        return $this->markedPaidBy;
    }

    public function setMarkedPaidBy(?User $markedPaidBy): static
    {
        $this->markedPaidBy = $markedPaidBy;

        return $this;
    }

    /**
     * Séparation des tâches non respectée : le même compte a créé la facture
     * ET l'a marquée payée. Faux si l'un des deux acteurs est inconnu (champ
     * introduit après coup, ou compte depuis supprimé) — on ne signale que ce
     * qu'on peut affirmer avec certitude.
     */
    public function wasCreatedAndMarkedPaidBySamePerson(): bool
    {
        // Comparaison d'identité d'objet, pas d'ID : deux User fraîchement
        // instanciés (non persistés) ont tous les deux un ID null, ce qui
        // donnerait un faux positif "même personne" en comparant les ID.
        // Une entité chargée deux fois dans la même requête reste le même
        // objet PHP grâce à l'identity map de Doctrine — cette comparaison
        // est donc fiable pour les deux User réellement persistés.
        return null !== $this->createdBy
            && null !== $this->markedPaidBy
            && $this->createdBy === $this->markedPaidBy;
    }

    public function getFormattedAmount(): string
    {
        $symbol = match ($this->currency) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            default => $this->currency,
        };

        return number_format((float) $this->amount, 2, ',', ' ').' '.$symbol;
    }
}
