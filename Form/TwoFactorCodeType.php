<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A six-digit code from the authenticator app — to confirm the enrolment, or to turn the second
 * factor off. Whether it is RIGHT depends on the account's secret, so `TotpEnrolment` checks it in
 * the controller; the form only checks its shape.
 *
 * Its messages are keys of the application's `validators` catalogue: `two_factor.code.not_blank`,
 * `two_factor.code.format`.
 *
 * @extends AbstractType<array{code?: string}>
 */
final class TwoFactorCodeType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'security.two_factor.code',
            'translation_domain' => 'security',
            'attr' => ['autocomplete' => 'one-time-code', 'inputmode' => 'numeric', 'maxlength' => 6, 'pattern' => '[0-9]*'],
            'constraints' => [
                new Assert\NotBlank(message: 'two_factor.code.not_blank'),
                new Assert\Regex(pattern: '/^\d{6}$/', message: 'two_factor.code.format'),
            ],
        ]);
    }
}
