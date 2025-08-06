<?php

namespace App\Form;

use App\Entity\Etage;
use App\Entity\Service;
use App\Service\ArchitectureService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlacesLibresSearchType extends AbstractType
{
    public function __construct(private readonly ArchitectureService $architectureService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('etage', EntityType::class, [
                'class' => Etage::class,
                'choice_label' => 'nom',
                'required' => false,
                'placeholder' => 'Tous les étages',
                'label' => 'Étage',
            ])
            ->add('service', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'nom',
                'required' => false,
                'placeholder' => 'Tous les services',
                'label' => 'Service',
            ])
            ->add('type', ChoiceType::class, [
                'choices' => array_combine(
                    $this->architectureService->positionTypes,
                    $this->architectureService->positionTypes
                ),
                'required' => false,
                'placeholder' => 'Tous les types',
                'label' => 'Type de position',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
