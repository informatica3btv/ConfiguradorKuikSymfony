<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool)($options['is_edit'] ?? false);

        $builder
            ->add('name', TextType::class, ['label' => 'Nombre'])
            ->add('email', EmailType::class, ['label' => 'Email'])

            // ✅ Roles como multi-selección
            ->add('roles', ChoiceType::class, [
                'label' => 'Roles',
                'choices' => [
                    'Admin'   => 'ROLE_ADMIN',
                ],
                'expanded' => true,  // checkboxes
                'multiple' => true,
                'required' => true,
            ])

            // ✅ Campo NO mapeado: se hashea en el controller
            ->add('plainPassword', PasswordType::class, [
                'label' => $isEdit ? 'Nueva contraseña (opcional)' : 'Contraseña',
                'required' => !$isEdit,
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}
