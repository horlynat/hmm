<?php

namespace App\Form;

use App\Entity\HomeContent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HomeContentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // --- Hero ---
            ->add('heroEyebrow', TextType::class, ['label' => 'Accroche (au-dessus du titre)'])
            ->add('heroEyebrowEn', TextType::class, ['label' => 'Accroche (anglais)', 'required' => false, 'help' => 'Optionnel — reprend le français si laissé vide.'])
            ->add('heroTitle', TextType::class, ['label' => 'Titre principal'])
            ->add('heroTitleEn', TextType::class, ['label' => 'Titre principal (anglais)', 'required' => false])
            ->add('heroTitleAccent', TextType::class, ['label' => 'Titre principal — partie accentuée'])
            ->add('heroTitleAccentEn', TextType::class, ['label' => 'Titre principal — partie accentuée (anglais)', 'required' => false])
            ->add('heroSub', TextareaType::class, ['label' => 'Sous-titre'])
            ->add('heroSubEn', TextareaType::class, ['label' => 'Sous-titre (anglais)', 'required' => false])
            ->add('heroRoles', TextareaType::class, [
                'mapped' => false,
                'label' => 'Rôles défilants',
                'help' => 'Un rôle par ligne, ex : Développeur Full-Stack',
                'data' => implode("\n", $options['data']?->getHeroRoles() ?? []),
            ])
            ->add('heroRolesEn', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Rôles défilants (anglais)',
                'help' => 'Optionnel — un rôle par ligne, même ordre que la version française.',
                'data' => implode("\n", $options['data']?->getHeroRolesEn() ?? []),
            ])
            ->add('founderBadge', TextType::class, ['label' => 'Badge fondateur'])
            ->add('founderBadgeEn', TextType::class, ['label' => 'Badge fondateur (anglais)', 'required' => false])
            ->add('diagramCaption', TextType::class, ['label' => 'Légende du diagramme d\'architecture'])
            ->add('diagramCaptionEn', TextType::class, ['label' => 'Légende du diagramme (anglais)', 'required' => false])

            // --- Teaser "à propos" ---
            ->add('aboutTitle', TextType::class, ['label' => 'Teaser à propos — titre'])
            ->add('aboutTitleEn', TextType::class, ['label' => 'Teaser à propos — titre (anglais)', 'required' => false])
            ->add('aboutP1', TextareaType::class, ['label' => 'Teaser à propos — paragraphe 1'])
            ->add('aboutP1En', TextareaType::class, ['label' => 'Teaser à propos — paragraphe 1 (anglais)', 'required' => false])
            ->add('aboutP2', TextareaType::class, ['label' => 'Teaser à propos — paragraphe 2'])
            ->add('aboutP2En', TextareaType::class, ['label' => 'Teaser à propos — paragraphe 2 (anglais)', 'required' => false])
            ->add('aboutHighlightTitle', TextType::class, ['label' => 'Carte mise en avant — titre'])
            ->add('aboutHighlightTitleEn', TextType::class, ['label' => 'Carte mise en avant — titre (anglais)', 'required' => false])
            ->add('aboutHighlightDesc', TextareaType::class, ['label' => 'Carte mise en avant — description'])
            ->add('aboutHighlightDescEn', TextareaType::class, ['label' => 'Carte mise en avant — description (anglais)', 'required' => false])
            ->add('aboutVisionText', TextareaType::class, ['label' => 'Vision (texte court)'])
            ->add('aboutVisionTextEn', TextareaType::class, ['label' => 'Vision (anglais)', 'required' => false])
            ->add('aboutMissionText', TextareaType::class, ['label' => 'Mission (texte court)'])
            ->add('aboutMissionTextEn', TextareaType::class, ['label' => 'Mission (anglais)', 'required' => false])

            // --- Pitch freelance ---
            ->add('freelanceTitle', TextType::class, ['label' => 'Freelance — titre'])
            ->add('freelanceTitleEn', TextType::class, ['label' => 'Freelance — titre (anglais)', 'required' => false])
            ->add('freelanceLede', TextareaType::class, ['label' => 'Freelance — chapô'])
            ->add('freelanceLedeEn', TextareaType::class, ['label' => 'Freelance — chapô (anglais)', 'required' => false])
            ->add('freelancePoint1', TextType::class, ['label' => 'Freelance — argument 1'])
            ->add('freelancePoint1En', TextType::class, ['label' => 'Freelance — argument 1 (anglais)', 'required' => false])
            ->add('freelancePoint2', TextType::class, ['label' => 'Freelance — argument 2'])
            ->add('freelancePoint2En', TextType::class, ['label' => 'Freelance — argument 2 (anglais)', 'required' => false])
            ->add('freelancePoint3', TextType::class, ['label' => 'Freelance — argument 3'])
            ->add('freelancePoint3En', TextType::class, ['label' => 'Freelance — argument 3 (anglais)', 'required' => false])
            ->add('freelanceCardDesc', TextareaType::class, ['label' => 'Freelance — description de la carte d\'inscription'])
            ->add('freelanceCardDescEn', TextareaType::class, ['label' => 'Freelance — description de la carte (anglais)', 'required' => false])

            // --- Appel à l'action final ---
            ->add('contactCtaTitle', TextType::class, ['label' => 'Appel à l\'action — titre'])
            ->add('contactCtaTitleEn', TextType::class, ['label' => 'Appel à l\'action — titre (anglais)', 'required' => false])
            ->add('contactCtaSub', TextareaType::class, ['label' => 'Appel à l\'action — texte'])
            ->add('contactCtaSubEn', TextareaType::class, ['label' => 'Appel à l\'action — texte (anglais)', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HomeContent::class,
        ]);
    }
}
