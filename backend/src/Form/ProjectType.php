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
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
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
        ]);
        $resolver->setAllowedTypes('include_planning', 'bool');
    }
}