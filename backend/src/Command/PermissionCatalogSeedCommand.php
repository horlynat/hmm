<?php

namespace App\Command;

use App\Entity\PermissionDefinition;
use App\Entity\Role;
use App\Repository\PermissionDefinitionRepository;
use App\Repository\RoleRepository;
use App\Service\PermissionRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Synchronise le catalogue permission_definition avec la liste ci-dessous —
 * source de vérité du RBAC dynamique, à tenir à jour manuellement quand un
 * Voter éligible (cf. PermissionDefinition) gagne/perd une permission.
 *
 * Idempotent et NON destructif pour les personnalisations déjà faites :
 * - Code nouveau → ligne créée, `currentRole` initialisé à `defaultRole`.
 * - Code déjà en base → seuls `label`/`category`/`defaultRole` sont resynchronisés
 *   (peuvent changer si le code évolue) ; `currentRole` n'est JAMAIS touché ici,
 *   pour ne jamais écraser une personnalisation faite depuis l'admin.
 * - Code en base mais absent de cette liste (permission retirée du code) →
 *   signalé en sortie, jamais supprimé automatiquement (une ligne orpheline
 *   n'est plus consultée par aucun Voter, donc sans risque ; sa suppression
 *   reste un choix humain).
 */
#[AsCommand(name: 'app:permissions:seed', description: 'Synchronise le catalogue de permissions pilotables en base avec le code (idempotent, ne touche jamais les personnalisations existantes).')]
class PermissionCatalogSeedCommand extends Command
{
    /**
     * @var array<string, array{label: string, category: string, defaultRole: string}>
     */
    private const CATALOG = [
        // Articles (ArticleVoter)
        'ARTICLE_VIEW' => ['label' => 'Voir les articles', 'category' => 'Articles', 'defaultRole' => 'ROLE_USER'],
        'ARTICLE_CREATE' => ['label' => 'Créer un article', 'category' => 'Articles', 'defaultRole' => 'ROLE_EDITOR'],
        'ARTICLE_EDIT' => ['label' => 'Modifier un article', 'category' => 'Articles', 'defaultRole' => 'ROLE_EDITOR'],
        'ARTICLE_DELETE' => ['label' => 'Supprimer un article', 'category' => 'Articles', 'defaultRole' => 'ROLE_MODERATOR'],

        // Contacts (ContactVoter)
        'CONTACT_VIEW' => ['label' => 'Voir les messages de contact', 'category' => 'Contacts', 'defaultRole' => 'ROLE_MODERATOR'],
        'CONTACT_ARCHIVE' => ['label' => 'Archiver un message de contact', 'category' => 'Contacts', 'defaultRole' => 'ROLE_MODERATOR'],
        'CONTACT_DELETE' => ['label' => 'Supprimer un message de contact', 'category' => 'Contacts', 'defaultRole' => 'ROLE_MANAGER'],

        // Formations (CourseVoter)
        'COURSE_CREATE' => ['label' => 'Créer une formation', 'category' => 'Formations', 'defaultRole' => 'ROLE_EDITOR'],
        'COURSE_EDIT' => ['label' => 'Modifier une formation', 'category' => 'Formations', 'defaultRole' => 'ROLE_EDITOR'],
        'COURSE_DELETE' => ['label' => 'Supprimer une formation', 'category' => 'Formations', 'defaultRole' => 'ROLE_MODERATOR'],

        // Tableau de bord (DashboardVoter)
        'DASHBOARD_VIEW' => ['label' => 'Voir le tableau de bord', 'category' => 'Tableau de bord', 'defaultRole' => 'ROLE_ADMIN'],
        'DASHBOARD_VIEW_STATS' => ['label' => 'Voir les statistiques du tableau de bord', 'category' => 'Tableau de bord', 'defaultRole' => 'ROLE_ADMIN'],
        'DASHBOARD_VIEW_LOGS' => ['label' => 'Voir les journaux depuis le tableau de bord', 'category' => 'Tableau de bord', 'defaultRole' => 'ROLE_ADMIN'],

        // Finance (FinanceVoter)
        'FINANCE_VIEW' => ['label' => 'Voir le module Finance', 'category' => 'Finance', 'defaultRole' => 'ROLE_MANAGER'],
        'FINANCE_EXPORT' => ['label' => 'Exporter les données financières', 'category' => 'Finance', 'defaultRole' => 'ROLE_MANAGER'],

        // Devis (QuoteVoter)
        'QUOTE_VIEW' => ['label' => 'Voir les demandes de devis', 'category' => 'Devis', 'defaultRole' => 'ROLE_MANAGER'],
        'QUOTE_EDIT' => ['label' => 'Modifier une demande de devis', 'category' => 'Devis', 'defaultRole' => 'ROLE_MANAGER'],
        'QUOTE_DELETE' => ['label' => 'Supprimer une demande de devis', 'category' => 'Devis', 'defaultRole' => 'ROLE_MANAGER'],
        'QUOTE_APPROVE' => ['label' => 'Approuver une demande de devis', 'category' => 'Devis', 'defaultRole' => 'ROLE_MANAGER'],
        'QUOTE_REJECT' => ['label' => 'Refuser une demande de devis', 'category' => 'Devis', 'defaultRole' => 'ROLE_MANAGER'],

        // Compétences (SkillVoter)
        'SKILL_CREATE' => ['label' => 'Créer une compétence', 'category' => 'Compétences', 'defaultRole' => 'ROLE_EDITOR'],
        'SKILL_EDIT' => ['label' => 'Modifier une compétence', 'category' => 'Compétences', 'defaultRole' => 'ROLE_EDITOR'],
        'SKILL_DELETE' => ['label' => 'Supprimer une compétence', 'category' => 'Compétences', 'defaultRole' => 'ROLE_MODERATOR'],

        // Support (SupportTicketVoter)
        'SUPPORT_TICKET_VIEW' => ['label' => 'Voir les tickets de support', 'category' => 'Support', 'defaultRole' => 'ROLE_MODERATOR'],
        'SUPPORT_TICKET_REPLY' => ['label' => 'Répondre à un ticket de support', 'category' => 'Support', 'defaultRole' => 'ROLE_MODERATOR'],
        'SUPPORT_TICKET_RESOLVE' => ['label' => 'Résoudre un ticket de support', 'category' => 'Support', 'defaultRole' => 'ROLE_MODERATOR'],
        'SUPPORT_TICKET_DELETE' => ['label' => 'Supprimer un ticket de support', 'category' => 'Support', 'defaultRole' => 'ROLE_MANAGER'],

        // Témoignages (TestimonialVoter)
        'TESTIMONIAL_VIEW' => ['label' => 'Voir les témoignages', 'category' => 'Témoignages', 'defaultRole' => 'ROLE_MODERATOR'],
        'TESTIMONIAL_CREATE' => ['label' => 'Créer un témoignage', 'category' => 'Témoignages', 'defaultRole' => 'ROLE_MODERATOR'],
        'TESTIMONIAL_EDIT' => ['label' => 'Modifier un témoignage', 'category' => 'Témoignages', 'defaultRole' => 'ROLE_MODERATOR'],
        'TESTIMONIAL_APPROVE' => ['label' => 'Approuver un témoignage', 'category' => 'Témoignages', 'defaultRole' => 'ROLE_MODERATOR'],
        'TESTIMONIAL_REJECT' => ['label' => 'Refuser un témoignage', 'category' => 'Témoignages', 'defaultRole' => 'ROLE_MODERATOR'],
        'TESTIMONIAL_DELETE' => ['label' => 'Supprimer un témoignage', 'category' => 'Témoignages', 'defaultRole' => 'ROLE_MANAGER'],
    ];

    public function __construct(
        private readonly PermissionDefinitionRepository $repository,
        private readonly RoleRepository $roleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionRegistry $permissionRegistry,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $existing = [];
        foreach ($this->repository->findAllOrdered() as $definition) {
            $existing[$definition->getCode()] = $definition;
        }

        $created = 0;
        $resynced = 0;
        foreach (self::CATALOG as $code => $meta) {
            $role = $this->roleRepository->findOneByCode($meta['defaultRole']);
            if (!$role instanceof Role) {
                // La table role (6 lignes fixes, cf. migration qui l'a introduite) n'est pas
                // seedée — erreur de déploiement, pas un cas d'exécution normal à absorber.
                throw new \RuntimeException(sprintf('Rôle « %s » introuvable en base pour la permission « %s ».', $meta['defaultRole'], $code));
            }

            if (isset($existing[$code])) {
                $definition = $existing[$code];
                if ($definition->getLabel() !== $meta['label'] || $definition->getCategory() !== $meta['category'] || $definition->getDefaultRole()->getCode() !== $meta['defaultRole']) {
                    $definition->setLabel($meta['label'])->setCategory($meta['category'])->setDefaultRole($role);
                    ++$resynced;
                }
                unset($existing[$code]);
                continue;
            }

            $this->entityManager->persist(new PermissionDefinition($code, $meta['label'], $meta['category'], $role));
            ++$created;
        }

        $this->entityManager->flush();
        $this->permissionRegistry->invalidate();

        $io->success(sprintf('%d permission(s) créée(s), %d resynchronisée(s).', $created, $resynced));

        if ([] !== $existing) {
            $io->warning(sprintf(
                "%d permission(s) en base ne correspondent plus à aucun code source (retirées du Voter ?), non supprimées automatiquement :\n- %s",
                count($existing),
                implode("\n- ", array_keys($existing)),
            ));
        }

        return Command::SUCCESS;
    }
}
