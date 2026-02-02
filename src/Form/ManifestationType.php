<?php

namespace App\Form;

use App\Entity\Manifestation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\NotBlank;

class ManifestationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextareaType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'タイトルは必須です',
                    ]),
                ],
                'attr' => [
                    'rows' => 1,
                ],
                'required' => true,
            ])
            ->add('title_transcription', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 1,
                ],
            ])
            ->add('identifier', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => '識別子は必須です',
                    ]),
                ],
                'required' => true,
            ])
            ->add('external_identifier1', null, [
                'required' => false,
            ])
            ->add('external_identifier2', null, [
                'required' => false,
            ])
            ->add('external_identifier3', null, [
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 1,
                ],
            ])
            ->add('buyer', null, [
                'required' => false,
            ])
            ->add('buyer_identifier', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 1,
                ],
            ])
            ->add('purchase_date', null, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('record_source', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 1,
                ],
            ])
            ->add('type1', null, [
                'required' => false,
            ])
            ->add('type2', null, [
                'required' => false,
            ])
            ->add('type3', null, [
                'required' => false,
            ])
            ->add('type4', null, [
                'required' => false,
            ])
            ->add('class1', null, [
                'required' => false,
            ])
            ->add('class2', null, [
                'required' => false,
            ])
            ->add('location1', null, [
                'required' => false,
            ])
            ->add('location2', null, [
                'required' => false,
            ])
            ->add('location3', null, [
                'required' => false,
            ])
            ->add('contributor1', null, [
                'required' => false,
            ])
            ->add('contributor2', null, [
                'required' => false,
            ])
            ->add('loan_restriction', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    '通常貸出' => '通常貸出',
                    '貸出禁止' => '貸出禁止',
                ],
                'placeholder' => '選択してください',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Manifestation::class,
            'validation_groups' => ['Default'],
        ]);
    }
}
