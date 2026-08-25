<?php

namespace App\Entity;

use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Inscription à la newsletter du blog (page /blog, cf. NewsletterForm côté
 * frontend) — jusqu'ici un stub purement visuel, aucune entité ni endpoint
 * réel derrière le formulaire (un faux succès local, aucune persistance).
 *
 * Volontairement minimal pour l'instant : capture l'inscription elle-même,
 * pas l'envoi réel des e-mails de notification (nouvel article/projet) — une
 * fonctionnalité distincte et bien plus large (file d'attente d'envoi,
 * gestion des rebonds, lien de désinscription fonctionnel...), pas ce qui a
 * été demandé ici. `unsubscribedAt` existe déjà pour ne pas avoir à modifier
 * le schéma quand cet envoi sera construit.
 *
 * PAS de #[UniqueEntity] ici, volontairement : la constraint valide
 * `get_class($value)`, ici App\ApiResource\NewsletterSubscriberApiResource —
 * une classe qui n'est PAS elle-même mappée Doctrine (seul CE parent l'est).
 * UniqueEntityValidator échoue alors avec "Unable to find the object manager
 * associated with an entity of class ...ApiResource" — testé en pratique,
 * ça fait échouer TOUTE requête (pas seulement les doublons) avec un 500.
 * L'unicité est vérifiée explicitement dans NewsletterSubscriberCreateProcessor
 * à la place — cf. son docblock. La contrainte `unique: true` sur la colonne
 * `email` ci-dessous reste un filet de sécurité au niveau base de données.
 */
#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
#[ORM\Table(name: 'newsletter_subscriber')]
class NewsletterSubscriber
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['api_public', 'api_admin'])]
    #[Assert\NotBlank(message: "L'adresse e-mail est obligatoire.")]
    #[Assert\Email(message: "L'adresse e-mail n'est pas valide.")]
    #[Assert\Length(max: 180, maxMessage: "L'adresse e-mail ne peut pas dépasser {{ limit }} caractères.")]
    private string $email = '';

    /** Langue au moment de l'inscription — pour envoyer les futures notifications dans la bonne langue. */
    #[ORM\Column(length: 5)]
    #[Groups(['api_public', 'api_admin'])]
    #[Assert\Choice(choices: ['fr', 'en'], message: 'Langue invalide.')]
    private string $locale = 'fr';

    #[ORM\Column]
    #[Groups(['api_admin'])]
    private \DateTimeImmutable $subscribedAt;

    /** Renseigné si l'abonné se désinscrit — cf. docblock de classe. */
    #[ORM\Column(nullable: true)]
    #[Groups(['api_admin'])]
    private ?\DateTimeImmutable $unsubscribedAt = null;

    public function __construct()
    {
        $this->subscribedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getSubscribedAt(): \DateTimeImmutable
    {
        return $this->subscribedAt;
    }

    public function getUnsubscribedAt(): ?\DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function unsubscribe(): static
    {
        $this->unsubscribedAt = new \DateTimeImmutable();

        return $this;
    }

    /** Re-soumettre le formulaire avec un e-mail déjà désinscrit vaut consentement explicite — cf. NewsletterSubscriberCreateProcessor. */
    public function resubscribe(): static
    {
        $this->unsubscribedAt = null;

        return $this;
    }
}
