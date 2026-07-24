<?php

namespace App\Form;

use App\Entity\Language;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class AdminLanguageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Código',
                'attr' => ['placeholder' => 'es, en, fr...'],
                'help' => 'Código ISO 639-1 del idioma (2 letras).',
            ])
            ->add('name', TextType::class, [
                'label' => 'Nombre',
                'attr' => ['placeholder' => 'Español'],
            ])
            ->add('flagFile', FileType::class, [
                'label' => 'Imagen de bandera',
                'mapped' => false,
                'required' => false,
                'help' => 'Sube una imagen (PNG, JPG, SVG o WEBP, máx. 1MB) para el selector de idioma. Si no subes ninguna se usa un icono por defecto.',
                'constraints' => [
                    new File([
                        'maxSize' => '1M',
                        'mimeTypes' => [
                            'image/png',
                            'image/jpeg',
                            'image/webp',
                            'image/svg+xml',
                        ],
                        'mimeTypesMessage' => 'Sube una imagen válida (PNG, JPG, SVG o WEBP).',
                    ]),
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Orden',
                'required' => false,
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Activo',
                'required' => false,
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'Idioma por defecto',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Language::class,
        ]);
    }
}
