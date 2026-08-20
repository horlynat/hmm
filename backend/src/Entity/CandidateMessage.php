<?php

namespace App\Entity;

use App\Repository\CandidateMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Message échangé entre un administrateur et un candidat/collaborateur
 * (User) — fil unique par compte, pas de notion de "sujet" distincte comme
 * SupportTicket : c'est une conversation continue avec ce candidat, pas une
 * demande ponctuelle. Relation unidirectionnelle vers User (même choix que
 * SupportTicket::$user) : la collection se lit par requête (voir
 * CandidateMessageRepository), pas par un OneToMany sur User.
 *
 * $read suit UN SEUL sens à la fois selon $fromAdmin : "lu par le candidat"
 * si fromAdmin=true, "lu par l'admin" si fromAdmin=false — pas besoin de
 * deux booléens, chaque message n'a qu'un seul destinataire possible.
 */
#[ORM\Entity(repositoryClass: CandidateMessageRepository::class)]
class CandidateMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $candidate;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le message est obligatoire.')]
    #[Assert\Length(min: 10, max: 5000)]
    private string $body = '';

    #[ORM\Column]
    private bool $fromAdmin = false;

    // Colonne nommée explicitement 'is_read' : 'read' est un mot réservé MySQL
    // — Doctrine le quote correctement en DDL (CREATE TABLE), mais PAS dans le
    // SQL généré pour une requête DQL UPDATE (cf. CandidateMessageRepository::
    // markReadFor()), qui échouait donc avec une erreur de syntaxe SQL.
    #[ORM\Column(name: 'is_read')]
    private bool $read = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCandidate(): User
    {
        return $this->candidate;
    }

    public function setCandidate(User $candidate): static
    {
        $this->candidate = $candidate;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function isFromAdmin(): bool
    {
        return $this->fromAdmin;
    }

    public function setFromAdmin(bool $fromAdmin): static
    {
        $this->fromAdmin = $fromAdmin;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->read;
    }

    public function setRead(bool $read): static
    {
        $this->read = $read;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
