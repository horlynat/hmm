<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Tag;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TagType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du tag',
                'attr' => [
                    'placeholder' => 'Ex: Symfony, Sécurité, DevOps...',
                ],
            ])
            ->add('article', EntityType::class, [
                'class' => Article::class,
                'choice_label' => 'title', // plus lisible que l’ID
                'multiple' => true,
                'required' => false, // facultatif
                'label' => 'Articles associés',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tag::class,
        ]);
    }
}
