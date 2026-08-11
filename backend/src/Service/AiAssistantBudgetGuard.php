<?php

namespace App\Service;

use App\Enum\NotificationPriorityEnum;
use App\Repository\AiAssistantConversationLogRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Coupure automatique du chat conversationnel dès dépassement du plafond
 * budgétaire mensuel — protection financière (déni de service par coût),
 * pas seulement de charge serveur. Cf. §4.5/§4.7 du document d'architecture
 * assistant IA : "bascule automatique vers un mode dégradé... plutôt qu'une
 * interruption de service brutale" — ici, repli sur AiAssistantSettings.fallback
 * (géré par App\State\AiAssistantChatProcessor), pas un vrai crash.
 *
 * L'alerte email est dédupliquée via le même mécanisme que ErrorNotifier
 * (RateLimiterFactory comme garde "au plus une fois par jour"), pour ne pas
 * spammer à chaque requête tant que le seuil reste dépassé.
 */
final class AiAssistantBudgetGuard
{
    public function __construct(
        private readonly AiAssistantConversationLogRepository $logRepository,
        private readonly AdminAlertNotifier $adminAlertNotifier,
        #[Autowire(service: 'limiter.ai_assistant_budget_alert')] private readonly RateLimiterFactory $alertLimiter,
        private readonly float $monthlyBudgetUsd,
    ) {
    }

    public function isBudgetExceeded(): bool
    {
        if ($this->monthlyBudgetUsd <= 0.0) {
            // Pas de plafond configuré : pas de coupure (mais pas de suivi de coût non plus).
            return false;
        }

        $spent = $this->logRepository->sumCostThisMonth();

        if ($spent < $this->monthlyBudgetUsd) {
            return false;
        }

        if ($this->alertLimiter->create('monthly')->consume(1)->isAccepted()) {
            $this->adminAlertNotifier->alert(
                NotificationPriorityEnum::URGENT,
                'Assistant IA : plafond budgétaire mensuel dépassé',
                sprintf(
                    "Le coût cumulé de l'assistant IA ce mois-ci (%.2f $) a dépassé le plafond configuré (%.2f $).\n"
                    . 'Le chat conversationnel est automatiquement basculé en mode dégradé (réponse de repli statique) '
                    . "jusqu'au mois prochain, ou ajuste ASSISTANT_MONTHLY_BUDGET_USD si le trafic est légitime.",
                    $spent,
                    $this->monthlyBudgetUsd,
                ),
            );
        }

        return true;
    }
}
