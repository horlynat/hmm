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
 * Un second seuil, intermédiaire et strictement informatif (ASSISTANT_BUDGET_
 * ALERT_THRESHOLD_USD), envoie une alerte par email avant le plafond — le chat
 * continue de fonctionner normalement tant que le plafond lui-même n'est pas
 * atteint (cf. maybeWarn()).
 *
 * Les deux alertes email sont dédupliquées via le même mécanisme que
 * ErrorNotifier (RateLimiterFactory comme garde "au plus une fois par jour"),
 * pour ne pas spammer à chaque requête tant que le seuil concerné reste
 * franchi.
 */
final class AiAssistantBudgetGuard
{
    public function __construct(
        private readonly AiAssistantConversationLogRepository $logRepository,
        private readonly AdminAlertNotifier $adminAlertNotifier,
        #[Autowire(service: 'limiter.ai_assistant_budget_alert')] private readonly RateLimiterFactory $exceededAlertLimiter,
        #[Autowire(service: 'limiter.ai_assistant_budget_warning')] private readonly RateLimiterFactory $warningAlertLimiter,
        private readonly float $monthlyBudgetUsd,
        private readonly float $alertThresholdUsd,
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
            $this->maybeWarn($spent);

            return false;
        }

        if ($this->exceededAlertLimiter->create('monthly')->consume(1)->isAccepted()) {
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

    /**
     * Alerte informative dès franchissement du seuil intermédiaire — jamais
     * de coupure ici (contrairement au plafond ci-dessus). Ignorée si le
     * seuil n'est pas configuré, ou s'il est >= au plafond (le dépassement du
     * plafond déclenche déjà sa propre alerte URGENT, pas besoin des deux).
     */
    private function maybeWarn(float $spent): void
    {
        if ($this->alertThresholdUsd <= 0.0 || $this->alertThresholdUsd >= $this->monthlyBudgetUsd) {
            return;
        }

        if ($spent < $this->alertThresholdUsd) {
            return;
        }

        if (!$this->warningAlertLimiter->create('monthly')->consume(1)->isAccepted()) {
            return;
        }

        $this->adminAlertNotifier->alert(
            NotificationPriorityEnum::HIGH,
            'Assistant IA : seuil budgétaire mensuel approché',
            sprintf(
                "Le coût cumulé de l'assistant IA ce mois-ci (%.2f \$) a franchi le seuil d'alerte configuré (%.2f \$), "
                . "pour un plafond de coupure fixé à %.2f \$.\n"
                . 'Le chat conversationnel continue de fonctionner normalement — cette alerte est informative.',
                $spent,
                $this->alertThresholdUsd,
                $this->monthlyBudgetUsd,
            ),
        );
    }
}
