<?php

namespace AppBundle\Form;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\StripeAccount;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

abstract class LocalBusinessType extends AbstractType
{
    protected $authorizationChecker;
    protected $tokenStorage;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'localBusiness.form.enabled',
                'required' => false
            ])
            ->add('name', TextType::class, ['label' => 'localBusiness.form.name',])
            ->add('legalName', TextType::class, ['required' => false, 'label' => 'localBusiness.form.legalName',])
            ->add('website', UrlType::class, ['required' => false, 'label' => 'localBusiness.form.website',])
            ->add('address', AddressType::class)
            ->add('imageFile', VichImageType::class, [
                'required' => false,
                'download_uri' => false,
            ])
            ->add('telephone', TextType::class, ['required' => false, 'label' => 'localBusiness.form.telephone',])
            ->add('openingHours', CollectionType::class, [
                'entry_type' => HiddenType::class,
                'required' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'label' => 'localBusiness.form.openingHours',
            ]);

        if (in_array('siret', $options['additional_properties'])) {
            $builder->add('siret', TextType::class, [
                'required' => false,
                'mapped' => false,
            ]);
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {
            $form = $event->getForm();
            $localBusiness = $event->getData();

            if (in_array('siret', $options['additional_properties'])) {
                $form->get('siret')->setData($localBusiness->getAdditionalPropertyValue('siret'));
            }
        });

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($options) {

                $localBusiness = $event->getForm()->getData();

                // Make sure there is no NULL value in the openingHours array
                $openingHours = array_filter($localBusiness->getOpeningHours());
                $localBusiness->setOpeningHours($openingHours);

                if (in_array('siret', $options['additional_properties'])) {
                    $value = $event->getForm()->get('siret')->getData();
                    $localBusiness->setAdditionalProperty('siret', $value);
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'additional_properties' => [],
        ));
    }
}
