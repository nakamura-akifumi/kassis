<?php

namespace App\Form;

use App\Entity\Member;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('identifier', TextType::class, [
                'required' => true,
                'attr' => [
                    'pattern' => '[A-Za-z0-9]+',
                    'title' => '英数字のみで入力してください。',
                ],
            ])
            ->add('full_name', TextType::class, [
                'required' => true,
            ])
            ->add('full_name_transcription', TextType::class, [
                'required' => false,
            ])
            ->add('group1', ChoiceType::class, [
                'required' => false,
                'empty_data' => 'standard',
                'choices' => $options['group1_choices'],
                'placeholder' => '選択してください',
            ])
            ->add('group2', TextType::class, [
                'required' => false,
            ])
            ->add('communication_address1', TextType::class, [
                'required' => false,
            ])
            ->add('communication_address2', TextType::class, [
                'required' => false,
            ])
            ->add('role', ChoiceType::class, [
                'required' => false,
                'choices' => $options['role_choices'],
                'placeholder' => '選択してください',
            ])
            ->add('status', ChoiceType::class, [
                'required' => false,
                'placeholder' => '選択してください',
                'empty_data' => Member::STATUS_ACTIVE,
                'choices' => [
                    '有効' => Member::STATUS_ACTIVE,
                    '無効' => Member::STATUS_INACTIVE,
                    '期限切れ' => Member::STATUS_EXPIRED,
                ],
            ])
            ->add('note', TextareaType::class, [
                'required' => false,
            ])
            ->add('expiry_date', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Member::class,
            'group1_choices' => [],
            'role_choices' => [],
        ]);
    }
}
