<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Skill;
use App\Enum\BillingTypeEnum;
use App\Enum\ProjectPriorityEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
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

class ProjectType extends AbstractType
{
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
                                mimeTypes: ['image/jpeg', 'image/png', 'application/pdf'],
                                mimeTypesMessage: 'Formats autorisés : JPG, PNG, PDF.'
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
                ->add('repoUrl', TextType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Dépôt de code public (optionnel)',
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