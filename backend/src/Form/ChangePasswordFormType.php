<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;

/**
 * Changement de mot de passe EN CONNAISSANT DÉJÀ l'ancien (self-service,
 * P1 #7) — distinct de App\Form\ResetPasswordFormType, utilisé pour le
 * mot de passe OUBLIÉ (aucun ancien mot de passe à fournir, par nature).
 * Les utiliser l'un pour l'autre serait une régression de sécurité dans un
 * sens (routine sans re-authentification) ou une impasse fonctionnelle dans
 * l'autre (personne n'a de mot de passe oublié ET son ancien sous la main).
 */
class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'attr' => ['autocomplete' => 'current-password', 'autofocus' => true],
                'constraints' => [
                    new NotBlank(message: 'Veuillez entrer votre mot de passe actuel.'),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez entrer un mot de passe'),
                    new Length(
                        min: 8,
                        minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
                        max: 4096,
                    ),
                    // Contre-audit B3 : le hacheur plafonne à 4096 octets.
                    new Length(max: 4096, countUnit: Length::COUNT_BYTES, maxMessage: 'Le mot de passe est trop long.'),
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
            'csrf_token_id' => 'change_password',
        ]);
    }
}
