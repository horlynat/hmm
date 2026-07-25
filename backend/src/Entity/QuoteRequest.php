<?php

namespace App\Entity;

use App\Enum\QuoteStatusEnum;
use App\Repository\QuoteRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: QuoteRequestRepository::class)]
class QuoteRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])] // exposé uniquement côté admin
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups(['api_public', 'api_admin'])]
    private string $name = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email]
    #[Assert\Length(max: 100)]
    #[Groups(['api_public', 'api_admin'])]
    private string $email = '';

    /** Requis uniquement si `channel` n'est pas l'email — cf. validatePhoneForChannel(). */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Assert\Regex(pattern: "/^\+?[0-9\s\-]{7,20}$/", message: "Le numéro de téléphone n'est pas valide.")]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $phone = null;

    /** Métier concerné : Développement web, Application mobile, Intégration IA, Cybersécurité, Assurance, Design, Autre. */
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "La catégorie de la demande est obligatoire.")]
    #[Groups(['api_public', 'api_admin'])]
    private string $category = '';

    /** Réponse à la question de qualification propre à la catégorie choisie (ex : "Site e-commerce"). */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $categoryDetail = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $source = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $budget = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $currency = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $timeline = null;

    /** Canal de contact préféré : Email, WhatsApp ou Appel — conditionne l'obligation du téléphone. */
    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: "Le canal de contact préféré est obligatoire.")]
    #[Groups(['api_public', 'api_admin'])]
    private string $channel = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $attachmentName = null;

    /** Précisions apportées lors de l'échange avec l'assistant IA avant envoi, sous forme [{question, answer}]. */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $clarifications = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le message est obligatoire.")]
    #[Assert\Length(min: 10)]
    #[Groups(['api_public', 'api_admin'])]
    private string $message = '';

    #[ORM\Column(length: 20, enumType: QuoteStatusEnum::class)]
    #[Groups(['api_admin'])]
    private QuoteStatusEnum $status = QuoteStatusEnum::PENDING;

    #[ORM\ManyToOne(inversedBy: 'quoteRequest')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['api_admin'])]
    private ?User $user = null;

    /**
     * Le téléphone n'est pas obligatoire dans l'absolu (le champ reste nullable pour
     * les demandes par email), mais devient requis dès que le client choisit WhatsApp
     * ou un appel comme canal préféré — impossible de le rappeler sinon.
     */
    #[Assert\Callback]
    public function validatePhoneForChannel(ExecutionContextInterface $context): void
    {
        $channel = mb_strtolower($this->channel);
        $isEmailChannel = '' === $channel || str_contains($channel, 'email') || str_contains($channel, 'mail');

        if (!$isEmailChannel && '' === trim((string) $this->phone)) {
            $context->buildViolation('Le téléphone est obligatoire pour le canal de contact choisi (WhatsApp ou appel).')
                ->atPath('phone')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getCategoryDetail(): ?string
    {
        return $this->categoryDetail;
    }

    public function setCategoryDetail(?string $categoryDetail): static
    {
        $this->categoryDetail = $categoryDetail;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getBudget(): ?string
    {
        return $this->budget;
    }

    public function setBudget(?string $budget): static
    {
        $this->budget = $budget;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getTimeline(): ?string
    {
        return $this->timeline;
    }

    public function setTimeline(?string $timeline): static
    {
        $this->timeline = $timeline;

        return $this;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getAttachmentName(): ?string
    {
        return $this->attachmentName;
    }

    public function setAttachmentName(?string $attachmentName): static
    {
        $this->attachmentName = $attachmentName;

        return $this;
    }

    public function getClarifications(): ?array
    {
        return $this->clarifications;
    }

    public function setClarifications(?array $clarifications): static
    {
        $this->clarifications = $clarifications;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getStatus(): QuoteStatusEnum
    {
        return $this->status;
    }

    public function setStatus(QuoteStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
