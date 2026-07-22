<?php

namespace App\Entity;

use App\Enum\NotificationPriorityEnum;
use App\Repository\NotificationPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Préférence de canal par niveau d'importance (une ligne par NotificationPriorityEnum).
 *
 * Consommée par App\Notifier\DatabaseChannelPolicy pour piloter dynamiquement
 * les envois du Notifier Symfony (remplace la policy statique de notifier.yaml).
 */
#[ORM\Entity(repositoryClass: NotificationPreferenceRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_NOTIFICATION_PRIORITY', fields: ['priority'])]
class NotificationPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: NotificationPriorityEnum::class)]
    private NotificationPriorityEnum $priority;

    #[ORM\Column(type: 'boolean')]
    private bool $emailEnabled = true;

    /** Canal push (ntfy, cf. notifier.yaml / NTFY_DSN), désactivé par défaut : l'admin l'active explicitement par niveau. */
    #[ORM\Column(type: 'boolean')]
    private bool $pushEnabled = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(NotificationPriorityEnum $priority)
    {
        $this->priority = $priority;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPriority(): NotificationPriorityEnum
    {
        return $this->priority;
    }

    public function isEmailEnabled(): bool
    {
        return $this->emailEnabled;
    }

    public function setEmailEnabled(bool $emailEnabled): static
    {
        $this->emailEnabled = $emailEnabled;

        return $this;
    }

    public function isPushEnabled(): bool
    {
        return $this->pushEnabled;
    }

    public function setPushEnabled(bool $pushEnabled): static
    {
        $this->pushEnabled = $pushEnabled;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
