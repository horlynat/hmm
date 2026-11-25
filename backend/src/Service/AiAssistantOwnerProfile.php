<?php

namespace App\Service;

use App\Entity\AboutContent;
use App\Entity\Course;
use App\Repository\AboutContentRepository;
use App\Enum\CourseTypeEnum;
use App\Repository\CourseRepository;
use Psr\Clock\ClockInterface;

/**
 * Présentation de Horlynat rédigée par lui-même (page À propos et ses
 * formations, back-office), transmise MOT POUR MOT à l'assistant du site.
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
 * Seuls les champs AFFICHÉS sur la page À propos sont repris (SiteAbout.tsx :
 * AboutContent + Course), donc relus et validés par le propriétaire — ni les
 * champs saisis au back-office mais affichés nulle part (localisation,
 * langues, mode de travail, « également », sur-titre, centres d'intérêt :
 * 2e contre-audit), ni rien d'interne (photo, horodatage, auteur). HomeContent n'est PAS repris : depuis la refonte,
 * l'accueil ne l'affiche plus (anciens champs non relus, doublons de la page
 * À propos qui finiraient par la contredire — contre-audit). Texte
 * déterministe (ordre fixe) : le system prompt reste identique d'un appel à
 * l'autre et profite du cache de prompt Claude.
 */
final class AiAssistantOwnerProfile
{
    public function __construct(
        private readonly AboutContentRepository $about,
        private readonly CourseRepository $courses,
        private readonly ClockInterface $clock,
    ) {
    }

    public function render(string $locale): string
    {
        $en = 'en' === $locale;
        // Lecture seule : jamais de création de ligne depuis la route publique du chat.
        $a = $this->about->findFirst() ?? new AboutContent();

        $sections = [
            $this->section($en ? 'Identity' : 'Identité', [
                $en ? 'Name' : 'Nom' => $a->getProfileName(),
                $en ? 'Role' : 'Rôle' => $this->pick($en, $a->getProfileRoleEn(), $a->getProfileRole()),
                $en ? 'Availability' : 'Disponibilité' => $this->pick($en, $a->getProfileAvailabilityEn(), $a->getProfileAvailability()),
                // Pas de trim(…, ' —') : trim() coupe octet par octet et corrompait l'UTF-8.
                $en ? 'Headline' : 'Accroche' => implode(' ', array_filter([$this->pick($en, $a->getHeroTitleEn(), $a->getHeroTitle()), $this->pick($en, $a->getHeroTitleAccentEn(), $a->getHeroTitleAccent())], static fn (string $v): bool => '' !== $v)),
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
                // Libellés de la page (aboutPage.horizon) : Aujourd'hui / D'ici trois ans / À long terme.
                $en ? 'Today' : 'Aujourd\'hui' => $this->pick($en, $a->getVisionTodayTextEn(), $a->getVisionTodayText()),
                $en ? 'Within three years' : 'D\'ici trois ans' => $this->pick($en, $a->getHorizonMidTextEn(), $a->getHorizonMidText()),
                $en ? 'In the long run' : 'À long terme' => $this->pick($en, $a->getVisionTomorrowTextEn(), $a->getVisionTomorrowText()),
                'Vision' => $this->pick($en, $a->getVisionPillarEn(), $a->getVisionPillar()),
                'Mission' => $this->pick($en, $a->getMissionPillarEn(), $a->getMissionPillar()),
                $en ? 'Ambition' : 'Ambition' => $this->pick($en, $a->getAmbitionPillarEn(), $a->getAmbitionPillar()),
                $en ? 'Learning' : 'Apprentissage' => $this->pick($en, $a->getLearningTextEn(), $a->getLearningText()),
            ]),
            $this->section($en ? 'Why work with him' : 'Pourquoi travailler avec lui', [
                '1' => $this->pair($this->pick($en, $a->getWhy1TitleEn(), $a->getWhy1Title()), $this->pick($en, $a->getWhy1DescEn(), $a->getWhy1Desc())),
                '2' => $this->pair($this->pick($en, $a->getWhy2TitleEn(), $a->getWhy2Title()), $this->pick($en, $a->getWhy2DescEn(), $a->getWhy2Desc())),
                '3' => $this->pair($this->pick($en, $a->getWhy3TitleEn(), $a->getWhy3Title()), $this->pick($en, $a->getWhy3DescEn(), $a->getWhy3Desc())),
                '4' => $this->pair($this->pick($en, $a->getWhy4TitleEn(), $a->getWhy4Title()), $this->pick($en, $a->getWhy4DescEn(), $a->getWhy4Desc())),
                '5' => $this->pair($this->pick($en, $a->getWhy5TitleEn(), $a->getWhy5Title()), $this->pick($en, $a->getWhy5DescEn(), $a->getWhy5Desc())),
            ]),
            $this->section($en ? 'Education and certifications' : 'Formation et certifications', $this->education($en)),
        ];

        $sections = array_values(array_filter($sections, static fn (string $s): bool => '' !== $s));

        return [] === $sections
            ? ($en ? '(profile not filled in yet)' : '(profil pas encore renseigné)')
            : implode("\n\n", $sections);
    }

    /**
     * Formations affichées sur la page À propos, de la plus récente à la plus
     * ancienne : « Diplôme — Licence de mathématiques, Université X (2010 – 2013) ».
     *
     * @return list<string>
     */
    private function education(bool $en): array
    {
        $out = [];
        foreach ($this->courses->findBy([], ['startDate' => 'DESC', 'id' => 'ASC']) as $course) {
            $out[] = $this->courseLine($course, $en);
        }

        return $out;
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }

    private function courseLine(Course $c, bool $en): string
    {
        $start = $c->getStartDate()->format('Y');
        $end = $c->getEndDate()->format('Y');
        // Comme la page (« Dès {mois année} · À venir ») : jamais un diplôme à venir
        // présenté comme obtenu.
        $years = $c->getStartDate() > $this->now()
            ? ($en ? 'starting ' : 'à partir de ').$c->getStartDate()->format('m/Y').($en ? ', upcoming' : ', à venir')
            : ($start === $end ? $start : $start.' – '.$end);
        $title = $this->pick($en, $c->getTitleEn(), $c->getTitle());
        $institution = self::clean($c->getInstitution());
        $label = $en ? match ($c->getType()) {
            CourseTypeEnum::DIPLOMA => 'Degree',
            CourseTypeEnum::CERTIFICATION => 'Certification',
            CourseTypeEnum::TRAINING => 'Training',
        } : $c->getType()->getLabel();
        $head = trim($label.' — '.$title.('' !== $institution ? ', '.$institution : ''));
        $desc = $this->pick($en, $c->getDescriptionEn(), $c->getDescription());

        return $head.' ('.$years.')'.('' !== $desc ? ' : '.$desc : '');
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

    /**
     * Une valeur = une ligne : saisie back-office, jamais de quoi refermer le
     * bloc <profil> (chevrons, y compris homoglyphes), forger un lien Markdown
     * (crochets, y compris pleine chasse) ni ajouter une ligne qui se ferait
     * passer pour un autre champ. Classes Unicode plutôt qu'une liste fixe :
     * tout séparateur (\p{Z}, U+2028…), caractère de contrôle (\p{Cc}) ou de
     * format invisible (\p{Cf} : U+200B, inversion de sens U+202E…).
     */
    private static function clean(string $text): string
    {
        return trim((string) preg_replace('/[\s\p{Z}\p{Cc}\p{Cf}\[\]<>＜＞﹤﹥‹›〈〉⟨⟩˂˃［］《》ᐸᐳ❬❭〈〉⧼⧽❰❱❮❯]+/u', ' ', $text));
    }
}
