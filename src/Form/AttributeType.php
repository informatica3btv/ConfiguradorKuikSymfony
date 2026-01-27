<?php

namespace App\Form;

use App\Entity\Attribute;
use App\Entity\AttributesType;
use App\Entity\ConfigurationType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('value')
            ->add('description', null, ['required' => false]);

        // ✅ si NO está bloqueado, mostramos el selector
        if (!$options['lock_attributes_type']) {
            $builder->add('attributesType', EntityType::class, [
                'class' => AttributesType::class,
                'choice_label' => 'name',
            ]);
        }

        $builder->add('configurationTypes', EntityType::class, [
            'class' => ConfigurationType::class,
            'choice_label' => 'name',
            'multiple' => true,
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Attribute::class,
            'lock_attributes_type' => false,
        ]);
    }
}
