<?php

namespace App\Service;

use App\Entity\Offer;
use App\Repository\OfferRepository;

/**
 * Catalogue des offres PUBLIÉES, transmis à l'assistant du site pour qu'il
 * tienne la promesse de son accueil (« je vous oriente vers la bonne
 * solution, avec son prix et son délai »). Sans lui, l'assistant ne
 * connaissait aucun prix : « Combien ça coûte ? » (suggestion de départ)
 * recevait « je n'ai pas cette information ».
 *
 * Texte déterministe (même ordre, même format) : le bloc système reste
 * identique d'un appel à l'autre et profite du cache de prompt Claude.
 * Les offres non publiées n'y figurent jamais (slug en préparation).
 */
final class AiAssistantOfferCatalog
{
    public function __construct(private readonly OfferRepository $offers)
    {
    }

    public function render(string $locale): string
    {
        $en = 'en' === $locale;
        $lines = [];
        foreach ($this->offers->findAllOrdered() as $offer) {
            if (!$offer->isPublished()) {
                continue;
            }
            $lines[] = $this->line($offer, $en);
        }

        return [] === $lines ? '(aucune offre publiée pour l\'instant)' : implode("\n", $lines);
    }

    private function line(Offer $offer, bool $en): string
    {
        $title = ($en ? $offer->getTitleEn() : null) ?: $offer->getTitle();
        $card = ($en ? $offer->getCardTextEn() : null) ?: $offer->getCardText();
        $delay = ($en ? $offer->getDelayEn() : null) ?: $offer->getDelay();
        $amount = $en
            ? number_format($offer->getPrice(), 0, '.', ',')
            : number_format($offer->getPrice(), 0, ',', ' ');
        $price = $offer->isPriceOnce()
            ? sprintf($en ? 'single price %s FCFA' : 'prix unique %s FCFA', $amount)
            : sprintf($en ? 'from %s FCFA' : 'à partir de %s FCFA', $amount);

        return sprintf(
            '- %s | %s | %s %s | %s | %s',
            self::oneLine($title),
            $price,
            $en ? 'lead time' : 'délai',
            self::oneLine($delay),
            self::oneLine($card),
            sprintf('[%s](/offres/%s)', self::oneLine($title), $offer->getSlug()),
        );
    }

    /**
     * Une offre = une ligne, au format strict « titre | prix | délai | carte
     * | lien » : retire tout caractère qui casserait ce format — retour à la
     * ligne, séparateur `|` (décale les colonnes), `<` `>` (referme le bloc
     * <catalogue> du system prompt), crochets/parenthèses (casse le lien
     * Markdown). Saisi au back-office, jamais la saisie du visiteur.
     */
    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/[\s\[\]()|<>]+/u', ' ', $text));
    }
}
