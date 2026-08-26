<?php

namespace App\Twig;

use App\Enum\CurrencyEnum;
use App\Repository\SystemSettingRepository;
use App\Service\CurrencyConversionService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Devise d'affichage active du back-office : cookie `display_currency`
 * (posé par AdminCurrencyController) si présent et valide, sinon le défaut
 * configuré dans SystemSetting. Le filtre `money` est l'unique point de
 * conversion+formatage appelé depuis les templates — remplace le
 * `number_format(...) . ' €'` fait à la main partout ailleurs.
 */
final class CurrencyExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SystemSettingRepository $systemSettingRepository,
        private readonly CurrencyConversionService $conversionService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('active_currency', $this->activeCurrency(...)),
            new TwigFunction('currency_choices', static fn (): array => CurrencyEnum::cases()),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->money(...)),
        ];
    }

    public function activeCurrency(): string
    {
        $cookie = $this->requestStack->getCurrentRequest()?->cookies->get('display_currency');
        if (\is_string($cookie) && null !== CurrencyEnum::tryFrom($cookie)) {
            return $cookie;
        }

        return $this->systemSettingRepository->getSettings()->getDefaultCurrency()->value;
    }

    public function money(mixed $amount, string $fromCurrency): string
    {
        return $this->conversionService->convertAndFormat((string) $amount, $fromCurrency, $this->activeCurrency());
    }
}
