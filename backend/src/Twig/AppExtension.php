<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_percentage', [$this, 'formatPercentage']),
            new TwigFilter('float', [$this, 'toFloat']), // Ajout du filtre float
        ];
    }

    public function formatPercentage(mixed $value, int $decimals = 0): string
    {
        if (!is_numeric($value)) {
            return '0 %';
        }
        
        return number_format($value * 100, $decimals, ',', ' ') . ' %';
    }

    // Méthode de conversion pour le filtre float
    public function toFloat(mixed $value): float
    {
        return (float) $value;
    }
}