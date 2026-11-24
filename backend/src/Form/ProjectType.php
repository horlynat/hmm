<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Skill;
use App\Enum\BillingTypeEnum;
use App\Enum\ProjectPriorityEnum;
use App\Repository\OfferRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;

class ProjectType extends AbstractType
{
    public function __construct(private readonly OfferRepository $offerRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du projet',
                // Project::$title est un `string` non nullable — cf.
                // ArticleType::content pour le pourquoi de cette option.
                'empty_data' => '',
            ])
            ->add('titleEn', TextType::class, [
                'label' => 'Titre du projet (anglais)',
                'required' => false,
                'help' => 'Optionnel — reprend le titre français si laissé vide.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                // Project::$description est un `string` non nullable — si le
                // champ venait à être absent de la soumission (ex. navigateur
                // en JS désactivé face à l'éditeur riche), Symfony le
                // traiterait sinon comme "vidé" (null) et ferait planter le
                // mapping avant même la validation. '' reste une valeur
                // licite, rejetée ensuite proprement par la contrainte
                // NotBlank de l'entité.
                'empty_data' => '',
            ])
            ->add('descriptionEn', TextareaType::class, [
                'label' => 'Description (anglais)',
                'required' => false,
                'help' => 'Optionnel — reprend la description française si laissé vide.',
            ])
            ->add('link', TextType::class, [
                'label' => 'Lien',
                'required' => false,
            ])
            ->add('budget', MoneyType::class, [
                'label' => 'Budget alloué',
                'currency' => 'EUR',
                'scale' => 2,
                'required' => false,
                'empty_data' => '0.00',
                'help' => 'Montant total alloué au projet. Doit être > 0 pour pouvoir enregistrer des dépenses.',
            ])

            ->add('skills', EntityType::class, [
                'class' => Skill::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => true, // ⚠️ cohérent avec la contrainte Count(min=1)
                'label' => 'Compétences',
                'attr' => [
                    'class' => 'select2' // exemple si tu utilises Select2 pour un rendu amélioré
                ],
            ])
            ->add('media', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Images et documents',
                'multiple' => true, // Permet de sélectionner plusieurs fichiers
                'constraints' => [
                    new All(
                        constraints: [
                            new File(
                                maxSize: '5M', // Taille max 5 Mo
                                // SVG accepté ici : upload réservé à ROLE_ADMIN
                                // (ProjectVoter::EDIT), même niveau de confiance
                                // que app.media_uploader.branding — cf. son
                                // commentaire dans services.yaml pour le risque
                                // résiduel assumé.
                                mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'application/pdf'],
                                mimeTypesMessage: 'Formats autorisés : JPG, PNG, WebP, SVG, PDF.'
                            )
                        ]
                    )
                ],
            ]);

        // Contenu vitrine (App\Entity\ProjectInfo) : présent uniquement à la
        // création, dans l'étape dédiée de l'assistant. Champs non mappés (pas
        // sur Project) — parsés et attachés à un ProjectInfo dans le contrôleur.
        if ($options['include_showcase']) {
            $builder
                ->add('role', TextType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Votre rôle exact sur ce projet',
                ])
                ->add('roleEn', TextType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Votre rôle exact sur ce projet (anglais)',
                ])
                ->add('objectives', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Objectifs du projet',
                    'help' => 'Un objectif par ligne (2-3 suffisent).',
                ])
                ->add('objectivesEn', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Objectifs du projet (anglais)',
                    'help' => 'Optionnel — un objectif par ligne, même ordre que la version française.',
                ])
                ->add('techStack', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Stack technique',
                    'help' => 'Une techno par ligne, format : Technologie | Pourquoi ce choix',
                ])
                ->add('techStackEn', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Stack technique (anglais)',
                    'help' => 'Optionnel — même format, même ordre que la version française.',
                ])
                ->add('challenges', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Défis rencontrés & solutions',
                    'help' => 'Un par ligne, format : Défi rencontré | Solution apportée',
                ])
                ->add('challengesEn', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Défis rencontrés & solutions (anglais)',
                    'help' => 'Optionnel — même format, même ordre que la version française.',
                ])
                ->add('results', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Résultats concrets',
                    'help' => 'Un par ligne, format : Libellé | Valeur',
                ])
                ->add('resultsEn', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Résultats concrets (anglais)',
                    'help' => 'Optionnel — même format, même ordre que la version française.',
                ])
                ->add('result', TextType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Résultat en une phrase',
                    'help' => "Affiché sous le titre de l'étude de cas. Ex. : Une société opérationnelle dès son lancement, sur un outil unique.",
                    'constraints' => [new Length(max: 255)],
                ])
                ->add('resultEn', TextType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Résultat en une phrase (anglais)',
                    'help' => 'Optionnel — même format, même ordre que la version française.',
                    'constraints' => [new Length(max: 255)],
                ])
                ->add('brief', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Le projet en bref (3 temps)',
                    'help' => 'Trois lignes au plus, format : Titre | Texte — ex. : La situation | Une société à lancer, sans outil numérique.',
                    'constraints' => [new Length(max: 4000)],
                ])
                ->add('briefEn', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Le projet en bref (3 temps) (anglais)',
                    'help' => 'Optionnel — même format, même ordre que la version française.',
                    'constraints' => [new Length(max: 4000)],
                ])
                ->add('context', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Le point de départ — contexte',
                    'help' => 'Premier paragraphe de « Le problème à résoudre ».',
                    'constraints' => [new Length(max: 2000)],
                ])
                ->add('contextEn', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Le point de départ — contexte (anglais)',
                    'help' => 'Optionnel — même format, même ordre que la version française.',
                    'constraints' => [new Length(max: 2000)],
                ])
                ->add('stake', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Le point de départ — défi',
                    'help' => 'Second paragraphe de « Le problème à résoudre ».',
                    'constraints' => [new Length(max: 2000)],
                ])
                ->add('stakeEn', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Le point de départ — défi (anglais)',
                    'help' => 'Optionnel — même format, même ordre que la version française.',
                    'constraints' => [new Length(max: 2000)],
                ])
                ->add('steps', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Démarche (étapes)',
                    'help' => 'Une étape par ligne, format : Étape | Ce qui a été fait — ex. : Cadrage | Lister les publics et leurs besoins.',
                    'constraints' => [new Length(max: 4000)],
                ])
                ->add('stepsEn', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Démarche (étapes) (anglais)',
                    'help' => 'Optionnel — même format, même ordre que la version française.',
                    'constraints' => [new Length(max: 4000)],
                ])
                ->add('solution', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Ce qui a été livré — synthèse',
                    'help' => 'Paragraphe de « Ce qui a été livré ».',
                    'constraints' => [new Length(max: 2000)],
                ])
                ->add('solutionEn', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Ce qui a été livré — synthèse (anglais)',
                    'help' => 'Optionnel — même format, même ordre que la version française.',
                    'constraints' => [new Length(max: 2000)],
                ])
                ->add('deliverables', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Livrables',
                    'help' => 'Un livrable par ligne.',
                    'constraints' => [new Length(max: 4000)],
                ])
                ->add('deliverablesEn', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Livrables (anglais)',
                    'help' => 'Optionnel — même format, même ordre que la version française.',
                    'constraints' => [new Length(max: 4000)],
                ])
                ->add('galleryCaptions', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Légendes de la galerie',
                    'help' => "Une légende par ligne, dans l'ordre des images du projet. Sans image correspondante, l'emplacement « Capture réelle à venir » est affiché.",
                    'constraints' => [new Length(max: 4000)],
                ])
                ->add('galleryCaptionsEn', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Légendes de la galerie (anglais)',
                    'help' => 'Optionnel — même format, même ordre que la version française.',
                    'constraints' => [new Length(max: 4000)],
                ])
                ->add('discreet', CheckboxType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Projet discret',
                    'help' => "Affiche la note « Pourquoi certains détails ne sont pas publiés » (architecture et protections non décrites publiquement).",
                ])
                ->add('relatedOfferSlug', ChoiceType::class, [
                    'mapped' => false,
                    'required' => false,
                    'placeholder' => 'Aucune',
                    'label' => 'Offre « Vous avez un besoin semblable ? »',
                    'help' => "Offre proposée en fin d'étude de cas, si elle est publiée.",
                    'choices' => $this->offerChoices(),
                ])
                ->add('repoUrl', TextType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Dépôt de code public (optionnel)',
                ])
                // Image de couverture réelle de la fiche vitrine (ProjectInfo::coverImage) —
                // distincte de `media` (galerie générique) : c'est cette image précise qui
                // sert de hero visuel sur /realisations/{slug}. Non mappée : gérée à part
                // en contrôleur, comme le reste du contenu vitrine, pour créer/mettre à
                // jour le Media associé (cf. syncProjectInfoFromForm()).
                ->add('coverImage', FileType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Image de couverture',
                    'help' => "L'image mise en avant en haut de la fiche publique du projet.",
                    'constraints' => [
                        new File(
                            maxSize: '5M',
                            mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
                            mimeTypesMessage: 'Formats autorisés : JPG, PNG, WebP, SVG.'
                        ),
                    ],
                ])
                ->add('removeCoverImage', CheckboxType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => "Retirer l'image de couverture actuelle",
                ]);
        }

        // Paramètres & planning : présents à la création (config complète du projet).
        // En édition, ces champs sont gérés en inline sur la page de lecture pour
        // ne pas surcharger ce formulaire.
        if ($options['include_planning']) {
            $builder
                ->add('priority', EnumType::class, [
                    'class' => ProjectPriorityEnum::class,
                    'choice_label' => fn (ProjectPriorityEnum $p) => $p->getLabel(),
                    'required' => false,
                    'placeholder' => '— Non définie —',
                    'label' => 'Priorité',
                ])
                ->add('billingType', EnumType::class, [
                    'class' => BillingTypeEnum::class,
                    'choice_label' => fn (BillingTypeEnum $b) => $b->getLabel(),
                    'required' => false,
                    'placeholder' => '— Non défini —',
                    'label' => 'Type de facturation',
                ])
                ->add('startedAt', DateType::class, [
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'required' => false,
                    'label' => 'Date de démarrage',
                ])
                ->add('deadline', DateType::class, [
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'required' => false,
                    'label' => 'Échéance',
                ]);
        }
    }

    /** @return array<string, string> libellé => slug, dans l'ordre du catalogue */
    private function offerChoices(): array
    {
        $choices = [];
        // Offres publiées seulement : le slug choisi est exposé par l'API
        // publique, il ne doit jamais révéler une offre en préparation.
        foreach ($this->offerRepository->findBy(['published' => true], ['position' => 'ASC']) as $offer) {
            $label = sprintf('%s — %s', $offer->getTitle(), $offer->getSlug());
            $choices[$label] = $offer->getSlug();
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'include_planning' => false,
            'include_showcase' => false,
        ]);
        $resolver->setAllowedTypes('include_planning', 'bool');
        $resolver->setAllowedTypes('include_showcase', 'bool');
    }
}