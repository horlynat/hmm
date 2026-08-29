<?php

namespace App\Twig;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly HtmlSanitizerInterface $richTextSanitizer,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_percentage', [$this, 'formatPercentage']),
            new TwigFilter('float', [$this, 'toFloat']), // Ajout du filtre float
            // Rendu sûr du HTML de l'éditeur riche (Article::content,
            // Project::description) : remplace `|raw` dans les gabarits
            // back-office (templates/admin/*/read.html.twig). Le contenu est
            // déjà sanitisé à l'écriture (App\Doctrine\RichTextSanitizerListener),
            // ce filtre est le filet pour les lignes enregistrées avant ce
            // correctif — même liste blanche (cf. config/packages/html_sanitizer.yaml).
            new TwigFilter('sanitize_rich_html', [$this, 'sanitizeRichHtml'], ['is_safe' => ['html']]),
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

    public function sanitizeRichHtml(?string $html): string
    {
        if (null === $html || '' === $html) {
            return '';
        }

        return $this->richTextSanitizer->sanitize($html);
    }
}
