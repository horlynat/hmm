<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Demande de changement d'e-mail self-service, en deux temps (P1 #7) : ce
 * formulaire ne fait QUE démarrer la demande (App\Entity\User::$pendingEmail)
 * — l'adresse n'est jamais écrite dans `email` avant confirmation cliquée
 * depuis la nouvelle adresse elle-même, cf. App\Controller\
 * ProfileEmailChangeController.
 */
class EmailChangeRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('newEmail', EmailType::class, [
                'label' => 'Nouvelle adresse e-mail',
                'mapped' => false,
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank(message: "Veuillez entrer une adresse e-mail."),
                    new Email(message: 'Veuillez entrer une adresse e-mail valide.'),
                    new Length(max: 180, maxMessage: "L'adresse e-mail ne peut pas dépasser {{ limit }} caractères."),
                ],
            ])
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez entrer votre mot de passe actuel.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_token_id' => 'email_change_request',
        ]);
    }
}
