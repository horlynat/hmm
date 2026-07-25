<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\Post;
use App\Entity\User;
use App\State\ClientRegistrationProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Inscription publique "client" — cf. séparation des espaces : la gestion des
 * comptes public (client, freelance, pro, collaborateur) est déléguée au
 * frontend Next.js. Un client est un compte réel (ROLE_USER) ; il se distingue
 * d'un collaborateur non par un rôle dédié mais par ses attributions
 * (Project.client, quoteRequest). Miroir de CollaboratorRegistrationApiResource,
 * sans les champs de profil collaborateur (specialties/availability/portfolioUrl).
 */
#[ApiResource(
    stateOptions: new Options(entityClass: User::class),
    shortName: 'ClientRegistration',
    description: "Permet à un client de créer un compte depuis le frontend Next.js.",
    operations: [
        new Post(
            uriTemplate: '/client_registrations',
            denormalizationContext: ['groups' => ['client_signup']],
            // Le processor retourne une vraie App\Entity\User (pas la sous-classe
            // ApiResource) : la normalisation doit utiliser un groupe dont tous les
            // champs existent sur l'entité de base — jamais "client_signup" qui
            // porte plainPassword/agreeTerms, propres à cette seule sous-classe.
            normalizationContext: ['groups' => ['api_user']],
            processor: ClientRegistrationProcessor::class,
            description: "Crée un compte client (ROLE_USER) à partir du formulaire public d'inscription.",
        ),
    ],
)]
class ClientRegistrationApiResource extends User
{
    #[Assert\NotBlank(message: "Veuillez entrer un mot de passe.")]
    #[Assert\Length(min: 8, max: 4096, minMessage: "Votre mot de passe doit contenir au moins {{ limit }} caractères.")]
    #[Assert\Regex(
        pattern: '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).+$/',
        message: "Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.",
    )]
    #[Groups(['client_signup'])]
    private string $plainPassword = '';

    #[Assert\IsTrue(message: "Vous devez accepter les conditions générales.")]
    #[Groups(['client_signup'])]
    private bool $agreeTerms = false;

    public function getPlainPassword(): string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function isAgreeTerms(): bool
    {
        return $this->agreeTerms;
    }

    public function setAgreeTerms(bool $agreeTerms): static
    {
        $this->agreeTerms = $agreeTerms;

        return $this;
    }
}
