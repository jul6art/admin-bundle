<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Account\Fixtures;

use Jul6Art\AdminBundle\Form\Security\ResetPasswordRequestFormType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * An application's request form: the bundle's, and one more field — as cegeta and tradebot add a captcha.
 *
 * @extends AbstractType<array{email: string, code: string}>
 */
final class CodeResetPasswordRequestType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, ['required' => false]);
    }

    #[\Override]
    public function getParent(): string
    {
        return ResetPasswordRequestFormType::class;
    }
}
