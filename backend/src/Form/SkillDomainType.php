<?php

namespace App\Form;

use App\Entity\Offer;
use App\Entity\Project;
use App\Entity\SkillDomain;
use App\Enum\ProjectStatusEnum;
use App\Form\DataTransformer\OfferLinesTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire d'un domaine de compétence (onglet « Compétences » de la page
 * À propos). Les listes se saisissent une ligne par élément ; offres et
 * réalisation se choisissent parmi l'existant (jamais un slug tapé à la main).
 */
class SkillDomainType extends AbstractType
{
    private const ICON_LABELS = [
        'Architecture (blocs)' => 'arch',
        'Sécurité (bouclier)' => 'lock',
        'Risques (parapluie)' => 'umb',
        'IA (étincelle)' => 'spark',
        'Application mobile' => 'app',
        'Site web' => 'web',
        'Automatisation (engrenage)' => 'cog',
        'Commerce' => 'shop',
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $offers = [];
        foreach ($this->em->getRepository(Offer::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']) as $offer) {
            $offers[$offer->getTitle().($offer->isPublished() ? '' : ' (non publiée)')] = $offer->getSlug();
        }
        $projects = [];
        foreach ($this->em->getRepository(Project::class)->findBy([], ['id' => 'ASC']) as $project) {
            $public = \in_array($project->getStatus(), [ProjectStatusEnum::IN_PROGRESS, ProjectStatusEnum::COMPLETED], true);
            $projects[$project->getTitle().($public ? '' : ' (non publique)')] = $project->getSlug();
        }

        $text = static fn (string $label, bool $required = false, ?string $help = null): array => ['label' => $label, 'required' => $required, 'help' => $help, 'empty_data' => ''];
        $builder
            ->add('position', IntegerType::class, ['label' => 'Ordre de l\'onglet', 'help' => '1 = premier onglet.', 'empty_data' => '0'])
            ->add('icon', ChoiceType::class, ['label' => 'Icône', 'choices' => self::ICON_LABELS])
            ->add('title', TextType::class, $text('Titre', true))
            ->add('titleEn', TextType::class, ['label' => 'Titre (anglais)', 'required' => false])
            ->add('short', TextType::class, $text('Accroche (sous le titre de l\'onglet)'))
            ->add('shortEn', TextType::class, ['label' => 'Accroche (anglais)', 'required' => false])
            ->add('text', TextareaType::class, $text('Présentation') + ['attr' => ['rows' => 3]])
            ->add('textEn', TextareaType::class, ['label' => 'Présentation (anglais)', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('doItems', TextareaType::class, ['label' => 'Ce que je fais pour vous', 'required' => false, 'help' => 'Un élément par ligne (8 au plus).', 'attr' => ['rows' => 4]])
            ->add('doItemsEn', TextareaType::class, ['label' => 'Ce que je fais pour vous (anglais)', 'required' => false, 'attr' => ['rows' => 4]])
            ->add('tools', TextareaType::class, ['label' => 'Outils du moment', 'required' => false, 'help' => 'Un outil par ligne (20 au plus). Jamais d\'outil qui révèle la configuration de vos systèmes.', 'attr' => ['rows' => 4]])
            ->add('toolsEn', TextareaType::class, ['label' => 'Outils du moment (anglais, si différents)', 'required' => false, 'attr' => ['rows' => 4]])
            ->add('offerSlugs', ChoiceType::class, ['label' => 'Offres liées (4 au plus)', 'choices' => $offers, 'multiple' => true, 'expanded' => true, 'required' => false, 'help' => 'Seules les offres publiées s\'affichent sur le site.'])
            ->add('proofProjectSlug', ChoiceType::class, ['label' => 'Réalisation qui sert de preuve', 'choices' => $projects, 'required' => false, 'placeholder' => '— Aucune —', 'help' => 'Affichée seulement si elle est publique.'])
        ;

        foreach (['doItems' => false, 'doItemsEn' => true, 'tools' => false, 'toolsEn' => true] as $field => $nullable) {
            $builder->get($field)->addModelTransformer(new OfferLinesTransformer(OfferLinesTransformer::MODE_LIST, $nullable));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SkillDomain::class]);
    }
}
