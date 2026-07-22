<?php
// src/Form/UserType.php

namespace App\Form;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class UserType extends AbstractType
{
    private const ROLE_LABELS = [
        'ROLE_USER' => 'Utilisateur',
        'ROLE_EDITOR' => 'Éditeur (collaborateur)',
        'ROLE_MODERATOR' => 'Modérateur',
        'ROLE_MANAGER' => 'Manager',
        'ROLE_ADMIN' => 'Administrateur',
        'ROLE_SUPER_ADMIN' => 'Super Administrateur',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $user */
        $user = $builder->getData();
        $isNewUser = null === $user->getId();

        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
            ])

            ->add('fullName', TextType::class, [
                'required' => false,
                'label' => 'Nom complet',
            ])

            ->add('phone', TextType::class, [
                'required' => false,
                'label' => 'Téléphone',
            ])

            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                // Obligatoire à la création (sinon le hash reste vide) ; optionnel à
                // l'édition ("laisser vide pour ne pas changer" — voir handlePassword()).
                'required' => $isNewUser,
                'label' => 'Nouveau mot de passe',
                'attr' => [
                    'placeholder' => 'Laisser vide pour ne pas changer',
                    'autocomplete' => 'new-password',
                ],
                // Length/Regex : Symfony ignore ces contraintes sur une valeur vide, donc
                // sans effet à l'édition tant que le champ n'est pas rempli.
                'constraints' => [
                    ...($isNewUser ? [new NotBlank(message: 'Veuillez entrer un mot de passe.')] : []),
                    new Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
                    new Regex(
                        pattern: '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).+$/',
                        message: 'Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et un caractère spécial.',
                    ),
                ],
            ])

            ->add('profileImageFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Image de profil',
            ])

            ->add('roles', ChoiceType::class, [
                // Restreint aux rôles que l'utilisateur connecté possède déjà
                // (via la hiérarchie) : un ROLE_ADMIN ne peut jamais s'auto-attribuer
                // ni attribuer à un tiers un rôle plus élevé que le sien (ex: SUPER_ADMIN).
                // Sur un ChoiceType "expanded" + "multiple", chaque choix devient sa
                // propre case à cocher : une valeur absente de cette liste ne peut pas
                // être injectée par une requête POST forgée, ce n'est pas qu'un masquage UI.
                'choices' => $this->getGrantableRoleChoices(),
                'multiple' => true,
                'expanded' => true,
                'label' => 'Rôles',
            ])

            ->add('isActive', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
            ])

            ->add('isVerified', CheckboxType::class, [
                'label' => 'Email vérifié',
                'required' => false,
            ])

            ->add('isTwoFactorEnabled', CheckboxType::class, [
                'label' => '2FA activé',
                'required' => false,
                // Lecture seule : voir AdminSecurityTwoFactorController pour la
                // désactivation forcée, et TwoFactorController pour l'activation
                // en libre-service (vérification d'un vrai code TOTP requise).
                'disabled' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }

    /** @return array<string, string> */
    private function getGrantableRoleChoices(): array
    {
        $currentUser = $this->security->getUser();
        $currentRoles = $currentUser instanceof User ? $currentUser->getRoles() : [];
        $grantableRoles = $this->roleHierarchy->getReachableRoleNames($currentRoles);

        $choices = [];
        foreach (self::ROLE_LABELS as $role => $label) {
            if (\in_array($role, $grantableRoles, true)) {
                $choices[$label] = $role;
            }
        }

        return $choices;
    }
}
