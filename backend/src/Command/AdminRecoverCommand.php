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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Issue de secours ("break glass") pour un compte admin devenu inaccessible :
 * mot de passe oublié, 2FA perdue avec les codes de secours épuisés, ou
 * compte désactivé par AccountStatusSubscriber. Volontairement en CLI (donc
 * réservée à qui a déjà un accès serveur/SSH) plutôt qu'un flux web — pas de
 * surface web supplémentaire à sécuriser (pas d'email, pas de token à faire
 * fuiter) pour un scénario qui n'arrive quasiment jamais.
 *
 * Sans option précisée, applique les trois remèdes (comportement "je suis
 * bloqué, répare tout") ; chaque option peut aussi être ciblée seule.
 */
#[AsCommand(name: 'app:admin:recover', description: "Répare l'accès d'un compte existant : mot de passe, verrouillage, 2FA.")]
class AdminRecoverCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly AuditLogger $auditLogger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, "Email du compte à récupérer (doit déjà exister).")
            ->addOption('reset-password', null, InputOption::VALUE_NONE, 'Réinitialise le mot de passe (saisie interactive masquée).')
            ->addOption('unlock', null, InputOption::VALUE_NONE, 'Réactive le compte (isActive + isVerified à vrai).')
            ->addOption('disable-2fa', null, InputOption::VALUE_NONE, 'Désactive la double authentification (TOTP + codes de secours effacés).')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) $input->getArgument('email');
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $io->error(sprintf("Aucun compte trouvé pour l'email « %s ».", $email));

            return Command::FAILURE;
        }

        $resetPassword = (bool) $input->getOption('reset-password');
        $unlock = (bool) $input->getOption('unlock');
        $disable2fa = (bool) $input->getOption('disable-2fa');
        // Aucune option précisée : on applique les trois remèdes ("je suis bloqué, répare tout").
        if (!$resetPassword && !$unlock && !$disable2fa) {
            $resetPassword = $unlock = $disable2fa = true;
        }

        $actions = array_filter([
            $resetPassword ? 'réinitialiser le mot de passe' : null,
            $unlock ? 'réactiver le compte (isActive/isVerified)' : null,
            $disable2fa ? 'désactiver la double authentification' : null,
        ]);
        $io->section(sprintf('Compte : %s (rôles : %s)', $user->getEmail(), implode(', ', $user->getRoles())));
        $io->listing($actions);

        if (!$io->confirm('Confirmer ces actions ?', false)) {
            $io->comment('Annulé.');

            return Command::SUCCESS;
        }

        if ($resetPassword) {
            $plainPassword = $this->askNewPassword($io);
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            $user->setPasswordChangedAt(new \DateTimeImmutable());
        }

        if ($unlock) {
            $user->setIsActive(true);
            $user->setIsVerified(true);
        }

        if ($disable2fa) {
            $user->setIsTwoFactorEnabled(false);
            $user->setTotpSecret(null);
            $user->setBackupCodes([]);
        }

        $this->entityManager->flush();

        $this->auditLogger->log(User::class, (int) $user->getId(), $user->getEmail(), 'admin_recovery_cli', implode(', ', $actions));
        $this->entityManager->flush();

        $io->success(sprintf('Compte « %s » mis à jour.', $user->getEmail()));

        return Command::SUCCESS;
    }

    private function askNewPassword(SymfonyStyle $io): string
    {
        $constraints = [
            new Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
            new PasswordStrength(
                minScore: PasswordStrength::STRENGTH_MEDIUM,
                message: 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.',
            ),
        ];

        while (true) {
            $password = $io->askHidden('Nouveau mot de passe (saisie masquée)');
            $confirm = $io->askHidden('Confirmer le mot de passe');

            if ($password !== $confirm) {
                $io->warning('Les deux saisies ne correspondent pas, réessayez.');
                continue;
            }

            $violations = $this->validator->validate($password ?? '', $constraints);
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $io->warning($violation->getMessage());
                }
                continue;
            }

            return $password;
        }
    }
}
