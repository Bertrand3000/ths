<?php

namespace App\Form;

use App\Entity\Site;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du site',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir un nom pour le site.',
                    ]),
                    new Length([
                        'min' => 2,
                        'minMessage' => 'Le nom du site doit comporter au moins {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('flex', CheckboxType::class, [
                'label' => 'Le site est en flex office',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Site::class,
        ]);
    }
}
