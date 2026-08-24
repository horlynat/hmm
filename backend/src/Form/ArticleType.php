<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Tag;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                // Article::$title est un `string` non nullable — cf.
                // ArticleType::content ci-dessous pour le pourquoi.
                'empty_data' => '',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Entrez le titre de l’article'
                ],
            ])
            ->add('titleEn', TextType::class, [
                'label' => 'Titre (anglais)',
                'required' => false,
                'help' => 'Optionnel — reprend le titre français si laissé vide.',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Enter the article title'
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                // Article::$content est un `string` non nullable — si le champ
                // venait à être absent de la soumission (ex. navigateur en
                // JS désactivé face à l'éditeur riche), Symfony le traiterait
                // sinon comme "vidé" (null) et ferait planter le mapping avant
                // même la validation. '' reste une valeur licite, rejetée
                // ensuite proprement par la contrainte NotBlank de l'entité.
                'empty_data' => '',
                'attr' => [
                    'rows' => 8,
                    'placeholder' => 'Rédigez le contenu de l’article (min. 20 caractères)'
                ],
            ])
            ->add('contentEn', TextareaType::class, [
                'label' => 'Contenu (anglais)',
                'required' => false,
                'help' => 'Optionnel — reprend le contenu français si laissé vide.',
                'attr' => [
                    'rows' => 8,
                    'placeholder' => 'Write the article content in English'
                ],
            ])
            ->add('media', FileType::class, [
                'label' => 'Image | PDF de l’article',
                'mapped' => false, // pas directement lié à l’entité Article
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'application/pdf'],
                        mimeTypesMessage: 'Seules les images JPG, PNG ou PDF sont autorisées.'
                    )
                ],
            ])
            ->add('tags', EntityType::class, [
                'class' => Tag::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => true, // ⚠️ cohérent avec la contrainte Count(min=1)
                'label' => 'Tags',
                'attr' => [
                    'class' => 'select2' // exemple si tu utilises Select2 pour un rendu amélioré
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}
