<?php

namespace App\Form;

use App\Entity\AiAssistantEntry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AiAssistantEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $options['data'];

        $builder
            ->add('chipLabel', TextType::class, [
                'label' => 'Libellé de la suggestion',
                'attr' => ['placeholder' => 'Ex : Qui est Horlynat ?'],
            ])
            ->add('chipLabelEn', TextType::class, [
                'label' => 'Libellé de la suggestion (anglais)',
                'required' => false,
            ])
            ->add('keywords', TextareaType::class, [
                'mapped' => false,
                'label' => 'Mots-clés déclencheurs',
                'help' => 'Un mot-clé par ligne (sous-chaîne, insensible à la casse). Ex : qui / présent / present',
                'data' => implode("\n", $data?->getKeywords() ?? []),
            ])
            ->add('keywordsEn', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Mots-clés déclencheurs (anglais)',
                'help' => 'Un mot-clé par ligne.',
                'data' => implode("\n", $data?->getKeywordsEn() ?? []),
            ])
            ->add('answer', TextareaType::class, ['label' => 'Réponse'])
            ->add('answerEn', TextareaType::class, ['label' => 'Réponse (anglais)', 'required' => false])
            ->add('sortOrder', IntegerType::class, ['label' => 'Ordre d\'affichage'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AiAssistantEntry::class,
        ]);
    }
}
