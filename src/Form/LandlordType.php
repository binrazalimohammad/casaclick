<?php

namespace App\Form;

use App\Entity\Landlord;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class LandlordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('FirstName', TextType::class, [
                'label' => 'First Name',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Enter first name',
                    'autocomplete' => 'given-name'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter a first name'
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'First name must be at least {{ limit }} characters long',
                        'maxMessage' => 'First name cannot be longer than {{ limit }} characters'
                    ])
                ]
            ])
            ->add('LastName', TextType::class, [
                'label' => 'Last Name',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Enter last name',
                    'autocomplete' => 'family-name'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter a last name'
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Last name must be at least {{ limit }} characters long',
                        'maxMessage' => 'Last name cannot be longer than {{ limit }} characters'
                    ])
                ]
            ])
            ->add('Email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'landlord@example.com',
                    'autocomplete' => 'email'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter an email address'
                    ]),
                    new Assert\Email([
                        'message' => 'Please enter a valid email address'
                    ]),
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'Email cannot be longer than {{ limit }} characters'
                    ])
                ]
            ])
            ->add('Phone', TelType::class, [
                'label' => 'Phone Number',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => '+63 900 000 0000',
                    'autocomplete' => 'tel'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter a phone number'
                    ]),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 20,
                        'minMessage' => 'Phone number must be at least {{ limit }} characters long',
                        'maxMessage' => 'Phone number cannot be longer than {{ limit }} characters'
                    ])
                ]
            ])
            ->add('Address', TextareaType::class, [
                'label' => 'Full Address',
                'attr' => [
                    'class' => 'form-input address-field',
                    'placeholder' => 'Street, Barangay, City, Province, ZIP Code',
                    'autocomplete' => 'street-address',
                    'rows' => 3
                ],
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 500,
                        'maxMessage' => 'Address cannot be longer than {{ limit }} characters'
                    ])
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Landlord::class,
            'attr' => [
                'class' => 'landlord-form',
                'novalidate' => 'novalidate'
            ]
        ]);
    }
}
