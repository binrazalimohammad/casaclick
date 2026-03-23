<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Symfony form field types
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Listing Name',
                'attr' => [
                    'placeholder' => 'Enter apartment or listing name',
                ],
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Monthly Rent (PHP)',
                'currency' => 'PHP',
                'attr' => [
                    'placeholder' => 'Enter monthly rent (e.g. 12000)',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Describe your apartment (location, amenities, etc.)',
                    'rows' => 4,
                ],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name', // Display the "name" field from Category entity
                'placeholder' => 'Select a category',
                'label' => 'Category',
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('image', FileType::class, [
                'label' => 'Upload Apartment Image',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Choose a .jpeg or .png file',
                    'accept' => 'image/jpeg,image/png',
                    'class' => 'form-control',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
