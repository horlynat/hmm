<?php

namespace App\Form;

use App\Entity\Testimonial;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

class TestimonialType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('author', TextType::class, [
                'label' => 'Auteur',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Nom de la personne',
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Témoignage',
                // Testimonial::$content est un `string` non nullable — cf.
                // ArticleType::content pour le pourquoi de cette option.
                'empty_data' => '',
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'Contenu du témoignage (10 à 255 caractères)',
                ],
            ])
            ->add('contentEn', TextareaType::class, [
                'label' => 'Témoignage (anglais)',
                'required' => false,
                'help' => 'Optionnel — reprend le témoignage français si laissé vide.',
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'English translation of the testimonial',
                ],
            ])
            ->add('rating', ChoiceType::class, [
                'label' => 'Note',
                'required' => false,
                'placeholder' => 'Aucune note',
                'choices' => [
                    '0 ★' => '0',
                    '1 ★' => '1',
                    '2 ★' => '2',
                    '3 ★' => '3',
                    '4 ★' => '4',
                    '5 ★' => '5',
                ],
            ])
            ->add('media', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Photo ou justificatif',
                'multiple' => true,
                'constraints' => [
                    new All(
                        constraints: [
                            new File(
                                maxSize: '5M',
                                mimeTypes: ['image/jpeg', 'image/png', 'application/pdf'],
                                mimeTypesMessage: 'Formats autorisés : JPG, PNG, PDF.'
                            ),
                        ]
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Testimonial::class,
        ]);
    }
}
