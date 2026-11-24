<?php

namespace App\Form;

use App\Entity\Offer;
use App\Form\DataTransformer\OfferLinesTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class OfferType extends AbstractType
{
    /** Textes bilingues : champ => [libellé, zone de texte ?]. */
    private const TEXTS = [
        'title' => ['Titre de l\'offre', false],
        'shortTitle' => ['Libellé court (carrousel, menus)', false],
        'pain' => ['Problème du client (« Vos clients… ? »)', false],
        'headline' => ['Titre du carrousel et de la page', false],
        'leadText' => ['Accroche', true],
        'cardText' => ['Texte de la carte (bénéfice)', true],
        'audience' => ['Pour qui', false],
        'clientNeeds' => ['Ce dont j\'ai besoin de votre part', true],
        'delay' => ['Délai affiché (ex. 21 jours)', false],
    ];

    /** Listes bilingues : champ => [libellé, mode, aide]. */
    private const LISTS = [
        'includes' => ['Ce que vous recevez', OfferLinesTransformer::MODE_LIST, 'Un élément par ligne (1 à 12).'],
        'beforeItems' => ['Aujourd\'hui (avant l\'offre)', OfferLinesTransformer::MODE_LIST, 'Un élément par ligne (6 au plus).'],
        'afterItems' => ['Avec cette offre', OfferLinesTransformer::MODE_LIST, 'Un élément par ligne (6 au plus).'],
        'calendar' => ['Calendrier', OfferLinesTransformer::MODE_CALENDAR, 'Une étape par ligne : « J1 | Échange et collecte de vos produits ».'],
        'pack' => ['Blocs du pack', OfferLinesTransformer::MODE_PACK, 'Un bloc par ligne : « Répondre : Questions 24 h sur 24 ; Réponses dans votre ton ».'],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = static fn (array $values): array => array_combine($values, $values);

        // Champ absent de la soumission => null transmis au setter : les champs
        // texte ont un `empty_data`, les listes à choix des setters qui
        // acceptent null (cf. Offer::setCategory) — sinon 500 (contre-audit R1).
        $builder
            ->add('slug', TextType::class, ['label' => 'Slug (adresse de la page)', 'empty_data' => '', 'help' => 'Ex. assistant-whatsapp — minuscules, chiffres et tirets.'])
            ->add('published', CheckboxType::class, ['label' => 'Publiée sur le site', 'required' => false])
            ->add('position', IntegerType::class, ['label' => 'Ordre d\'affichage', 'empty_data' => '0'])
            ->add('heroPosition', IntegerType::class, ['label' => 'Position dans le carrousel de l\'accueil', 'required' => false, 'help' => 'Vide = absente du carrousel.'])
            ->add('featured', CheckboxType::class, ['label' => 'Offre vedette', 'required' => false])
            ->add('category', ChoiceType::class, ['label' => 'Famille', 'choices' => ['Ingénierie (dev)' => 'dev', 'Audit et conseil (aud)' => 'aud']])
            ->add('icon', ChoiceType::class, ['label' => 'Icône', 'choices' => $choices(Offer::ICONS)])
            ->add('previewType', ChoiceType::class, ['label' => 'Aperçu illustré (repli si aucune image)', 'choices' => $choices(Offer::PREVIEWS)])
            // Image réelle (Offer::coverImage) — non mappée : gérée à part en
            // contrôleur comme ProjectInfo::coverImage, pour créer/remplacer le
            // Media associé (cf. AdminOfferController::syncCoverImageFromForm).
            // Absente => reste l'illustration de `previewType` ci-dessus.
            ->add('coverImage', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Image illustrative (remplace l\'aperçu dessiné)',
                'help' => "Photo ou visuel réel affiché dans le carrousel de l'accueil et la fiche de l'offre.",
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
                        mimeTypesMessage: 'Formats autorisés : JPG, PNG, WebP, SVG.'
                    ),
                ],
            ])
            ->add('removeCoverImage', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Retirer l\'image illustrative actuelle',
            ])
            ->add('situation', ChoiceType::class, ['label' => 'Situation (filtre des offres)', 'choices' => $choices(Offer::SITUATIONS)])
            ->add('proofProjectSlug', TextType::class, ['label' => 'Projet cité en preuve (slug)', 'required' => false])
            ->add('price', IntegerType::class, ['label' => 'Prix (FCFA)', 'empty_data' => '0'])
            ->add('priceOnce', CheckboxType::class, ['label' => 'Prix unique (sinon « À partir de »)', 'required' => false]);

        foreach (self::TEXTS as $field => [$label, $textarea]) {
            $type = $textarea ? TextareaType::class : TextType::class;
            $builder
                ->add($field, $type, ['label' => $label, 'empty_data' => '', 'attr' => $textarea ? ['rows' => 3] : []])
                ->add($field.'En', $type, ['label' => $label.' (anglais)', 'required' => false, 'help' => 'Optionnel — reprend le français si vide.', 'attr' => $textarea ? ['rows' => 3] : []]);
        }

        foreach (self::LISTS as $field => [$label, $mode, $help]) {
            foreach (['' => false, 'En' => true] as $suffix => $nullable) {
                $builder->add($field.$suffix, TextareaType::class, [
                    'label' => $label.($nullable ? ' (anglais)' : ''),
                    'required' => false,
                    'help' => $help.($nullable ? ' Optionnel — même ordre que le français.' : ''),
                    'attr' => ['rows' => 5],
                    'invalid_message' => 'Format invalide — '.$help,
                ]);
                $builder->get($field.$suffix)->addModelTransformer(new OfferLinesTransformer($mode, $nullable));
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Offer::class]);
    }
}
