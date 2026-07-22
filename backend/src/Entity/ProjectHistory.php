<?php

namespace App\Entity;

use App\Entity\User;
use App\Entity\Project;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'project_history')]
#[ORM\Index(columns: ['project_id'], name: 'idx_project_history_project')]
#[ORM\Index(columns: ['created_at'], name: 'idx_project_history_created_at')]
class ProjectHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'histories')]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['api_admin'])]
    private Project $project;

    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['api_admin'])]
    private string $action; // Ex: 'created', 'updated', 'status_changed', 'expense_added'

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['api_admin'])]
    private ?string $details = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    #[Groups(['api_admin'])]
    private User $user;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['api_admin'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ===== Getters et Setters =====
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setProject(Project $project): self
    {
        $this->project = $project;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): self
    {
        $this->details = $details;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // ===== Méthodes Utilitaires =====
    public function getActionLabel(): string
    {
        return match($this->action) {
            'created' => 'Création du projet',
            'updated' => 'Mise à jour du projet',
            'status_changed' => 'Changement de statut',
            'expense_added' => 'Ajout d\'une dépense',
            'expense_removed' => 'Suppression d\'une dépense',
            'collaborator_added' => 'Ajout d\'un collaborateur',
            'collaborator_removed' => 'Retrait d\'un collaborateur',
            'client_assigned' => 'Client assigné',
            'media_removed' => 'Suppression d\'un média',
            'project_deleted' => 'Suppression du projet',
            default => ucfirst($this->action),
        };
    }

    public function getActionIcon(): string
    {
        return match($this->action) {
            'created' => '🆕',
            'updated' => '✏️',
            'status_changed' => '🔄',
            'expense_added' => '💰',
            'expense_removed' => '💸',
            'collaborator_added' => '👥',
            'collaborator_removed' => '👤',
            'client_assigned' => '🤝',
            'media_removed' => '🖼️',
            'project_deleted' => '🗑️',
            default => '📝',
        };
    }

    public function getActionBadgeClass(): string
    {
        return match($this->action) {
            'created' => 'bg-green-500 text-white',
            'updated' => 'bg-blue-500 text-white',
            'status_changed' => 'bg-purple-500 text-white',
            'expense_added' => 'bg-red-500 text-white',
            'expense_removed' => 'bg-red-700 text-white',
            'collaborator_added', 'collaborator_removed' => 'bg-yellow-500 text-black',
            'client_assigned' => 'bg-indigo-500 text-white',
            'media_removed' => 'bg-orange-500 text-white',
            'project_deleted' => 'bg-gray-900 text-white',
            default => 'bg-gray-500 text-white',
        };
    }
}