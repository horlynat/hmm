<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CollaboratorRegistrationApiResource;
use App\Entity\User;
use App\Enum\NotificationPriorityEnum;
use App\Exception\ConflictException;
use App\Service\AdminAlertNotifier;
use App\Service\EmailManager;
use App\Service\JWTService;
use App\Service\PublicSubmissionThrottler;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée un vrai compte User (ROLE_USER) à partir de l'inscription publique
 * freelance — même mécanique que RegistrationController::register() (hash du
 * mot de passe, email de vérification via JWTService/EmailManager, alerte
 * admin), avec en plus les champs de profil collaborateur (specialties,
 * availability, portfolioUrl, bio). Le passage en ROLE_EDITOR reste une
 * action manuelle de l'administrateur depuis /admin/collaborators — aucun
 * rôle d'édition n'est accordé automatiquement à un inconnu qui remplit un
 * formulaire public.
 */
final class CollaboratorRegistrationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTService $jwt,
        private readonly EmailManager $emailManager,
        private readonly AdminAlertNotifier $adminAlertNotifier,
        private readonly PublicSubmissionThrottler $throttler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        \assert($data instanceof CollaboratorRegistrationApiResource);

        $this->throttler->assertRegistrationAllowed();

        $user = new User();
        $user->setEmail($data->getEmail());
        $user->setFullName($data->getFullName());
        $user->setPhone($data->getPhone());
        $user->setSpecialties($data->getSpecialties());
        $user->setAvailability($data->getAvailability());
        $user->setPortfolioUrl($data->getPortfolioUrl());
        $user->setBio($data->getBio());
        $user->setPassword($this->passwordHasher->hashPassword($user, $data->getPlainPassword()));
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($user);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            // Le validator (UniqueEntity sur User::email) a déjà été passé avant
            // d'arriver ici : ce cas ne survient qu'en cas de course entre deux
            // requêtes concurrentes sur le même email (double clic, retry après
            // timeout réseau...). On évite de laisser fuiter une erreur SQL brute.
            throw new ConflictException('Un compte existe déjà avec cet email.', context: ['email' => $user->getEmail()], previous: $e);
        }

        $this->adminAlertNotifier->alert(
            NotificationPriorityEnum::LOW,
            'Nouvelle candidature freelance',
            sprintf(
                '%s <%s> vient de créer un compte collaborateur (spécialités : %s).',
                $user->getFullName() ?? $user->getEmail(),
                $user->getEmail(),
                $user->getSpecialties() ? implode(', ', $user->getSpecialties()) : '—',
            ),
        );

        $token = $this->jwt->generateEmailVerificationToken($user->getId());

        $this->emailManager->sendNow(
            to: $user->getEmail(),
            subject: 'Confirmez votre adresse email',
            template: 'confirmation_email',
            context: [
                'user' => $user,
                'token' => $token,
                'fullName' => $user->getFullName(),
            ],
        );

        return $user;
    }
}
