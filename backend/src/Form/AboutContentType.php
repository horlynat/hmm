<?php

namespace App\Form;

use App\Entity\AboutContent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AboutContentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $options['data'];

        $builder
            // --- Hero ---
            ->add('heroEyebrow', TextType::class, ['label' => 'Accroche (au-dessus du titre)'])
            ->add('heroEyebrowEn', TextType::class, ['label' => 'Accroche (anglais)', 'required' => false])
            ->add('heroTitle', TextType::class, ['label' => 'Titre principal'])
            ->add('heroTitleEn', TextType::class, ['label' => 'Titre principal (anglais)', 'required' => false])
            ->add('heroTitleAccent', TextType::class, ['label' => 'Titre principal — partie accentuée'])
            ->add('heroTitleAccentEn', TextType::class, ['label' => 'Titre principal — partie accentuée (anglais)', 'required' => false])
            ->add('heroSub', TextareaType::class, ['label' => 'Sous-titre'])
            ->add('heroSubEn', TextareaType::class, ['label' => 'Sous-titre (anglais)', 'required' => false])

            // --- Carte profil ---
            ->add('profileName', TextType::class, ['label' => 'Nom affiché'])
            ->add('profileRole', TextType::class, ['label' => 'Intitulé de poste'])
            ->add('profileRoleEn', TextType::class, ['label' => 'Intitulé de poste (anglais)', 'required' => false])
            ->add('profileAvailability', TextType::class, ['label' => 'Disponibilité (valeur)', 'help' => 'Ex : Disponible, Complet, Sur liste d\'attente...'])
            ->add('profileAvailabilityEn', TextType::class, ['label' => 'Disponibilité (anglais)', 'required' => false])
            ->add('profileAlso', TextType::class, ['label' => 'Aussi (valeur)', 'help' => 'Ex : Cybersécurité · Assurance'])
            ->add('profileAlsoEn', TextType::class, ['label' => 'Aussi (anglais)', 'required' => false])
            ->add('profileLocation', TextType::class, ['label' => 'Localisation (valeur)'])
            ->add('profileLocationEn', TextType::class, ['label' => 'Localisation (anglais)', 'required' => false])
            ->add('profileWorkMode', TextType::class, ['label' => 'Mode de travail (valeur)'])
            ->add('profileWorkModeEn', TextType::class, ['label' => 'Mode de travail (anglais)', 'required' => false])
            ->add('profileLanguages', TextType::class, ['label' => 'Langues (valeur)', 'help' => 'Ex : Français · Anglais'])
            ->add('profileLanguagesEn', TextType::class, ['label' => 'Langues (anglais)', 'required' => false])

            // --- Bio ---
            ->add('bioTitle', TextType::class, ['label' => 'Bio — titre'])
            ->add('bioTitleEn', TextType::class, ['label' => 'Bio — titre (anglais)', 'required' => false])
            ->add('bioP1', TextareaType::class, ['label' => 'Bio — paragraphe 1'])
            ->add('bioP1En', TextareaType::class, ['label' => 'Bio — paragraphe 1 (anglais)', 'required' => false])
            ->add('bioP2', TextareaType::class, ['label' => 'Bio — paragraphe 2'])
            ->add('bioP2En', TextareaType::class, ['label' => 'Bio — paragraphe 2 (anglais)', 'required' => false])
            ->add('bioP3', TextareaType::class, ['label' => 'Bio — paragraphe 3'])
            ->add('bioP3En', TextareaType::class, ['label' => 'Bio — paragraphe 3 (anglais)', 'required' => false])

            // --- Vision ---
            ->add('visionTitle', TextType::class, ['label' => 'Vision — titre'])
            ->add('visionTitleEn', TextType::class, ['label' => 'Vision — titre (anglais)', 'required' => false])
            ->add('visionLede', TextareaType::class, ['label' => 'Vision — chapô'])
            ->add('visionLedeEn', TextareaType::class, ['label' => 'Vision — chapô (anglais)', 'required' => false])
            ->add('visionTodayText', TextareaType::class, ['label' => 'Vision — "Aujourd\'hui"'])
            ->add('visionTodayTextEn', TextareaType::class, ['label' => 'Vision — "Aujourd\'hui" (anglais)', 'required' => false])
            ->add('visionTomorrowText', TextareaType::class, ['label' => 'Vision — "Demain"'])
            ->add('visionTomorrowTextEn', TextareaType::class, ['label' => 'Vision — "Demain" (anglais)', 'required' => false])

            // --- Pourquoi moi ---
            ->add('why1Title', TextType::class, ['label' => 'Différenciateur 1 — titre'])
            ->add('why1TitleEn', TextType::class, ['label' => 'Différenciateur 1 — titre (anglais)', 'required' => false])
            ->add('why1Desc', TextareaType::class, ['label' => 'Différenciateur 1 — description'])
            ->add('why1DescEn', TextareaType::class, ['label' => 'Différenciateur 1 — description (anglais)', 'required' => false])
            ->add('why2Title', TextType::class, ['label' => 'Différenciateur 2 — titre'])
            ->add('why2TitleEn', TextType::class, ['label' => 'Différenciateur 2 — titre (anglais)', 'required' => false])
            ->add('why2Desc', TextareaType::class, ['label' => 'Différenciateur 2 — description'])
            ->add('why2DescEn', TextareaType::class, ['label' => 'Différenciateur 2 — description (anglais)', 'required' => false])
            ->add('why3Title', TextType::class, ['label' => 'Différenciateur 3 — titre'])
            ->add('why3TitleEn', TextType::class, ['label' => 'Différenciateur 3 — titre (anglais)', 'required' => false])
            ->add('why3Desc', TextareaType::class, ['label' => 'Différenciateur 3 — description'])
            ->add('why3DescEn', TextareaType::class, ['label' => 'Différenciateur 3 — description (anglais)', 'required' => false])
            ->add('why4Title', TextType::class, ['label' => 'Différenciateur 4 — titre'])
            ->add('why4TitleEn', TextType::class, ['label' => 'Différenciateur 4 — titre (anglais)', 'required' => false])
            ->add('why4Desc', TextareaType::class, ['label' => 'Différenciateur 4 — description'])
            ->add('why4DescEn', TextareaType::class, ['label' => 'Différenciateur 4 — description (anglais)', 'required' => false])

            // --- Au-delà du code ---
            ->add('beyondLanguages', TextareaType::class, [
                'mapped' => false,
                'label' => 'Langues parlées',
                'help' => 'Une par ligne, ex : Français — natif',
                'data' => implode("\n", $data?->getBeyondLanguages() ?? []),
            ])
            ->add('beyondLanguagesEn', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Langues parlées (anglais)',
                'data' => implode("\n", $data?->getBeyondLanguagesEn() ?? []),
            ])
            ->add('beyondInterests', TextareaType::class, [
                'mapped' => false,
                'label' => 'Centres d\'intérêt',
                'help' => 'Un par ligne',
                'data' => implode("\n", $data?->getBeyondInterests() ?? []),
            ])
            ->add('beyondInterestsEn', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Centres d\'intérêt (anglais)',
                'data' => implode("\n", $data?->getBeyondInterestsEn() ?? []),
            ])

            // --- Appel à l'action final ---
            ->add('ctaTitle', TextType::class, ['label' => 'Appel à l\'action — titre'])
            ->add('ctaTitleEn', TextType::class, ['label' => 'Appel à l\'action — titre (anglais)', 'required' => false])
            ->add('ctaSub', TextareaType::class, ['label' => 'Appel à l\'action — texte'])
            ->add('ctaSubEn', TextareaType::class, ['label' => 'Appel à l\'action — texte (anglais)', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AboutContent::class,
        ]);
    }
}
