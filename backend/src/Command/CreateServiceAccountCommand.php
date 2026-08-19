<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Seule voie de création d'un compte de service (intégration/automatisation,
 * pas une personne) — volontairement en CLI, pas de formulaire web ni
 * d'auto-inscription possible pour ce type de compte.
 *
 * Porte le rôle ROLE_SERVICE, volontairement absent de role_hierarchy
 * (security.yaml) : aucun héritage, aucun accès par défaut au-delà de ce qui
 * sera explicitement accordé plus tard, ressource par ressource, dans les
 * attributs `security` d'API Platform. S'authentifie ensuite exactement
 * comme un compte humain, via le flux JWT existant (POST /api/login_check) —
 * aucune infrastructure supplémentaire (pas de serveur OAuth2, pas
 * d'endpoint dédié).
 *
 * isVerified/isActive sont posés à vrai directement : pas de personne pour
 * cliquer un lien de vérification email.
 */
#[AsCommand(name: 'app:service-account:create', description: 'Crée un compte de service (API) à scope restreint.')]
class CreateServiceAccountCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AuditLogger $auditLogger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Identifiant du compte (adresse email, ne reçoit jamais réellement de mail).')
            ->addArgument('label', InputArgument::REQUIRED, "Description de l'usage (ex: « Intégration reporting BI »).")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = trim((string) $input->getArgument('email'));
        $label = trim((string) $input->getArgument('label'));

        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error(sprintf('« %s » n\'est pas une adresse email valide.', $email));

            return Command::FAILURE;
        }

        if ('' === $label) {
            $io->error("La description de l'usage est obligatoire.");

            return Command::FAILURE;
        }

        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            $io->error(sprintf('Un compte existe déjà pour « %s ».', $email));

            return Command::FAILURE;
        }

        $io->section(sprintf('Nouveau compte de service : %s', $email));
        $io->text([
            'Usage : '.$label,
            'Rôle : ROLE_SERVICE (aucun accès par défaut — à accorder ressource par ressource si besoin).',
        ]);

        if (!$io->confirm('Confirmer la création ?', false)) {
            $io->comment('Annulé.');

            return Command::SUCCESS;
        }

        $plainPassword = bin2hex(random_bytes(32));

        $user = new User();
        $user->setEmail($email);
        $user->setFullName($label);
        $user->setRoles(['ROLE_SERVICE']);
        $user->setIsSystemAccount(true);
        $user->setIsActive(true);
        $user->setIsVerified(true);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->auditLogger->log(User::class, (int) $user->getId(), $user->getEmail(), 'service_account_created', $label);
        $this->entityManager->flush();

        $io->success(sprintf('Compte de service « %s » créé (#%d).', $email, $user->getId()));
        $io->warning('Mot de passe affiché une seule fois ci-dessous — il ne sera plus jamais récupérable en clair :');
        $io->text($plainPassword);
        $io->note("S'authentifie via POST /api/login_check comme un compte classique, pour obtenir un JWT.");

        return Command::SUCCESS;
    }
}
