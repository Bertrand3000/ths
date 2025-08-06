<?php

namespace App\Form;

use App\Entity\Etage;
use App\Entity\Site;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class EtageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'étage',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir un nom.']),
                ],
            ])
            ->add('site', EntityType::class, [
                'class' => Site::class,
                'choice_label' => 'nom',
                'label' => 'Site parent',
                'placeholder' => 'Sélectionnez un site',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un site.']),
                ],
            ])
            ->add('arriereplan', TextType::class, [
                'label' => 'Fichier de l\'arrière-plan',
                'required' => false,
            ])
            ->add('largeur', NumberType::class, [
                'label' => 'Largeur (px)',
                'required' => false,
            ])
            ->add('hauteur', NumberType::class, [
                'label' => 'Hauteur (px)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Etage::class,
        ]);
    }
}
