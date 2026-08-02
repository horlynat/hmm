<?php

namespace App\Form;

use App\Entity\Invoice;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('number', TextType::class, [
                'label' => 'Numéro de facture',
                'attr' => ['class' => 'input', 'placeholder' => 'FA-2026-001'],
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'attr' => ['class' => 'input', 'placeholder' => 'Acompte 30 %, solde final…'],
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'Montant',
                'currency' => false,
                'scale' => 2,
                'attr' => ['class' => 'input', 'placeholder' => '0.00'],
            ])
            ->add('currency', ChoiceType::class, [
                'label' => 'Devise',
                'choices' => ['EUR' => 'EUR', 'USD' => 'USD', 'GBP' => 'GBP'],
                'attr' => ['class' => 'input input-select'],
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Échéance',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Invoice::class,
        ]);
    }
}
