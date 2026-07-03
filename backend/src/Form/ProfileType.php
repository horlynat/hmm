<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Nom complet',
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
                ],
                'constraints' => [
                    new Length(
                        min: 2,
                        max: 255,
                        minMessage: 'Votre nom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Votre nom ne peut pas contenir plus de {{ limit }} caractères.'
                    ),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Nouveau mot de passe',
                'attr' => [
                    'placeholder' => 'Laisser vide pour ne pas changer',
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
                ],
                'constraints' => [
                    new Length(
                        min: 8,
                        minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.'
                    ),
                    new PasswordStrength(
                        minScore: PasswordStrength::STRENGTH_MEDIUM,
                        message: 'Votre mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.'
                    ),
                ],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Confirmer le mot de passe',
                'attr' => [
                    'placeholder' => 'Laisser vide pour ne pas changer',
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'choices' => [
                    'Administrateur' => 'ROLE_ADMIN',
                    'Utilisateur' => 'ROLE_USER',
                ],
                'multiple' => true,
                'expanded' => true,
                'attr' => [
                    'class' => 'flex items-center gap-4 text-gray-800 dark:text-gray-200',
                ],
            ])
            ->add('isVerified', CheckboxType::class, [
                'label' => 'Email vérifié',
                'required' => false,
                'attr' => [
                    'class' => 'h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-600 rounded focus:ring-indigo-500 bg-white dark:bg-gray-700',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
                'attr' => [
                    'class' => 'h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-600 rounded focus:ring-indigo-500 bg-white dark:bg-gray-700',
                ],
            ])
            ->add('isTwoFactorEnabled', CheckboxType::class, [
                'label' => '2FA activé',
                'required' => false,
                'attr' => [
                    'class' => 'h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-600 rounded focus:ring-indigo-500 bg-white dark:bg-gray-700',
                ],
            ])
            // ✅ Ajoutez les champs système en lecture seule
            ->add('lastIp', TextType::class, [
                'label' => 'Dernière IP',
                'disabled' => true,
                'attr' => [
                    'class' => 'block w-full text-sm bg-white dark:bg-gray-700 border-gray-200 dark:border-gray-600 rounded text-gray-800 dark:text-gray-200',
                    'readonly' => true,
                ],
            ])
            ->add('lastLocation', TextType::class, [
                'label' => 'Localisation',
                'disabled' => true,
                'attr' => [
                    'class' => 'block w-full text-sm bg-white dark:bg-gray-700 border-gray-200 dark:border-gray-600 rounded text-gray-800 dark:text-gray-200',
                    'readonly' => true,
                ],
            ])
            ->add('lastLoginAt', TextType::class, [
                'label' => 'Dernière connexion',
                'disabled' => true,
                'attr' => [
                    'class' => 'block w-full text-sm bg-white dark:bg-gray-700 border-gray-200 dark:border-gray-600 rounded text-gray-800 dark:text-gray-200',
                    'readonly' => true,
                ],
            ])
            ->add('lastDevice', TextType::class, [
                'label' => 'Dernier appareil',
                'disabled' => true,
                'attr' => [
                    'class' => 'block w-full text-sm bg-white dark:bg-gray-700 border-gray-200 dark:border-gray-600 rounded text-gray-800 dark:text-gray-200',
                    'readonly' => true,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}