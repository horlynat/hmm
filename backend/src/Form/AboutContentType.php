<?php

namespace App\Form;

use App\Entity\AboutContent;
use App\Form\DataTransformer\OfferLinesTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class AboutContentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Ordre = ordre de la page (prototype). Bilingue : chaque champ FR a son
        // jumeau « En » (facultatif, repli sur le français). Seuls le titre, le
        // sous-titre et le nom sont obligatoires ; un texte vide masque son bloc.
        $pair = static function (FormBuilderInterface $b, string $name, string $label, bool $textarea = false, bool $required = false, ?string $help = null): void {
            $type = $textarea ? TextareaType::class : TextType::class;
            $attr = $textarea ? ['attr' => ['rows' => 3]] : [];
            $b->add($name, $type, ['label' => $label, 'required' => $required, 'help' => $help] + $attr);
            $b->add($name.'En', $type, ['label' => $label.' (anglais)', 'required' => false] + $attr);
        };

        // --- 1. En-tête : titre, présentation courte, photo ---
        $pair($builder, 'heroTitle', 'Titre — début', false, true, 'Ex : « Ingénieur et »');
        $pair($builder, 'heroTitleAccent', 'Titre — partie en couleur', false, true, 'Ex : « architecte logiciel. »');
        $pair($builder, 'heroSub', 'Courte présentation', true, true);
        $builder->add('profileName', TextType::class, ['label' => 'Nom sous la photo']);
        $pair($builder, 'profileRole', 'Titre sous la photo', false, false, 'Ex : « Ingénieur et architecte logiciel »');
        $pair($builder, 'profileAvailability', 'Pastille de disponibilité', false, false, 'Ex : « Disponible » — vide = pastille masquée');
        $builder->add('profileImageFile', FileType::class, [
            'label' => 'Photo (laisser vide pour conserver l\'actuelle)',
            'mapped' => false,
            'required' => false,
            'constraints' => [
                new File(
                    maxSize: '5M',
                    mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                    mimeTypesMessage: 'Formats autorisés : JPG, PNG, WEBP.',
                ),
            ],
        ]);

        // --- 2. Vision ---
        $pair($builder, 'visionLede', 'Citation', true, false, 'Affichée entre guillemets « … »');
        $pair($builder, 'visionPillar', 'Carte « Vision »', true);
        $pair($builder, 'missionPillar', 'Carte « Mission »', true);
        $pair($builder, 'ambitionPillar', 'Carte « Ambition »', true);
        $pair($builder, 'visionTodayText', 'Ambition « Aujourd\'hui »', true);
        $pair($builder, 'horizonMidText', 'Ambition « D\'ici trois ans »', true);
        $pair($builder, 'visionTomorrowText', 'Ambition « À long terme »', true);

        // --- 3. Qui je suis ---
        $pair($builder, 'bioTitle', 'Titre');
        $pair($builder, 'bioP1', 'Paragraphe 1', true);
        $pair($builder, 'bioP2', 'Paragraphe 2', true);
        foreach ([1, 2, 3, 4, 5] as $i) {
            $pair($builder, "why{$i}Title", "Point {$i} — titre");
            $pair($builder, "why{$i}Desc", "Point {$i} — texte", true);
        }

        // --- 4. Compétences (les domaines se gèrent dans leur propre écran) ---
        $pair($builder, 'skillsTitle', 'Titre');
        $pair($builder, 'skillsLede', 'Chapô', true);
        $pair($builder, 'skillsNote', 'Note sous les onglets', true);

        // --- 5. Réalisations ---
        $pair($builder, 'projectsTitle', 'Titre');
        $pair($builder, 'projectsLede', 'Chapô', true);

        // --- 6. Parcours (expériences et formations : leurs propres écrans) ---
        $pair($builder, 'pathTitle', 'Titre');
        $pair($builder, 'pathLede', 'Chapô', true);
        $pair($builder, 'learningText', 'Encart « Toujours en formation »', true);

        // --- 7. Appel à l'action final ---
        $pair($builder, 'ctaTitle', 'Titre');
        $pair($builder, 'ctaSub', 'Texte', true);

        // Repères du parcours : « Depuis 2016 | dans le numérique », un par
        // ligne. Ligne mal formée => erreur de formulaire (422), jamais 500.
        $statsHelp = 'Un repère par ligne : « Depuis 2016 | dans le numérique » (6 au plus).';
        foreach (['journeyStats' => false, 'journeyStatsEn' => true] as $field => $nullable) {
            $builder->add($field, TextareaType::class, [
                'label' => 'Parcours — repères chiffrés'.($nullable ? ' (anglais)' : ''),
                'required' => false,
                'help' => $statsHelp.($nullable ? ' Optionnel — même ordre que le français.' : ''),
                'attr' => ['rows' => 4],
                'invalid_message' => 'Format invalide — '.$statsHelp,
            ]);
            $builder->get($field)->addModelTransformer(new OfferLinesTransformer(OfferLinesTransformer::MODE_STAT, $nullable));
        }

        // Tout champ texte : vide => '' (jamais null dans un setter `string`,
        // ce qui ferait une 500 si l'attribut HTML `required` est contourné).
        // Les obligatoires portent #[Assert\NotBlank] sur l'entité (titre,
        // sous-titre) ou ici (nom).
        foreach ($builder->all() as $name => $child) {
            $type = $child->getType()->getInnerType()::class;
            // Les repères (journeyStats*) ont un transformateur de modèle qu'un
            // nouvel add() perdrait (→ 500) : on les laisse tels quels.
            if (!$child->getMapped() || !\in_array($type, [TextType::class, TextareaType::class], true) || str_ends_with($name, 'En') || str_starts_with($name, 'journeyStats')) {
                continue;
            }
            $constraints = $child->getOption('constraints') ?? [];
            if ('profileName' === $name) {
                $constraints[] = new NotBlank(normalizer: 'trim', message: 'Ce champ est obligatoire.');
            }
            $builder->add($name, $type, array_merge($child->getOptions(), [
                'empty_data' => '',
                'constraints' => $constraints,
            ]));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AboutContent::class,
        ]);
    }
}
