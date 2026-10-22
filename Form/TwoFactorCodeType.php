<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A six-digit code from the authenticator app — to confirm the enrolment, or to turn the second
 * factor off. Whether it is RIGHT depends on the account's secret, so `TotpEnrolment` checks it in
 * the controller; the form only checks its shape.
 *
 * Its messages are keys of the application's `validators` catalogue: `two_factor.code.not_blank`,
 * `two_factor.code.format` — and `two_factor.code.format_or_backup` with `backup_code`, where a
 * backup code (`K7QM2-XW9RT`) is accepted beside the six digits.
 *
 * @extends AbstractType<array{code?: string}>
 */
final class TwoFactorCodeType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $backup = true === $options['backup_code'];

        $builder->add('code', TextType::class, [
            'label' => $backup ? 'security.two_factor.code_or_backup' : 'security.two_factor.code',
            'translation_domain' => 'security',
            // Pas de clavier numérique ni de motif quand un code de secours est permis : il a des lettres.
            'attr' => $backup
                ? ['autocomplete' => 'one-time-code', 'maxlength' => 11, 'autocapitalize' => 'characters', 'spellcheck' => 'false']
                : ['autocomplete' => 'one-time-code', 'inputmode' => 'numeric', 'maxlength' => 6, 'pattern' => '[0-9]*'],
            'constraints' => [
                new Assert\NotBlank(message: 'two_factor.code.not_blank'),
                $backup
                    ? new Assert\Regex(pattern: '/^(\d{6}|[A-Za-z0-9]{5}[\s-]?[A-Za-z0-9]{5})$/', message: 'two_factor.code.format_or_backup')
                    : new Assert\Regex(pattern: '/^\d{6}$/', message: 'two_factor.code.format'),
            ],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('backup_code', false);
        $resolver->setAllowedTypes('backup_code', 'bool');
    }
}
