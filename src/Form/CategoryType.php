<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use App\Enum\CategoryType as EnumCategoryType;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Название категории',
                'attr' => [
                    'placeholder' => 'Введите название категории',
                    'class' => 'form-control'
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Тип категории',
                'choices' => [
                    'Доход' => EnumCategoryType::INCOME,
                    'Расход' => EnumCategoryType::EXPENSE
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'placeholder' => 'Выберите тип категории'
            ])
            ->add('color', ColorType::class, [
                'label' => 'Цвет категории',
                'attr' => [
                    'class' => 'form-control form-control-color'
                ],
                'html5' => true // Включает нативный color picker
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}