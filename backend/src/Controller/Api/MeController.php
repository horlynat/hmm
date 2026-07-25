<?php

namespace App\Controller\Api;

use App\Entity\Project;
use App\Entity\QuoteRequest;
use App\Entity\User;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Espace compte "self-service" consommé par le frontend Next.js.
 *
 * Ces endpoints vivent sous le firewall `api` (stateless JWT) : l'utilisateur
 * est authentifié via un Bearer token émis par /api/login_check. On expose ici
 * le profil de l'utilisateur *courant* et ses attributions selon son rôle
 * (freelance/collaborateur → projets ; client → projets & devis), sans jamais
 * permettre l'auto-modification de champs sensibles (roles / isActive /
 * isVerified) — cf. la même règle documentée dans App\Form\ProfileType.
 *
 * Note : pas de #[IsGranted('ROLE_USER')] au niveau classe. Le chemin ^/api est
 * en PUBLIC_ACCESS (access_control), donc une requête anonyme atteint le
 * contrôleur ; IsGranted y lèverait une AccessDeniedException — bruyante (log +
 * risque de fausse alerte admin, cf. ExceptionSubscriber). On renvoie plutôt un
 * 401 JSON propre quand aucun utilisateur n'est authentifié.
 */
#[Route('/api/me', name: 'api_me_')]
final class MeController extends AbstractController
{
    /**
     * Champs que l'utilisateur peut modifier lui-même. Toute autre clé du corps
     * JSON est ignorée : liste blanche stricte contre l'élévation de privilèges.
     */
    private const EDITABLE_FIELDS = ['fullName', 'phone', 'bio', 'specialties', 'availability', 'portfolioUrl'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectRepository $projectRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'read', methods: ['GET'])]
    public function read(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($this->serializeUser($user));
    }

    #[Route('', name: 'update', methods: ['PATCH', 'PUT'])]
    public function update(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['detail' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (\array_key_exists('fullName', $payload)) {
            $user->setFullName($this->nullableString($payload['fullName']));
        }
        if (\array_key_exists('phone', $payload)) {
            $user->setPhone($this->nullableString($payload['phone']));
        }
        if (\array_key_exists('bio', $payload)) {
            $user->setBio($this->nullableString($payload['bio']));
        }
        if (\array_key_exists('availability', $payload)) {
            $user->setAvailability($this->nullableString($payload['availability']));
        }
        if (\array_key_exists('portfolioUrl', $payload)) {
            $user->setPortfolioUrl($this->nullableString($payload['portfolioUrl']));
        }
        if (\array_key_exists('specialties', $payload)) {
            $specialties = $payload['specialties'];
            $user->setSpecialties(
                \is_array($specialties)
                    ? array_values(array_filter(array_map('trim', array_map('strval', $specialties))))
                    : null,
            );
        }

        // Mot de passe : la contrainte de robustesse est portée par les formulaires ;
        // ici on valide explicitement longueur + complexité avant de hasher.
        $newPassword = $payload['plainPassword'] ?? null;
        if (\is_string($newPassword) && '' !== $newPassword) {
            $violations = $this->validator->validate($newPassword, [
                new \Symfony\Component\Validator\Constraints\Length(min: 8, max: 4096, minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.'),
                new \Symfony\Component\Validator\Constraints\Regex(
                    pattern: '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).+$/',
                    message: 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.',
                ),
            ]);

            if (\count($violations) > 0) {
                return $this->json(['detail' => $violations[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
            $user->setPasswordChangedAt(new \DateTimeImmutable());
        }

        // Valide les contraintes portées par l'entité (email, phone, portfolioUrl…).
        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            return $this->json(['detail' => $violations[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json($this->serializeUser($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        $roles = $user->getRoles();
        $isCollaborator = \in_array('ROLE_EDITOR', $roles, true);

        // Attributions "client" : projets dont l'utilisateur est le client + ses devis.
        $clientProjects = $this->projectRepository->findBy(['client' => $user]);

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'phone' => $user->getPhone(),
            'bio' => $user->getBio(),
            'profileImage' => $user->getProfileImage(),
            'specialties' => $user->getSpecialties(),
            'availability' => $user->getAvailability(),
            'portfolioUrl' => $user->getPortfolioUrl(),
            'roles' => $roles,
            'isVerified' => $user->isVerified(),
            'isTwoFactorEnabled' => $user->isTwoFactorEnabled(),
            'isCollaborator' => $isCollaborator,
            'editableFields' => self::EDITABLE_FIELDS,
            'attributions' => [
                // Projets confiés au collaborateur/freelance (participation ou pilotage).
                'collaboratingProjects' => array_map($this->serializeProject(...), $user->getCollaboratingProjects()->toArray()),
                'ownedProjects' => array_map($this->serializeProject(...), $user->getOwnedProjects()->toArray()),
                // Projets/devis rattachés au compte client.
                'clientProjects' => array_map($this->serializeProject(...), $clientProjects),
                'quoteRequests' => array_map($this->serializeQuote(...), $user->getQuoteRequest()->toArray()),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProject(Project $project): array
    {
        return [
            'id' => $project->getId(),
            'title' => $project->getTitle(),
            'status' => $project->getStatus()->value,
            'statusLabel' => $project->getStatusLabel(),
            'progress' => $project->getProgress(),
            'deadline' => $project->getDeadline()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeQuote(QuoteRequest $quote): array
    {
        return [
            'id' => $quote->getId(),
            'category' => $quote->getCategory(),
            'status' => $quote->getStatus()->value,
            'budget' => $quote->getBudget(),
            'currency' => $quote->getCurrency(),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
