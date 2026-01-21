<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
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
            ->add('email', EmailType::class, ['label' => 'Email']);

        // Password: en editar opcional
        $builder->add('password', PasswordType::class, [
            'label' => $isEdit ? 'Nueva contraseña (opcional)' : 'Contraseña',
            'required' => !$isEdit,
            'mapped' => true, // si en tu entidad password = hash, esto debe cambiar
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}
