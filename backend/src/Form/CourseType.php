<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseTypeEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du cours',
                // Course::$title est un `string` non nullable — cf.
                // ArticleType::content pour le pourquoi de cette option.
                'empty_data' => '',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Entrez le titre du cours'
                ],
            ])
            ->add('titleEn', TextType::class, [
                'label' => 'Titre du cours (anglais)',
                'required' => false,
                'help' => 'Optionnel — reprend le titre français si laissé vide.',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Enter the course title'
                ],
            ])
            ->add('institution', TextType::class, [
                'label' => 'Institution',
                'attr' => [
                    'maxlength' => 100,
                    'placeholder' => 'Nom de l’institution'
                ],
            ])
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de début',
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de fin',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'empty_data' => '',
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'Décrivez le contenu du cours (min. 10 caractères)'
                ],
            ])
            ->add('descriptionEn', TextareaType::class, [
                'label' => 'Description (anglais)',
                'required' => false,
                'help' => 'Optionnel — reprend la description française si laissée vide.',
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'Describe the course content in English'
                ],
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email', // ⚠️ plus parlant que l’ID
                'label' => 'Utilisateur associé',
                'placeholder' => 'Sélectionnez un utilisateur',
            ])
            ->add('type', EnumType::class, [
                'class' => CourseTypeEnum::class,
                'label' => 'Type',
                'choice_label' => static fn (CourseTypeEnum $type): string => $type->getLabel(),
                'help' => 'Détermine le regroupement affiché publiquement (diplômes / certifications / formations).',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
        ]);
    }
}
