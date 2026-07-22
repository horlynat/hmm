<?php

namespace App\Twig\Components\Project;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\ProjectPriorityEnum;
use App\Enum\BillingTypeEnum;
use App\Enum\BudgetStatusEnum;
use App\Enum\ProjectStatusEnum; // Import de ton énumération de statuts[cite: 9]
use App\Repository\ProjectRepository; // Import du repository de projets[cite: 9]
use App\Repository\TagRepository; // Import du repository de tags[cite: 9]
use App\Repository\UserRepository; // Import du repository d'utilisateurs[cite: 9]
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent; // Import de l'attribut du composant[cite: 9]
use Symfony\UX\LiveComponent\Attribute\LiveAction; // Import de l'attribut d'action[cite: 9]
use Symfony\UX\LiveComponent\Attribute\LiveProp; // Import de l'attribut de propriété[cite: 9]
use Symfony\UX\LiveComponent\DefaultActionTrait; // Import du trait par défaut[cite: 9]

#[AsLiveComponent(name: 'admin_project_table', template: 'components/project/project_table.html.twig')] // Définition du composant[cite: 9]
class ProjectTable
{
    use DefaultActionTrait; // Utilisation du trait par défaut[cite: 9]

    #[LiveProp(writable: true, url: true)] 
    public int $page = 1; // Propriété de pagination[cite: 9]
    
    #[LiveProp(writable: true, url: true)] 
    public string $sort = 'createdAt'; // Propriété de tri[cite: 9]
    
    #[LiveProp(writable: true, url: true)] 
    public string $direction = 'desc'; // Propriété de direction du tri[cite: 9]

    // Correction : Nettoyage de l'option inconnue pour utiliser de simples LiveProp éditables
    #[LiveProp(writable: true)] public string $status = ''; // Filtre de statut[cite: 9]
    #[LiveProp(writable: true)] public string $search = ''; // Filtre de recherche[cite: 9]
    #[LiveProp(writable: true)] public string $budget_status = ''; // Filtre de statut du budget[cite: 9]
    #[LiveProp(writable: true)] public string $client = ''; // Filtre de client[cite: 9]
    #[LiveProp(writable: true)] public string $collaborator = ''; // Filtre de collaborateur[cite: 9]
    #[LiveProp(writable: true)] public ?float $budget_min = null; // Filtre de budget minimum[cite: 9]
    #[LiveProp(writable: true)] public ?float $budget_max = null; // Filtre de budget maximum[cite: 9]
    #[LiveProp(writable: true)] public string $date_start = ''; // Filtre de date de début[cite: 9]
    #[LiveProp(writable: true)] public string $date_end = ''; // Filtre de date de fin[cite: 9]
    #[LiveProp(writable: true)] public string $time_urgency = ''; // Filtre d'urgence temporelle[cite: 9]
    #[LiveProp(writable: true)] public ?int $inactive_days = null; // Filtre de jours d'inactivité[cite: 9]
    #[LiveProp(writable: true)] public string $billing_type = ''; // Filtre de type de facturation[cite: 9]
    #[LiveProp(writable: true)] public string $priority = ''; // Filtre de priorité[cite: 9]
    #[LiveProp(writable: true)] public string $tag = ''; // Filtre de tag[cite: 9]
    #[LiveProp(writable: true)] public string $team_size = ''; // Filtre de taille d'équipe[cite: 9]
    #[LiveProp(writable: true)] public string $orphan = ''; // Filtre d'anomalie[cite: 9]
    #[LiveProp(writable: true)] public ?int $progress_min = null; // Filtre d'avancement minimum[cite: 9]
    #[LiveProp(writable: true)] public ?int $progress_max = null; // Filtre d'avancement maximum[cite: 9]
    
    private int $limit = 20; // Limite de projets par page[cite: 9]

    public function __construct(
        private ProjectRepository $projectRepository, // Repository des projets[cite: 9]
        private UserRepository $userRepository, // Repository des utilisateurs[cite: 9]
        private TagRepository $tagRepository, // Repository des tags[cite: 9]
    ) {
    }

    /** @return array<string, mixed> */
    private function getFilterArray(): array
    {
        return [
            'status' => $this->status, // Statut du projet[cite: 9]
            'title' => $this->search, // Titre recherché[cite: 9]
            'budget_status' => $this->budget_status, // Statut financier[cite: 9]
            'client' => $this->client, // Identifiant du client[cite: 9]
            'collaborator' => $this->collaborator, // Identifiant du collaborateur[cite: 9]
            'budget_min' => $this->budget_min, // Seuil minimum du budget[cite: 9]
            'budget_max' => $this->budget_max, // Seuil maximum du budget[cite: 9]
            'date_start' => $this->date_start, // Date minimale[cite: 9]
            'date_end' => $this->date_end, // Date maximale[cite: 9]
            'time_urgency' => $this->time_urgency, // Niveau d'urgence temporelle[cite: 9]
            'inactive_days' => $this->inactive_days, // Seuil d'inactivité en jours[cite: 9]
            'billing_type' => $this->billing_type, // Type de facturation choisi[cite: 9]
            'priority' => $this->priority, // Niveau de priorité[cite: 9]
            'tag' => $this->tag, // Identifiant du tag recherché[cite: 9]
            'team_size' => $this->team_size, // Catégorie de la taille d'équipe[cite: 9]
            'orphan' => $this->orphan, // Statut d'alerte ou anomalie[cite: 9]
            'progress_min' => $this->progress_min, // Taux minimal d'avancement[cite: 9]
            'progress_max' => $this->progress_max, // Taux maximal d'avancement[cite: 9]
            'sort' => $this->sort, // Champ sur lequel s'applique le tri[cite: 9]
            'direction' => $this->direction, // Sens de tri ascendant ou descendant[cite: 9]
        ];
    }

    /** @return Project[] */
    public function getProjects(): array
    {
        return $this->projectRepository->findByFilters(array_merge($this->getFilterArray(), [
            'page' => $this->page, 'limit' => $this->limit, // Récupération paginée des projets[cite: 9]
        ]));
    }

    public function getTotalPages(): int
    {
        $all = $this->projectRepository->findByFilters($this->getFilterArray()); // Liste complète pour compter[cite: 9]

        return (int) ceil(count($all) / $this->limit); // Calcul du nombre de pages totales[cite: 9]
    }

    /**
     * Calcule le total global des résultats filtrés (affiché sur le Twig)
     */
    public function getTotalItems(): int
    {
        return count($this->projectRepository->findByFilters($this->getFilterArray())); // Compte global[cite: 9]
    }

    /**
     * Alimente les boutons de statuts horizontaux et le select latéral
     */
    /** @return ProjectStatusEnum[] */
    public function getStatuses(): array
    {
        return ProjectStatusEnum::cases(); // Renvoie UPCOMING, IN_PROGRESS, etc.[cite: 6, 9]
    }

    /** @return ProjectPriorityEnum[] */
    public function getPriorities(): array
    {
        return ProjectPriorityEnum::cases();
    }

    /** @return BillingTypeEnum[] */
    public function getBillingTypes(): array
    {
        return BillingTypeEnum::cases();
    }

    /** @return BudgetStatusEnum[] */
    public function getBudgetStatuses(): array
    {
        return BudgetStatusEnum::cases();
    }

    /** @return User[] */
    public function getUsers(): array
    {
        return $this->userRepository->findAll(); // Récupère tous les utilisateurs en base[cite: 9]
    }

    /** @return Tag[] */
    public function getTags(): array
    {
        return $this->tagRepository->findAll(); // Récupère tous les tags en base[cite: 9]
    }

    /**
     * Alimente les compteurs par statut en haut de ton tableau
     *
     * @return array<string, array{count: int}>
     */
    public function getProjectsByStatus(): array
    {
        $counts = []; // Initialisation du tableau des comptes[cite: 9]
        foreach (ProjectStatusEnum::cases() as $case) { // Parcours de l'énumération[cite: 9]
            // Crée un sous-filtre émulé uniquement pour le statut de la boucle
            $filters = array_merge($this->getFilterArray(), ['status' => $case->value]); // Surcharge du filtre[cite: 9]
            $counts[$case->value]['count'] = count($this->projectRepository->findByFilters($filters)); // Compte par cas[cite: 9]
        }
        return $counts; // Tableau finalisé[cite: 9]
    }

    #[LiveAction]
    public function changeSort(string $column): void
    {
        $this->direction = ($this->sort === $column && 'asc' === $this->direction) ? 'desc' : 'asc'; // Inversion de l'ordre[cite: 9]
        $this->sort = $column; // Changement de colonne cible[cite: 9]
        $this->page = 1; // Retour automatique en première page[cite: 9]
    }

    /**
     * Hook magique natif exécuté automatiquement après la mise à jour d'un modèle (data-model)
     */
    public function onUpdated(string $propertyName, mixed $newValue): void
    {
        // Si la propriété mise à jour est un filtre (tout sauf la page elle-même), on force le retour à la page 1
        if ('page' !== $propertyName) {
            $this->page = 1;
        }
    }
}
