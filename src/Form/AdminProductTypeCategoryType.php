<?php

namespace App\Form;

use App\Entity\ProductCategory;
use App\Entity\ProductTypeCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminProductTypeCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', EntityType::class, [
                'label' => 'Categoría',
                'class' => ProductCategory::class,
                'choice_label' => 'name',
                'query_builder' => fn ($repo) => $repo->createQueryBuilder('c')->orderBy('c.position', 'ASC')->addOrderBy('c.name', 'ASC'),
                'placeholder' => 'Sin categoría',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductTypeCategory::class,
        ]);
    }
}
