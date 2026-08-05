<?php

namespace App\Controller\Api;

use App\Entity\Comment;
use App\Entity\Invoice;
use App\Entity\Media;
use App\Entity\Project;
use App\Entity\ProjectHistory;
use App\Entity\ProjectInfo;
use App\Entity\QuoteRequest;
use App\Entity\User;
use App\Enum\InvoiceStatusEnum;
use App\Repository\ProjectRepository;
use App\Repository\QuoteRequestRepository;
use App\Service\EmailManager;
use App\Service\JWTService;
use App\Service\ProjectNotifier;
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
     * `phone` n'y figure pas volontairement : modification désactivée en
     * self-service (changement de numéro géré hors de cet espace).
     */
    private const EDITABLE_FIELDS = ['fullName', 'bio', 'specialties', 'availability', 'portfolioUrl'];

    /**
     * Actions d'historique visibles par un client (par opposition à un membre
     * de l'équipe, qui voit tout sauf 'access_denied') : uniquement le cycle
     * de vie du projet, jamais les mouvements internes (dépenses,
     * collaborateurs) — même logique que la rétention de budget/spent.
     */
    private const CLIENT_SAFE_HISTORY_ACTIONS = ['created', 'status_changed'];

    /** Nombre d'entrées renvoyées par /api/me/activity, toutes catégories confondues. */
    private const ACTIVITY_LIMIT = 20;

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
        // `phone` est volontairement ignoré ici : absent de EDITABLE_FIELDS,
        // non modifiable en self-service.
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
     * Flux d'activité de l'utilisateur courant : historique de projet
     * (ProjectHistory) et messages (Comment) agrégés sur tous ses projets
     * (owned/collaborating/client), triés du plus récent au plus ancien.
     * Le client ne voit qu'un sous-ensemble « sûr » de l'historique — cf.
     * CLIENT_SAFE_HISTORY_ACTIONS.
     */
    #[Route('/activity', name: 'activity', methods: ['GET'])]
    public function activity(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $projects = $this->getAllUserProjects($user);

        $history = [];
        $messages = [];
        foreach ($projects as $project) {
            $isTeamMember = $project->isTeamMember($user);
            $isClient = $project->getClient() === $user;

            foreach ($project->getHistories() as $entry) {
                $visible = $isTeamMember
                    ? 'access_denied' !== $entry->getAction()
                    : ($isClient && \in_array($entry->getAction(), self::CLIENT_SAFE_HISTORY_ACTIONS, true));

                if ($visible) {
                    $history[] = $this->serializeHistoryEntry($entry, $project);
                }
            }

            foreach ($project->getComments() as $comment) {
                $messages[] = $this->serializeComment($comment, $project, $user);
            }
        }

        usort($history, static fn (array $a, array $b) => $b['createdAt'] <=> $a['createdAt']);
        usort($messages, static fn (array $a, array $b) => $b['createdAt'] <=> $a['createdAt']);

        return $this->json([
            'history' => \array_slice($history, 0, self::ACTIVITY_LIMIT),
            'messages' => \array_slice($messages, 0, self::ACTIVITY_LIMIT),
        ]);
    }

    /**
     * Fil de discussion complet d'un projet auquel l'utilisateur est
     * rattaché (client, responsable ou collaborateur) — ordre chronologique.
     */
    #[Route('/projects/{id}/comments', name: 'project_comments_read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function readComments(int $id, ProjectRepository $projectRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $project = $projectRepository->find($id);
        if (!$project instanceof Project || (!$project->isTeamMember($user) && $project->getClient() !== $user)) {
            return $this->json(['detail' => 'Projet introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $comments = array_map(
            fn (Comment $c): array => $this->serializeComment($c, $project, $user),
            $project->getComments()->toArray(),
        );

        return $this->json(['comments' => $comments]);
    }

    /**
     * Poste un message dans le fil de discussion d'un projet — même
     * périmètre d'accès que readComments().
     */
    #[Route('/projects/{id}/comments', name: 'project_comments_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function createComment(int $id, Request $request, ProjectRepository $projectRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $project = $projectRepository->find($id);
        if (!$project instanceof Project || (!$project->isTeamMember($user) && $project->getClient() !== $user)) {
            return $this->json(['detail' => 'Projet introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        $content = \is_array($payload) ? $this->nullableString($payload['content'] ?? null) : null;
        if (null === $content) {
            return $this->json(['detail' => 'Le message ne peut pas être vide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $comment = new Comment();
        $comment->setProject($project)->setAuthor($user)->setContent($content);

        $violations = $this->validator->validate($comment);
        if (\count($violations) > 0) {
            return $this->json(['detail' => $violations[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $project->addComment($comment);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $this->json($this->serializeComment($comment, $project, $user), Response::HTTP_CREATED);
    }

    /**
     * Le client confirme être d'accord avec le montant d'une facture — ne
     * change pas son statut de paiement, juste un signal envoyé à l'équipe.
     */
    #[Route('/invoices/{id}/validate', name: 'invoice_validate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function validateInvoice(int $id, ProjectNotifier $projectNotifier): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $invoice = $this->findClientInvoice($id, $user);
        if (!$invoice instanceof Invoice) {
            return $this->json(['detail' => 'Facture introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (InvoiceStatusEnum::PENDING !== $invoice->getStatus() || $invoice->isValidated()) {
            return $this->json(['detail' => 'Cette facture ne peut plus être validée dans son état actuel.'], Response::HTTP_CONFLICT);
        }

        $invoice->markValidated();
        $this->entityManager->flush();

        $projectNotifier->invoiceValidated($invoice);

        return $this->json($this->serializeInvoice($invoice, $invoice->getProject()));
    }

    /**
     * Le client demande une révision du montant d'une facture : le motif est
     * posté comme message dans le fil de discussion du projet (historique
     * partagé, visible par l'équipe), et le statut de la facture bascule pour
     * signaler qu'elle est en discussion plutôt que simplement en attente.
     */
    #[Route('/invoices/{id}/request-revision', name: 'invoice_request_revision', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function requestInvoiceRevision(int $id, Request $request, ProjectNotifier $projectNotifier): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $invoice = $this->findClientInvoice($id, $user);
        if (!$invoice instanceof Invoice) {
            return $this->json(['detail' => 'Facture introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (InvoiceStatusEnum::PENDING !== $invoice->getStatus() || $invoice->isValidated()) {
            return $this->json(['detail' => 'Une révision ne peut plus être demandée pour cette facture dans son état actuel.'], Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        $message = \is_array($payload) ? $this->nullableString($payload['message'] ?? null) : null;
        if (null === $message) {
            return $this->json(['detail' => 'Merci de préciser le motif de votre demande de révision.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $project = $invoice->getProject();

        $comment = new Comment();
        $comment
            ->setProject($project)
            ->setAuthor($user)
            ->setContent(sprintf('💬 Demande de révision — Facture %s (%s) : %s', $invoice->getNumber(), $invoice->getFormattedAmount(), $message));

        $violations = $this->validator->validate($comment);
        if (\count($violations) > 0) {
            return $this->json(['detail' => $violations[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $invoice->markRevisionRequested();
        $project->addComment($comment);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $projectNotifier->invoiceRevisionRequested($invoice, $message);

        return $this->json($this->serializeInvoice($invoice, $project));
    }

    /** Facture appartenant à un projet dont l'utilisateur courant est le client — null sinon. */
    private function findClientInvoice(int $id, User $user): ?Invoice
    {
        $invoice = $this->entityManager->getRepository(Invoice::class)->find($id);
        if (!$invoice instanceof Invoice || $invoice->getProject()->getClient() !== $user) {
            return null;
        }

        return $invoice;
    }

    /**
     * Union dédupliquée des projets auxquels l'utilisateur est rattaché,
     * tous rôles confondus (owner, collaborateur, client).
     *
     * @return Project[]
     */
    private function getAllUserProjects(User $user): array
    {
        $clientProjects = $this->projectRepository->findBy(['client' => $user]);

        $byId = [];
        foreach ([...$user->getOwnedProjects(), ...$user->getCollaboratingProjects(), ...$clientProjects] as $project) {
            $byId[$project->getId()] = $project;
        }

        return array_values($byId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeHistoryEntry(ProjectHistory $entry, Project $project): array
    {
        return [
            'id' => $entry->getId(),
            'projectId' => $project->getId(),
            'projectTitle' => $project->getTitle(),
            'action' => $entry->getAction(),
            'actionLabel' => $entry->getActionLabel(),
            'details' => $entry->getDetails(),
            'createdAt' => $entry->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeComment(Comment $comment, Project $project, User $viewer): array
    {
        $author = $comment->getAuthor();

        return [
            'id' => $comment->getId(),
            'projectId' => $project->getId(),
            'projectTitle' => $project->getTitle(),
            'content' => $comment->getContent(),
            'createdAt' => $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'isMine' => $author === $viewer,
            'author' => [
                'id' => $author->getId(),
                'fullName' => $author->getFullName(),
                'email' => $author->getEmail(),
            ],
        ];
    }

    /**
     * @param Project[] $clientProjects
     *
     * @return array<int, array<string, mixed>>
     */
    private function serializeClientInvoices(array $clientProjects): array
    {
        $invoices = [];
        foreach ($clientProjects as $project) {
            foreach ($project->getInvoices() as $invoice) {
                $invoices[] = $this->serializeInvoice($invoice, $project);
            }
        }

        return $invoices;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvoice(Invoice $invoice, Project $project): array
    {
        return [
            'id' => $invoice->getId(),
            'projectId' => $project->getId(),
            'projectTitle' => $project->getTitle(),
            'number' => $invoice->getNumber(),
            'label' => $invoice->getLabel(),
            'amount' => $invoice->getAmount(),
            'currency' => $invoice->getCurrency(),
            'formattedAmount' => $invoice->getFormattedAmount(),
            'status' => $invoice->getStatus()->value,
            'statusLabel' => $invoice->getStatus()->getLabel(),
            'issuedAt' => $invoice->getIssuedAt()->format(\DateTimeInterface::ATOM),
            'dueDate' => $invoice->getDueDate()?->format(\DateTimeInterface::ATOM),
            'paidAt' => $invoice->getPaidAt()?->format(\DateTimeInterface::ATOM),
            'validatedAt' => $invoice->getValidatedAt()?->format(\DateTimeInterface::ATOM),
            'overdue' => $invoice->isOverdue(),
        ];
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
                // Factures des projets dont l'utilisateur est le client — jamais
                // pour l'équipe (owner/collaborateur), même restriction de
                // périmètre que budget/spent sur Project.
                'invoices' => $this->serializeClientInvoices($clientProjects),
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
