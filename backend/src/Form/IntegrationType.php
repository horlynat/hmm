<?php

namespace App\Form;

use App\Entity\Integration;
use App\Enum\IntegrationTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * apiKey n'est pas mappée directement sur l'entité : le contrôleur la chiffre
 * via SecretEncryptor avant de l'assigner à Integration::apiKeyEncrypted, et
 * ne l'écrase que si une nouvelle valeur non vide a été saisie (cf.
 * AdminIntegrationController).
 */
class IntegrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EnumType::class, [
                'class' => IntegrationTypeEnum::class,
                'label' => 'Type',
                'choice_label' => static fn (IntegrationTypeEnum $type): string => $type->getLabel(),
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Ex: Alertes équipe Slack'],
            ])
            ->add('webhookUrl', UrlType::class, [
                'label' => 'URL du webhook (Slack)',
                'required' => false,
                'attr' => ['placeholder' => 'https://hooks.slack.com/services/...'],
            ])
            ->add('apiKey', PasswordType::class, [
                'label' => 'Clé API / Token',
                'mapped' => false,
                'required' => false,
                'always_empty' => true,
                'attr' => ['placeholder' => '•••••••• (laisser vide pour conserver)', 'autocomplete' => 'new-password'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Intégration active',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Integration::class,
        ]);
    }
}
