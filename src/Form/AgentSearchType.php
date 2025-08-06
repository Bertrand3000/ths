<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AgentSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('search_term', TextType::class, [
                'label' => 'Nom ou prénom',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: Dupont',
                ],
            ])
            ->add('num_agent', TextType::class, [
                'label' => 'Numéro d\'agent (5 chiffres)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: 01234',
                    'pattern' => '\d{5}',
                    'title' => 'Le numéro d\'agent doit contenir 5 chiffres.'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
