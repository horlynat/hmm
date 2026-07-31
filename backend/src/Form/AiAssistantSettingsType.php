<?php

namespace App\Form;

use App\Entity\AiAssistantSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AiAssistantSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('greeting', TextareaType::class, ['label' => 'Message d\'accueil'])
            ->add('greetingEn', TextareaType::class, ['label' => 'Message d\'accueil (anglais)', 'required' => false])
            ->add('fallback', TextareaType::class, ['label' => 'Réponse par défaut (question non reconnue)'])
            ->add('fallbackEn', TextareaType::class, ['label' => 'Réponse par défaut (anglais)', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AiAssistantSettings::class,
        ]);
    }
}
