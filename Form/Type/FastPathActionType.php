<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticAspectFileBundle\Entity\Schema;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Form type for FastPath campaign action configuration
 */
class FastPathActionType extends AbstractType
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Get published schemas
        $schemas = $this->em->createQueryBuilder()
            ->select('s')
            ->from(Schema::class, 's')
            ->where('s.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        $schemaChoices = [];
        foreach ($schemas as $schema) {
            $schemaChoices[$schema->getName()] = $schema->getId();
        }

        $builder->add(
            'schema_id',
            ChoiceType::class,
            [
                'label' => 'Schema',
                'choices' => $schemaChoices,
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'Select the schema to use for formatting lead data',
                ],
            ]
        );

        $builder->add(
            'wsdl_url',
            TextType::class,
            [
                'label' => 'WSDL URL',
                'required' => true,
                'empty_data' => 'http://bpctaasp1alme.bp.local:8000/FastPathService?wsdl',
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'FastPath SOAP service WSDL URL',
                    'placeholder' => 'http://bpctaasp1alme.bp.local:8000/FastPathService?wsdl',
                ],
            ]
        );

        $builder->add(
            'fast_list',
            TextType::class,
            [
                'label' => 'FastList Name',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'Name of the FastPath list to send records to',
                    'placeholder' => 'e.g., BRADESCO_COMERCIAL_PJ',
                ],
            ]
        );

        $builder->add(
            'function_type',
            IntegerType::class,
            [
                'label' => 'Function Type',
                'required' => true,
                'empty_data' => '1',
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'FastPath function type (integer)',
                    'min' => 1,
                    'placeholder' => '1',
                ],
            ]
        );

        $builder->add(
            'timeout',
            IntegerType::class,
            [
                'label' => 'Timeout (seconds)',
                'required' => false,
                'empty_data' => '30',
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'SOAP request timeout in seconds',
                    'min' => 5,
                    'max' => 120,
                    'placeholder' => '30',
                ],
            ]
        );

        $builder->add(
            'custom_field_1',
            TextType::class,
            [
                'label' => 'Custom Field 1 (optional)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'Optional custom field 1',
                ],
            ]
        );

        $builder->add(
            'custom_field_2',
            TextType::class,
            [
                'label' => 'Custom Field 2 (optional)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'Optional custom field 2',
                ],
            ]
        );

        $builder->add(
            'custom_field_3',
            TextType::class,
            [
                'label' => 'Custom Field 3 (optional)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'Optional custom field 3',
                ],
            ]
        );

        $builder->add(
            'response_uri',
            TextType::class,
            [
                'label' => 'Response URI (optional)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'Optional response URI for callbacks',
                ],
            ]
        );
    }

    public function getBlockPrefix(): string
    {
        return 'fastpath_action';
    }
}
