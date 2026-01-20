<?php

namespace App\Form;

use App\Entity\Transaction;
use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class TransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', NumberType::class, [
                'label' => 'Сумма',
                'html5' => true,
                'attr' => [
                    'placeholder' => '0.00',
                    'step' => '0.01',
                    'min' => '0.01'
                ]
            ])
            ->add('description', TextType::class, [
                'label' => 'Описание',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Необязательное описание'
                ]
            ])
            ->add('category', EntityType::class, [
                'label' => 'Категория',
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Выберите категорию',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('date', DateType::class, [
                'label' => 'Дата',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
        ;

        // Устанавливаем текущую дату по умолчанию при создании новой транзакции
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $transaction = $event->getData();
            
            if (!$transaction || null === $transaction->getId()) {
                $transaction->setDate(new \DateTime());
            }
        });

        // Обрабатываем сабмит - если дата пустая, устанавливаем текущую
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $transaction = $event->getData();
            
            if (!$transaction->getDate()) {
                $transaction->setDate(new \DateTime());
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Transaction::class,
        ]);
    }
}