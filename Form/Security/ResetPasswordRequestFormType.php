<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Form\Security;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The reset request: an address, nothing else.
 *
 * No `data_class`: the form writes on no account, and giving it one would tie it to an account whose existence is
 * exactly what must not be told. An application that adds a captcha makes this type the parent of its own.
 *
 * @extends AbstractType<array{email: string}>
 */
final class ResetPasswordRequestFormType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', AccountFields::email(), [
            'label' => 'security.reset_password.request.email',
            'empty_data' => '',
            'constraints' => [
                new Assert\NotBlank(message: 'user.email.not_blank'),
                new Assert\Email(message: 'user.email.invalid'),
            ],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'security']);
    }
}
