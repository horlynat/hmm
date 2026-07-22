<?php

namespace App\Form;

use App\Entity\SystemSetting;
use App\Enum\ThemeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class SystemSettingType extends AbstractType
{
    private const LOCALE_CHOICES = [
        'Français' => 'fr',
        'English' => 'en',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('siteName', TextType::class, [
                'label' => 'Nom du site',
                'attr' => ['placeholder' => 'Ex: Portfolio Horlynat'],
            ])
            ->add('logoFile', FileType::class, [
                'label' => 'Logo (laisser vide pour conserver l\'actuel)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'],
                        mimeTypesMessage: 'Formats autorisés : JPG, PNG, SVG, WEBP.',
                    ),
                ],
            ])
            ->add('primaryColor', ColorType::class, [
                'label' => 'Couleur principale',
            ])
            ->add('theme', EnumType::class, [
                'class' => ThemeEnum::class,
                'label' => 'Thème',
                'choice_label' => static fn (ThemeEnum $theme): string => $theme->getLabel(),
            ])
            ->add('defaultLocale', ChoiceType::class, [
                'label' => 'Langue par défaut',
                'choices' => self::LOCALE_CHOICES,
            ])
            ->add('availableLocales', ChoiceType::class, [
                'label' => 'Langues disponibles',
                'choices' => self::LOCALE_CHOICES,
                'multiple' => true,
                'expanded' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SystemSetting::class,
        ]);
    }
}
