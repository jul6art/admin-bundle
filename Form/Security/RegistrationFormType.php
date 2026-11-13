<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Form\Security;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The public sign-up: the names when the account keeps them, the address, a password typed twice.
 *
 * - **`empty_data => ''`** on every text: the account's setters take a string, and a field emptied by the visitor
 *   would otherwise write `null` into it — a 500 before validation could say what is missing.
 * - **The password's rules are here**, not on the entity: `plainPassword` is transient, and the form is where they
 *   apply.
 *
 * An application with more to ask — a captcha, its terms, a username — makes this type the parent of its own
 * (`getParent()`) and names it in `admin.account.registration.form_type`.
 *
 * @extends AbstractType<object>
 */
final class RegistrationFormType extends AbstractType
{
    /**
     * @param class-string $userClass
     */
    public function __construct(
        private readonly string $userClass,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var class-string $class */
        $class = $options['data_class'];
        foreach (['firstName' => 'security.register.first_name', 'lastName' => 'security.register.last_name'] as $field => $label) {
            if (AccountFields::writable($class, $field)) {
                $builder->add($field, TextType::class, ['label' => $label, 'empty_data' => '']);
            }
        }

        $builder
            ->add('email', AccountFields::email(), ['label' => 'security.register.email', 'empty_data' => ''])
            ->add('plainPassword', RepeatedType::class, [
                'type' => AccountFields::password(),
                // ⚠️ A translation key, never Symfony's English default sentence.
                'invalid_message' => 'password.mismatch',
                'first_options' => ['label' => 'security.register.password'],
                'second_options' => ['label' => 'security.register.password_confirm'],
                'constraints' => [
                    new Assert\NotBlank(message: 'password.not_blank'),
                    new Assert\Length(min: 12, max: 4096, minMessage: 'password.too_short'),
                ],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->userClass,
            'translation_domain' => 'security',
        ]);
    }
}
