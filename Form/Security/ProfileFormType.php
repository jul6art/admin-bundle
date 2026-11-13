<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Form\Security;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The profile: what an account corrects itself, and nothing else — its names and its picture, when it keeps them.
 *
 * ⚠️ Neither the address, nor the roles, nor the active flag: changing them belongs to the administration of the
 * accounts. Here, any account could rename itself into another sign-in identity, or grant itself a role.
 *
 * `empty_data => ''` on the names: a name emptied would write `null` into a setter that takes a string.
 *
 * @extends AbstractType<object>
 */
final class ProfileFormType extends AbstractType
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
        foreach (['firstName' => 'profile.first_name', 'lastName' => 'profile.last_name'] as $field => $label) {
            if (AccountFields::writable($class, $field)) {
                $builder->add($field, TextType::class, [
                    'label' => $label,
                    'empty_data' => '',
                    'constraints' => [new Assert\Length(max: 100)],
                ]);
            }
        }

        if (AccountFields::writable($class, 'avatarPath')) {
            // `mapped: false`: the field carries an uploaded file, the entity a path. The controller converts one into
            // the other, after validation.
            $builder->add('avatarFile', FileType::class, [
                'label' => 'profile.avatar',
                'help' => 'profile.avatar_help',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\Image(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        maxSizeMessage: 'profile.avatar_too_large',
                        mimeTypesMessage: 'profile.avatar_mime',
                    ),
                ],
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->userClass,
            'translation_domain' => 'user',
        ]);
    }
}
