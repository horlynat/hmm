<?php

namespace App\Service;

use App\Entity\AboutContent;
use App\Entity\HomeContent;
use App\Repository\AboutContentRepository;
use App\Repository\HomeContentRepository;

/**
 * Présentation de Horlynat rédigée par lui-même (pages À propos et Accueil,
 * back-office), transmise MOT POUR MOT à l'assistant du site.
 *
 * Pourquoi pas le pipeline d'ingestion (résumé Gemini de 5 à 8 phrases) :
 * - aucune perte — un résumé effacerait chiffres et nuances (« 8 ans dans
 *   l'assurance, dont 5 comme responsable… », « taux de renouvellement
 *   au-dessus de 80 % ») ;
 * - aucun intermédiaire IA entre ce qu'il a écrit et ce que l'assistant lit ;
 * - toujours à jour : lu à chaque question, sans réingestion.
 *
 * Avant ce service, l'assistant ne lisait ni l'une ni l'autre page (seuls
 * Project, Article, Experience et SkillCategory étaient ingérés) : il ne
 * connaissait pas le parcours de son propriétaire (constat 2026-11-25).
 *
 * Seuls les champs AFFICHÉS publiquement sur ces pages sont repris (rien
 * d'interne : ni photo, ni horodatage, ni auteur de modification). Texte
 * déterministe (ordre fixe) : le system prompt reste identique d'un appel à
 * l'autre et profite du cache de prompt Claude.
 */
final class AiAssistantOwnerProfile
{
    public function __construct(
        private readonly AboutContentRepository $about,
        private readonly HomeContentRepository $home,
    ) {
    }

    public function render(string $locale): string
    {
        $en = 'en' === $locale;
        $a = $this->about->getContent();
        $h = $this->home->getContent();

        $sections = [
            $this->section($en ? 'Identity' : 'Identité', [
                $en ? 'Name' : 'Nom' => $a->getProfileName(),
                $en ? 'Role' : 'Rôle' => $this->pick($en, $a->getProfileRoleEn(), $a->getProfileRole()),
                $en ? 'Also' : 'Également' => $this->pick($en, $a->getProfileAlsoEn(), $a->getProfileAlso()),
                $en ? 'Location' : 'Localisation' => $this->pick($en, $a->getProfileLocationEn(), $a->getProfileLocation()),
                $en ? 'Work mode' : 'Mode de travail' => $this->pick($en, $a->getProfileWorkModeEn(), $a->getProfileWorkMode()),
                $en ? 'Availability' : 'Disponibilité' => $this->pick($en, $a->getProfileAvailabilityEn(), $a->getProfileAvailability()),
                $en ? 'Languages' : 'Langues' => $this->pick($en, $a->getProfileLanguagesEn(), $a->getProfileLanguages()),
                $en ? 'Roles' : 'Rôles' => $this->pickList($en, $h->getHeroRolesEn(), $h->getHeroRoles()),
                $en ? 'Headline' : 'Accroche' => trim($this->pick($en, $a->getHeroEyebrowEn(), $a->getHeroEyebrow()).' — '.$this->pick($en, $a->getHeroTitleEn(), $a->getHeroTitle()).' '.$this->pick($en, $a->getHeroTitleAccentEn(), $a->getHeroTitleAccent()), ' —'),
                $en ? 'In short' : 'En bref' => $this->pick($en, $a->getHeroSubEn(), $a->getHeroSub()),
            ]),
            $this->section($en ? 'Background' : 'Parcours', [
                $en ? 'Title' : 'Titre' => $this->pick($en, $a->getBioTitleEn(), $a->getBioTitle()),
                '1' => $this->pick($en, $a->getBioP1En(), $a->getBioP1()),
                '2' => $this->pick($en, $a->getBioP2En(), $a->getBioP2()),
                '3' => $this->pick($en, $a->getBioP3En(), $a->getBioP3()),
                $en ? 'Milestones' : 'Repères' => $this->stats($en ? $a->getJourneyStatsEn() : null, $a->getJourneyStats()),
            ]),
            $this->section($en ? 'Vision of the craft' : 'Vision du métier', [
                $en ? 'Principle' : 'Principe' => $this->pick($en, $a->getVisionLedeEn(), $a->getVisionLede()),
                $en ? 'Today' : 'Aujourd\'hui' => $this->pick($en, $a->getVisionTodayTextEn(), $a->getVisionTodayText()),
                $en ? 'His approach' : 'Son approche' => $this->pick($en, $a->getVisionTomorrowTextEn(), $a->getVisionTomorrowText()),
                'Vision' => $this->pick($en, $a->getVisionPillarEn(), $a->getVisionPillar()),
                'Mission' => $this->pick($en, $a->getMissionPillarEn(), $a->getMissionPillar()),
                $en ? 'Ambition' : 'Ambition' => $this->pick($en, $a->getAmbitionPillarEn(), $a->getAmbitionPillar()),
                $en ? 'Mid-term horizon' : 'Horizon à moyen terme' => $this->pick($en, $a->getHorizonMidTextEn(), $a->getHorizonMidText()),
                $en ? 'Learning' : 'Apprentissage' => $this->pick($en, $a->getLearningTextEn(), $a->getLearningText()),
            ]),
            $this->section($en ? 'Why work with him' : 'Pourquoi travailler avec lui', [
                '1' => $this->pair($this->pick($en, $a->getWhy1TitleEn(), $a->getWhy1Title()), $this->pick($en, $a->getWhy1DescEn(), $a->getWhy1Desc())),
                '2' => $this->pair($this->pick($en, $a->getWhy2TitleEn(), $a->getWhy2Title()), $this->pick($en, $a->getWhy2DescEn(), $a->getWhy2Desc())),
                '3' => $this->pair($this->pick($en, $a->getWhy3TitleEn(), $a->getWhy3Title()), $this->pick($en, $a->getWhy3DescEn(), $a->getWhy3Desc())),
                '4' => $this->pair($this->pick($en, $a->getWhy4TitleEn(), $a->getWhy4Title()), $this->pick($en, $a->getWhy4DescEn(), $a->getWhy4Desc())),
                '5' => $this->pair($this->pick($en, $a->getWhy5TitleEn(), $a->getWhy5Title()), $this->pick($en, $a->getWhy5DescEn(), $a->getWhy5Desc())),
            ]),
            $this->section($en ? 'Presentation (home page)' : 'Présentation (accueil)', [
                $en ? 'Badge' : 'Badge' => $this->pick($en, $h->getFounderBadgeEn(), $h->getFounderBadge()),
                $en ? 'Title' : 'Titre' => $this->pick($en, $h->getAboutTitleEn(), $h->getAboutTitle()),
                '1' => $this->pick($en, $h->getAboutP1En(), $h->getAboutP1()),
                '2' => $this->pick($en, $h->getAboutP2En(), $h->getAboutP2()),
                $en ? 'Highlight' : 'Point fort' => $this->pair($this->pick($en, $h->getAboutHighlightTitleEn(), $h->getAboutHighlightTitle()), $this->pick($en, $h->getAboutHighlightDescEn(), $h->getAboutHighlightDesc())),
                'Vision' => $this->pick($en, $h->getAboutVisionTextEn(), $h->getAboutVisionText()),
                'Mission' => $this->pick($en, $h->getAboutMissionTextEn(), $h->getAboutMissionText()),
            ]),
            $this->section($en ? 'Beyond work' : 'Au-delà du travail', [
                $en ? 'Languages' : 'Langues' => $this->pickList($en, $a->getBeyondLanguagesEn(), $a->getBeyondLanguages()),
                $en ? 'Interests' : 'Centres d\'intérêt' => $this->pickList($en, $a->getBeyondInterestsEn(), $a->getBeyondInterests()),
            ]),
        ];

        $sections = array_values(array_filter($sections, static fn (string $s): bool => '' !== $s));

        return [] === $sections
            ? ($en ? '(profile not filled in yet)' : '(profil pas encore renseigné)')
            : implode("\n\n", $sections);
    }

    /** @param array<int|string, string> $fields */
    private function section(string $title, array $fields): string
    {
        $lines = [];
        foreach ($fields as $label => $value) {
            $value = self::clean($value);
            if ('' !== $value) {
                $lines[] = \is_int($label) || ctype_digit((string) $label) ? '- '.$value : '- '.$label.' : '.$value;
            }
        }

        return [] === $lines ? '' : $title."\n".implode("\n", $lines);
    }

    /** Traduction nettoyée d'abord : vide une fois nettoyée → repli sur le français. */
    private function pick(bool $en, ?string $translated, ?string $fallback): string
    {
        $t = $en ? self::clean((string) $translated) : '';

        return '' !== $t ? $t : self::clean((string) $fallback);
    }

    private function pickList(bool $en, mixed $translated, mixed $fallback): string
    {
        $t = $en ? self::join($translated) : '';

        return '' !== $t ? $t : self::join($fallback);
    }

    private function stats(mixed $translated, mixed $fallback): string
    {
        $render = static function (mixed $stats): string {
            $out = [];
            foreach (\is_array($stats) ? $stats : [] as $s) {
                $text = trim(self::clean(\is_string($s['value'] ?? null) ? $s['value'] : '').' '.self::clean(\is_string($s['label'] ?? null) ? $s['label'] : ''));
                if ('' !== $text) {
                    $out[] = $text;
                }
            }

            return implode(' ; ', $out);
        };
        $t = $render($translated);

        return '' !== $t ? $t : $render($fallback);
    }

    private function pair(string $title, string $desc): string
    {
        return trim('' !== $title && '' !== $desc ? $title.' : '.$desc : $title.$desc);
    }

    /** Liste de chaînes ; tout élément qui n'est pas une chaîne est ignoré. */
    private static function join(mixed $items): string
    {
        $out = [];
        foreach (\is_array($items) ? $items : [] as $item) {
            $text = \is_string($item) ? self::clean($item) : '';
            if ('' !== $text) {
                $out[] = $text;
            }
        }

        return implode(' ; ', $out);
    }

    /**
     * Une valeur = une ligne : saisie back-office, jamais de quoi refermer le
     * bloc <profil> (chevrons, y compris homoglyphes), forger un lien Markdown
     * (crochets) ni ajouter une ligne qui se ferait passer pour un autre champ.
     */
    private static function clean(string $text): string
    {
        return trim((string) preg_replace('/[\s\[\]<>＜＞‹›〈〉]+/u', ' ', $text));
    }
}
