<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticAspectFileBundle\Entity\Schema;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Form type for AspectFile campaign action configuration
 */
class AspectFileActionType extends AbstractType
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
                    'tooltip' => 'Select the schema to use for file generation',
                ],
            ]
        );

        $builder->add(
            'destination_type',
            ChoiceType::class,
            [
                'label' => 'mautic.aspectfile.action.destination_type',
                'required' => true,
                'choices' => [
                    'mautic.aspectfile.action.destination.s3' => 'S3',
                    'mautic.aspectfile.action.destination.network' => 'NETWORK',
                ],
                'empty_data' => 'S3',
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'mautic.aspectfile.action.destination_type.tooltip',
                    'onchange' => 'AspectFile.toggleDestinationFields(this.value)',
                ],
            ]
        );

        $builder->add(
            'bucket_name',
            TextType::class,
            [
                'label' => 'Bucket Name',
                'required' => false,
                'attr' => [
                    'class' => 'form-control aspectfile-s3-field',
                    'tooltip' => 'MinIO/S3 bucket name where the file will be uploaded',
                    'placeholder' => 'my-bucket',
                ],
            ]
        );

        $builder->add(
            'network_path',
            TextType::class,
            [
                'label' => 'mautic.aspectfile.action.network_path',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'mautic.aspectfile.action.network_path.tooltip',
                    'placeholder' => '/mnt/share/uploads or \\\\\\\\server\\\\share\\\\uploads',
                ],
            ]
        );

        $builder->add(
            'file_name_template',
            TextType::class,
            [
                'label' => 'File Name Template (optional)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'Custom file name template. Available variables: {date} (YYYYMMDD), {datetime} (YYYYMMDD_HHMMSS), {timestamp}, {batch_id}, {campaign_id}',
                    'placeholder' => 'my_export_{date}',
                ],
            ]
        );
    }

    public function getBlockPrefix(): string
    {
        return 'aspectfile_action';
    }
}
