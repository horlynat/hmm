<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Skill;
use App\Entity\SkillCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SkillType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la compétence',
                'attr' => [
                    'placeholder' => 'Ex: Symfony, React, Docker...',
                ],
            ])
            ->add('level', IntegerType::class, [
                'label' => 'Niveau',
                'attr' => [
                    'min' => 1,
                    'max' => 10,
                    'placeholder' => 'Ex: 5',
                ],
            ])
            ->add('projects', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'title', // plus lisible que l’ID
                'multiple' => true,
                'label' => 'Projets associés',
                'required' => false,
            ])
            ->add('skillCategory', EntityType::class, [
                'class' => SkillCategory::class,
                'choice_label' => 'name', // plus lisible que l’ID
                'label' => 'Catégorie',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Skill::class,
        ]);
    }
}
