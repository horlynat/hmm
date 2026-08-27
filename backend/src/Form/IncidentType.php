<?php

namespace App\Form;

use App\Entity\Incident;
use App\Enum\IncidentCategoryEnum;
use App\Enum\IncidentSeverityEnum;
use App\Enum\IncidentStatusEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IncidentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'empty_data' => '',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Ex: mysqldump absent de l\'image backend',
                ],
            ])
            ->add('category', EnumType::class, [
                'class' => IncidentCategoryEnum::class,
                'label' => 'Catégorie',
                'choice_label' => static fn (IncidentCategoryEnum $c): string => $c->getLabel(),
                'help' => 'Sert à repérer les récurrences — un même type d\'incident qui revient signale une cause racine non traitée.',
            ])
            ->add('severity', EnumType::class, [
                'class' => IncidentSeverityEnum::class,
                'label' => 'Gravité',
                'choice_label' => static fn (IncidentSeverityEnum $s): string => $s->getLabel(),
            ])
            ->add('status', EnumType::class, [
                'class' => IncidentStatusEnum::class,
                'label' => 'Statut',
                'choice_label' => static fn (IncidentStatusEnum $s): string => $s->getLabel(),
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Ce qui s\'est passé',
                'empty_data' => '',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Description factuelle des symptômes observés, sans interprétation.',
                ],
            ])
            ->add('rootCause', TextareaType::class, [
                'label' => 'Cause racine',
                'required' => false,
                'help' => 'Peut rester vide tant que l\'analyse n\'est pas faite.',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Pourquoi c\'est arrivé — laisser vide si pas encore identifié.',
                ],
            ])
            ->add('remediation', TextareaType::class, [
                'label' => 'Remédiation',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Ce qui a été fait, ou reste à faire, pour corriger/éviter la récidive.',
                ],
            ])
            ->add('relatedReference', TextType::class, [
                'label' => 'Référence liée',
                'required' => false,
                'help' => 'Numéro de PR, commit, ou chemin de doc (ex: horlynat/hmm#95, docs/incident-auth.md#2).',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'horlynat/hmm#95',
                ],
            ])
            ->add('detectedAt', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Détecté le',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Incident::class,
        ]);
    }
}
