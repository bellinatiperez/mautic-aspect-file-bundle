<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Form\Type;

use MauticPlugin\MauticAspectFileBundle\Entity\Schema;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for Schema entity
 */
class SchemaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'name',
            TextType::class,
            [
                'label' => 'Name',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'Schema name',
                ],
            ]
        );

        $builder->add(
            'description',
            TextareaType::class,
            [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                ],
            ]
        );

        $builder->add(
            'fileExtension',
            TextType::class,
            [
                'label' => 'File Extension',
                'required' => true,
                'data' => 'raw',
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'File extension (e.g., raw, txt, dat)',
                ],
            ]
        );

        $builder->add(
            'isPublished',
            CheckboxType::class,
            [
                'label' => 'Published',
                'required' => false,
                'attr' => [
                    'tooltip' => 'Make this schema available for use',
                ],
            ]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Schema::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'aspectfile_schema';
    }
}
