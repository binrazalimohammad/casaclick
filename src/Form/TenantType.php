<?php

namespace App\Form;

use App\Entity\Tenant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class TenantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('email', EmailType::class, [
                'disabled' => $options['disable_email'] ?? false,
                'help' => $options['disable_email'] ? 'Email cannot be changed. It must match your account email.' : null,
            ])
            ->add('phone', TextType::class)
            ->add('address', TextType::class, [ 'required' => false ])
            ->add('modeOfPayment', ChoiceType::class, [
                'label' => 'Mode of Payment',
                'required' => false,
                'choices' => [
                    'Cash' => 'cash',
                    'Bank Transfer' => 'bank_transfer',
                    'GCash' => 'gcash',
                    'PayMaya' => 'paymaya',
                    'Credit Card' => 'credit_card',
                ],
                'placeholder' => 'Select payment method',
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('photo', FileType::class, [
                'label' => 'Photo',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/*'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tenant::class,
            'disable_email' => false,
        ]);
        
        $resolver->setAllowedTypes('disable_email', 'bool');
    }
}
