<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Conversion de devises pour l'affichage (jamais pour les montants stockés,
 * qui restent dans leur devise native — cf. Invoice::$currency, ou implicite
 * EUR pour Project/ProjectExpense, cf. PROJECT_LEDGER_CURRENCY). Ne doit
 * jamais faire échouer un rendu de page : toute panne du fournisseur de taux
 * dégrade vers l'affichage du montant dans sa devise d'origine (convert()
 * renvoie null, convertAndFormat() retombe sur format() de la devise
 * source) — même philosophie que GeolocationService.
 */
class CurrencyConversionService
{
    /** Devise fixe des montants "livre de comptes" (Project::budget/spent, ProjectExpense::amount) — pas configurable, cf. décision utilisateur. */
    public const PROJECT_LEDGER_CURRENCY = 'EUR';

    private const RATES_ENDPOINT = 'https://open.er-api.com/v6/latest/EUR';
    private const RATES_CACHE_KEY = 'fx_rates_eur_base';
    private const SUCCESS_TTL = 21600; // 6h : les taux de change ne bougent pas assez vite pour ce cas d'usage (affichage, pas trading).
    private const FAILURE_TTL = 900; // 15 min : réessaie vite après une panne fournisseur sans le marteler.
    private const SCALE = 6; // précision intermédiaire avant arrondi final à 2 décimales (même convention que Project::getBudgetPercentageUsed()).

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Convertit un montant décimal de $from vers $to. Null si la conversion
     * est impossible (fournisseur en panne, devise absente de la table) —
     * l'appelant doit alors afficher le montant dans sa devise d'origine.
     */
    public function convert(string $amount, string $from, string $to): ?string
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $rates = $this->getRates();
        if (null === $rates || !isset($rates[$from], $rates[$to])) {
            return null;
        }

        // Taux exprimés base EUR (1 EUR = rates[X] unités de X).
        $amountInEur = 'EUR' === $from ? $amount : bcdiv($amount, (string) $rates[$from], self::SCALE);
        $converted = 'EUR' === $to ? $amountInEur : bcmul($amountInEur, (string) $rates[$to], self::SCALE);

        return bcadd($converted, '0', 2);
    }

    /** Formatte un montant dans une devise donnée (symbole/décimales ICU), sans aucune conversion. */
    public function format(string $amount, string $currency, string $locale = 'fr'): string
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

        return $formatter->formatCurrency((float) $amount, strtoupper($currency));
    }

    /**
     * convert() + format() en une passe. Ne renvoie jamais d'erreur à la
     * place d'un montant : si la conversion échoue, retombe sur le montant
     * formaté dans sa devise d'origine.
     */
    public function convertAndFormat(string $amount, string $from, string $to, string $locale = 'fr'): string
    {
        $converted = $this->convert($amount, $from, $to);

        return $this->format($converted ?? $amount, $converted ? $to : $from, $locale);
    }

    /**
     * Table de taux complète (base EUR), pour affichage informatif (ex. panneau
     * "taux de change actuels" du module Finance) — convert() reste le seul
     * point d'entrée pour une conversion réelle.
     *
     * @return array<string, float>|null null si le fournisseur est injoignable/invalide
     */
    public function getRatesSnapshot(): ?array
    {
        return $this->getRates();
    }

    /**
     * @return array<string, float>|null table de taux base EUR, ou null si le fournisseur est injoignable/invalide
     */
    private function getRates(): ?array
    {
        return $this->cache->get(self::RATES_CACHE_KEY, function (ItemInterface $item) {
            $rates = $this->fetchRates();
            $item->expiresAfter(null !== $rates ? self::SUCCESS_TTL : self::FAILURE_TTL);

            return $rates;
        });
    }

    /**
     * @return array<string, float>|null
     */
    private function fetchRates(): ?array
    {
        try {
            $data = $this->httpClient->request('GET', self::RATES_ENDPOINT, ['timeout' => 5])->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->warning('Conversion de devises : fournisseur de taux injoignable.', ['error' => $e->getMessage()]);

            return null;
        }

        if ('success' !== ($data['result'] ?? null) || !isset($data['rates']) || !\is_array($data['rates'])) {
            $this->logger->warning('Conversion de devises : réponse du fournisseur de taux invalide.', ['result' => $data['result'] ?? null]);

            return null;
        }

        return $data['rates'];
    }
}
