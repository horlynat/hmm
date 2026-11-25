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

    /**
     * Fiche détaillée de chaque offre publiée : tout ce que la page de l'offre
     * montre au visiteur (pour qui, problème, promesse, contenu, pack, avant /
     * après, calendrier, ce qu'il faut fournir). Sans elle, l'assistant ne
     * voyait que la phrase de la carte et répondait « je n'ai pas le détail »
     * à chaque question sur une offre — en production, sur sa propre offre
     * Assistant IA WhatsApp (2026-11-25).
     *
     * Chaque valeur passe par oneLine() (ni `<`/`>` pour refermer le bloc, ni
     * retour à la ligne pour se faire passer pour un autre champ).
     */
    public function renderDetails(string $locale): string
    {
        $en = 'en' === $locale;
        $blocks = [];
        foreach ($this->offers->findAllOrdered() as $offer) {
            if ($offer->isPublished()) {
                $blocks[] = $this->details($offer, $en);
            }
        }

        return [] === $blocks ? '(aucune offre publiée pour l\'instant)' : implode("\n\n", $blocks);
    }

    private function details(Offer $offer, bool $en): string
    {
        // Nettoyer AVANT de choisir : une traduction vide une fois nettoyée
        // (« () », espaces) retombe sur le français au lieu d'effacer le champ.
        $pick = static function (?string $translated, string $fallback) use ($en): string {
            $t = $en ? self::oneLine((string) $translated) : '';

            return '' !== $t ? $t : self::oneLine($fallback);
        };
        $pickList = static function (mixed $translated, mixed $fallback) use ($en): string {
            $t = $en ? self::joinItems($translated) : '';

            return '' !== $t ? $t : self::joinItems($fallback);
        };
        $pickBlocks = static function (mixed $translated, mixed $fallback, callable $render) use ($en): string {
            $t = $en ? $render($translated) : '';

            return '' !== $t ? $t : $render($fallback);
        };

        $fields = [
            $en ? 'Offer' : 'Offre' => $pick($offer->getTitleEn(), $offer->getTitle()),
            $en ? 'Link' : 'Lien' => '/offres/'.$offer->getSlug(),
            $en ? 'For whom' : 'Pour qui' => $pick($offer->getAudienceEn(), $offer->getAudience()),
            $en ? 'Problem solved' : 'Problème résolu' => $pick($offer->getPainEn(), $offer->getPain()),
            $en ? 'Promise' : 'Promesse' => trim($pick($offer->getHeadlineEn(), $offer->getHeadline()).' '.$pick($offer->getLeadTextEn(), $offer->getLeadText())),
            $en ? 'Included' : 'Inclus' => $pickList($offer->getIncludesEn(), $offer->getIncludes()),
            'Pack' => $pickBlocks($offer->getPackEn(), $offer->getPack(), self::packText(...)),
            $en ? 'Before' : 'Avant' => $pickList($offer->getBeforeItemsEn(), $offer->getBeforeItems()),
            $en ? 'After' : 'Après' => $pickList($offer->getAfterItemsEn(), $offer->getAfterItems()),
            $en ? 'Schedule' : 'Calendrier' => $pickBlocks($offer->getCalendarEn(), $offer->getCalendar(), self::calendarText(...)),
            $en ? 'Client provides' : 'Le client fournit' => $pick($offer->getClientNeedsEn(), $offer->getClientNeeds()),
        ];

        $lines = [];
        foreach ($fields as $label => $value) {
            if ('' !== trim($value)) {
                $lines[] = $label.' : '.$value;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Liste de chaînes → « a ; b ». Tolérante : un ÉLÉMENT qui n'est pas une
     * chaîne (JSON modifié hors back-office, migration) est ignoré. Une colonne
     * qui n'est pas un tableau échoue plus tôt, à l'hydratation Doctrine
     * (propriété typée `array`) : hors de portée de ce service.
     */
    private static function joinItems(mixed $items): string
    {
        if (!\is_array($items)) {
            return '';
        }
        $out = [];
        foreach ($items as $item) {
            $text = \is_string($item) ? self::oneLine($item) : '';
            if ('' !== $text) {
                $out[] = $text;
            }
        }

        return implode(' ; ', $out);
    }

    /** Bloc qui n'est pas un tableau : `?? null` le lit comme vide, il est ignoré. */
    private static function packText(mixed $pack): string
    {
        $out = [];
        foreach (\is_array($pack) ? $pack : [] as $block) {
            $title = \is_string($block['title'] ?? null) ? self::oneLine($block['title']) : '';
            $items = self::joinItems($block['items'] ?? null);
            if ('' !== $title || '' !== $items) {
                $out[] = trim($title.' : '.$items, ' :');
            }
        }

        return implode(' ; ', $out);
    }

    private static function calendarText(mixed $calendar): string
    {
        $out = [];
        foreach (\is_array($calendar) ? $calendar : [] as $step) {
            $text = trim((\is_string($step['day'] ?? null) ? self::oneLine($step['day']) : '').' '.(\is_string($step['text'] ?? null) ? self::oneLine($step['text']) : ''));
            if ('' !== $text) {
                $out[] = $text;
            }
        }

        return implode(' ; ', $out);
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
        return trim((string) preg_replace('/[\s\[\]()|<>＜＞‹›〈〉]+/u', ' ', $text));
    }
}
