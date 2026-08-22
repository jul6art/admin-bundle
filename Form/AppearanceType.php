<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Form;

use Jul6Art\AdminBundle\Appearance\AccentColor;
use Jul6Art\AdminBundle\Appearance\ColorMode;
use Jul6Art\AdminBundle\Appearance\DisplayDensity;
use Jul6Art\AdminBundle\Appearance\FontScale;
use Jul6Art\AdminBundle\Contract\AppearanceAwareInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The self-service appearance settings, bound to the signed-in account.
 *
 * Every field is `expanded`, because the template renders none of them as a `<select>`: the mode
 * is three preview cards, the accent a row of swatches, the density and the font scale segmented
 * controls. A widget only reachable through a dropdown is a setting people do not discover.
 *
 * `data_class` is left open — the application passes its own `User`, which only has to implement
 * {@see AppearanceAwareInterface}. Pinning it here would tie the bundle to an entity it does not
 * own, the mistake `auth-bundle` documented with its concrete `User`.
 *
 * @extends AbstractType<AppearanceAwareInterface>
 */
final class AppearanceType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('colorMode', EnumType::class, [
                'class' => ColorMode::class,
                'label' => 'appearance.form.mode',
                'choice_label' => static fn (ColorMode $m): string => $m->translationKey(),
                'expanded' => true,
            ])
            ->add('accent', EnumType::class, [
                'class' => AccentColor::class,
                'label' => 'appearance.form.accent',
                'choice_label' => static fn (AccentColor $c): string => $c->translationKey(),
                'expanded' => true,
            ])
            ->add('density', EnumType::class, [
                'class' => DisplayDensity::class,
                'label' => 'appearance.form.density',
                'choice_label' => static fn (DisplayDensity $d): string => $d->translationKey(),
                'expanded' => true,
            ])
            ->add('fontScale', EnumType::class, [
                'class' => FontScale::class,
                'label' => 'appearance.form.font_scale',
                'choice_label' => static fn (FontScale $f): string => $f->translationKey(),
                'expanded' => true,
            ])
            ->add('highContrast', CheckboxType::class, [
                'label' => 'appearance.form.high_contrast',
                'required' => false,
            ])
            ->add('reducedMotion', CheckboxType::class, [
                'label' => 'appearance.form.reduced_motion',
                'required' => false,
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'appearance',
        ]);
    }
}
