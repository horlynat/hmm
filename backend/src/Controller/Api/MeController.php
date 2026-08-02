<?php

namespace App\Controller\Api;

use App\Entity\Media;
use App\Entity\Project;
use App\Entity\ProjectInfo;
use App\Entity\QuoteRequest;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\QuoteRequestRepository;
use App\Service\EmailManager;
use App\Service\JWTService;
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
        // ici on valide explicitement longueur + complexité avant de hasher. La
        // preuve de connaissance de l'ancien mot de passe est exigée ici (contrairement
        // à ProfileType côté back-office) car ce endpoint est accessible via un Bearer
        // token stateless : un token intercepté ne doit pas suffire à verrouiller le
        // titulaire légitime hors de son compte.
        $newPassword = $payload['plainPassword'] ?? null;
        if (\is_string($newPassword) && '' !== $newPassword) {
            $currentPassword = $payload['currentPassword'] ?? null;
            if (!\is_string($currentPassword) || '' === $currentPassword || !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                return $this->json(['detail' => 'Mot de passe actuel incorrect.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

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
     * Suppression (anonymisation) du compte courant — droit à l'effacement en
     * self-service. Anonymisation plutôt que suppression physique : le
     * compte peut être référencé par des enregistrements métier légitimes du
     * prestataire (Project.client, QuoteRequest.user, historique de
     * connexion...) qui doivent survivre à la demande (traçabilité
     * commerciale) — seules les données personnelles identifiantes sont
     * effacées, et le compte est désactivé (login impossible ensuite).
     *
     * Réservé aux comptes client/collaborateur simples : un compte staff
     * (ROLE_EDITOR et au-dessus) gère le back-office, sa suppression est une
     * action d'administration, pas un self-service — on refuse pour éviter
     * qu'un collaborateur/admin ne se verrouille lui-même hors du back-office
     * par erreur.
     */
    #[Route('', name: 'delete', methods: ['DELETE'])]
    public function delete(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        if (\in_array('ROLE_EDITOR', $user->getRoles(), true)) {
            return $this->json([
                'detail' => 'Les comptes collaborateur ou administrateur ne peuvent pas être supprimés depuis cet espace. Contactez un administrateur.',
            ], Response::HTTP_FORBIDDEN);
        }

        $user->setFullName(null);
        $user->setPhone(null);
        $user->setBio(null);
        $user->setProfileImage(null);
        $user->setSpecialties(null);
        $user->setAvailability(null);
        $user->setPortfolioUrl(null);
        $user->setIsTwoFactorEnabled(false);
        $user->setTotpSecret(null);
        $user->setIsActive(false);
        $user->setEmail(sprintf('deleted-user-%d@deleted.invalid', (int) $user->getId()));
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $this->json(['detail' => 'Compte supprimé.']);
    }

    /**
     * Renvoie l'email de vérification (même logique que RegistrationController::resendVerif(),
     * mais accessible sans passer par le firewall web `main` — nécessaire pour un compte
     * qui ne s'est jamais connecté qu'au frontend Next.js).
     */
    #[Route('/resend-verification', name: 'resend_verification', methods: ['POST'])]
    public function resendVerification(JWTService $jwt, EmailManager $emailManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isVerified()) {
            return $this->json(['detail' => 'Votre compte est déjà activé.'], Response::HTTP_CONFLICT);
        }

        $token = $jwt->generateEmailVerificationToken($user->getId());
        $emailManager->sendNow(
            to: $user->getEmail(),
            subject: 'Confirmez votre adresse email',
            template: 'confirmation_email',
            context: [
                'user' => $user,
                'token' => $token,
                'fullName' => $user->getFullName(),
            ],
        );

        return $this->json(['detail' => 'Un nouveau lien de vérification vous a été envoyé.']);
    }

    /**
     * Détail d'un projet auquel l'utilisateur courant est rattaché (client,
     * responsable ou collaborateur) — mêmes règles de périmètre que
     * App\Security\Voter\ProjectVoter::VIEW côté back-office.
     */
    #[Route('/projects/{id}', name: 'project_read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function readProject(int $id, ProjectRepository $projectRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $project = $projectRepository->find($id);
        if (!$project instanceof Project || (!$project->isTeamMember($user) && $project->getClient() !== $user)) {
            return $this->json(['detail' => 'Projet introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeProjectDetail($project));
    }

    /**
     * Détail d'une demande de devis appartenant à l'utilisateur courant.
     * Même règle de propriété que App\Controller\MemberQuoteController::read().
     */
    #[Route('/quotes/{id}', name: 'quote_read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function readQuote(int $id, QuoteRequestRepository $quoteRequestRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $quote = $quoteRequestRepository->find($id);
        if (!$quote instanceof QuoteRequest || $quote->getUser()?->getId() !== $user->getId()) {
            return $this->json(['detail' => 'Devis introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeQuoteDetail($quote));
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
            'lastLoginAt' => $user->getLastLoginAt()?->format(\DateTimeInterface::ATOM),
            'lastIp' => $user->getLastIp(),
            'lastLocation' => $user->getLastLocation(),
            'lastDevice' => $user->getLastDevice(),
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
            'updatedAt' => $project->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
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
            // Peut être `null` pour les demandes créées avant l'ajout de ce
            // champ — cf. QuoteRequest::$createdAt.
            'createdAt' => $quote->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProjectDetail(Project $project): array
    {
        $info = $project->getInfo();

        // Ni `budget` ni `spent` ne sont exposés ici : ce sont des chiffres de
        // gestion interne (combien l'enveloppe allouée au projet a été
        // consommée en dépenses d'équipe/freelance approuvées — cf. docblock
        // de Project::recalculateSpent()), pas ce que le client a payé. Ils
        // portent d'ailleurs `#[Groups(['api_admin'])]` sur l'entité — cette
        // méthode, qui sérialise à la main plutôt que via API Platform,
        // contournait ce garde-fou et les renvoyait aussi bien au client
        // qu'à un collaborateur via /api/me/projects/{id}.
        return [
            'id' => $project->getId(),
            'slug' => $project->getSlug(),
            'title' => $project->getTitle(),
            'description' => $project->getDescription(),
            'link' => $project->getLink(),
            'status' => $project->getStatus()->value,
            'statusLabel' => $project->getStatusLabel(),
            'priority' => $project->getPriority()?->value,
            'priorityLabel' => $project->getPriority()?->getLabel(),
            'billingType' => $project->getBillingType()?->value,
            'billingTypeLabel' => $project->getBillingType()?->getLabel(),
            'progress' => $project->getProgress(),
            'deadline' => $project->getDeadline()?->format(\DateTimeInterface::ATOM),
            'skills' => array_map(
                static fn ($skill): array => ['id' => $skill->getId(), 'name' => $skill->getName()],
                $project->getSkills()->toArray(),
            ),
            'tags' => array_map(
                static fn ($tag): array => ['id' => $tag->getId(), 'name' => $tag->getName()],
                $project->getTags()->toArray(),
            ),
            'media' => array_map($this->serializeMedia(...), $project->getMedia()->toArray()),
            'info' => $info instanceof ProjectInfo ? $this->serializeProjectInfo($info) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProjectInfo(ProjectInfo $info): array
    {
        return [
            'role' => $info->getRole(),
            'objectives' => $info->getObjectives(),
            'techStack' => $info->getTechStack(),
            'challenges' => $info->getChallenges(),
            'results' => $info->getResults(),
            'repoUrl' => $info->getRepoUrl(),
            'coverImage' => $info->getCoverImage() ? $this->serializeMedia($info->getCoverImage()) : null,
            'architectureDiagram' => $info->getArchitectureDiagram() ? $this->serializeMedia($info->getArchitectureDiagram()) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMedia(Media $media): array
    {
        return [
            'id' => $media->getId(),
            'filePath' => $media->getFilePath(),
            'altText' => $media->getAltText(),
            'mimeType' => $media->getMimeType(),
            'size' => $media->getSize(),
            'uploadedAt' => $media->getUploadedAt()?->format(\DateTimeInterface::ATOM),
            'type' => $media->getType(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeQuoteDetail(QuoteRequest $quote): array
    {
        return [
            'id' => $quote->getId(),
            'category' => $quote->getCategory(),
            'categoryDetail' => $quote->getCategoryDetail(),
            'status' => $quote->getStatus()->value,
            'statusLabel' => $quote->getStatus()->getLabel(),
            'budget' => $quote->getBudget(),
            'currency' => $quote->getCurrency(),
            'timeline' => $quote->getTimeline(),
            'channel' => $quote->getChannel(),
            'attachmentName' => $quote->getAttachmentName(),
            'clarifications' => $quote->getClarifications(),
            'message' => $quote->getMessage(),
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
