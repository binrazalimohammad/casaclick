<?php

namespace App\Form;

use App\Entity\Payment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', MoneyType::class, [
                'label' => 'Payment Amount',
                'currency' => 'PHP',
                'attr' => [
                    'placeholder' => 'Enter payment amount',
                ],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Payment Method',
                'choices' => [
                    'Cash' => 'cash',
                    'Bank Transfer' => 'bank_transfer',
                    'GCash' => 'gcash',
                    'PayMaya' => 'paymaya',
                    'Credit Card' => 'credit_card',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('transactionId', TextType::class, [
                'label' => 'Transaction ID / Reference Number',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter transaction ID or reference number (if applicable)',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Payment Notes',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Additional notes about this payment',
                    'rows' => 3,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
        ]);
    }
}

