<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'attr' => ['autocomplete' => 'new-password', 'autofocus' => true],
                'constraints' => [
                    new NotBlank(message: 'Veuillez entrer un mot de passe'),
                    new Length(
                        min: 8,
                        minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
                        max: 4096,
                    ),
                    new PasswordStrength(
                        minScore: PasswordStrength::STRENGTH_MEDIUM,
                        message: 'Votre mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.',
                    ),
                ],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => 'Confirmer le mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez confirmer le mot de passe'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_token_id' => 'reset_password',
        ]);
    }
}
