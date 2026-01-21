<?php

namespace App\Form;

use App\Entity\Color;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminColorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre',
            ])
            ->add('hex', TextType::class, [
                'label' => 'Hex',
                'attr' => [
                    'placeholder' => '#f39c12',
                ],
                'help' => 'Formato recomendado: #RRGGBB (ej: #3498db) o #000000',
            ])
            ->add('ral', TextType::class, [
                'label' => 'Ral',
                'attr' => [
                    'placeholder' => '20002',
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Tipo',
                'choices' => [
                    'Puerta' => 'door',
                    'Cuerpo' => 'body',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Color::class,
        ]);
    }
}
