<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\Post;
use App\Entity\User;
use App\State\CollaboratorRegistrationProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Inscription publique "collaborateur" (pro/freelance) — cf. plan : un
 * freelance est un compte réel (rôles USER/EDITOR/MODERATOR selon promotion),
 * pas un simple message. Créé à ROLE_USER ; un administrateur le promeut
 * ensuite en ROLE_EDITOR depuis /admin/collaborators (cf. AdminCollaboratorController),
 * comme pour toute autre voie d'obtention du rôle collaborateur dans l'app.
 */
#[ApiResource(
    stateOptions: new Options(entityClass: User::class),
    shortName: 'CollaboratorRegistration',
    description: "Permet à un pro/freelance de créer un compte collaborateur (rôle attribué ensuite par un administrateur).",
    operations: [
        new Post(
            uriTemplate: '/collaborator_registrations',
            denormalizationContext: ['groups' => ['collaborator_signup']],
            // Le processor retourne une vraie App\Entity\User (pas la sous-classe
            // ApiResource) : la normalisation de la réponse doit donc utiliser un
            // groupe dont tous les champs existent sur l'entité de base — jamais
            // "collaborator_signup" qui porte plainPassword/agreeTerms, propres à
            // cette seule sous-classe.
            normalizationContext: ['groups' => ['api_user']],
            processor: CollaboratorRegistrationProcessor::class,
            description: "Crée un compte collaborateur (ROLE_USER) à partir du formulaire public d'inscription freelance.",
        ),
    ],
)]
class CollaboratorRegistrationApiResource extends User
{
    #[Assert\NotBlank(message: "Veuillez entrer un mot de passe.")]
    #[Assert\Length(min: 8, max: 4096, minMessage: "Votre mot de passe doit contenir au moins {{ limit }} caractères.")]
    #[Assert\Regex(
        pattern: '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).+$/',
        message: "Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.",
    )]
    #[Groups(['collaborator_signup'])]
    private string $plainPassword = '';

    #[Assert\IsTrue(message: "Vous devez accepter les conditions générales.")]
    #[Groups(['collaborator_signup'])]
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
