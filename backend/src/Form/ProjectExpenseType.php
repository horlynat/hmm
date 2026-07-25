<?php

namespace App\Form;

use App\Entity\ProjectExpense;
use App\Enum\ExpenseCategoryEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProjectExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', MoneyType::class, [
                'label' => 'Montant (€)',
                'currency' => 'EUR',
                'scale' => 2,
                'attr' => ['class' => 'input', 'placeholder' => '0.00'],
            ])
            ->add('category', EnumType::class, [
                'class' => ExpenseCategoryEnum::class,
                'label' => 'Catégorie',
                'choice_label' => fn (ExpenseCategoryEnum $c) => $c->getLabel(),
                'attr' => ['class' => 'input input-select'],
            ])
            ->add('spentAt', DateType::class, [
                'label' => 'Date de la dépense',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'input'],
                'help' => 'Laisser vide pour aujourd\'hui.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'input textarea', 'rows' => 3, 'placeholder' => 'Nature, fournisseur, référence…'],
            ])
            ->add('receipt', FileType::class, [
                'label' => 'Justificatif (reçu / facture)',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'input'],
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
                        mimeTypesMessage: 'Formats acceptés : JPG, PNG, WEBP, PDF (max 5 Mo).',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectExpense::class,
        ]);
    }
}
