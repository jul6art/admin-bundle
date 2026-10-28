<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Form;

use Jul6Art\AdminBundle\Contract\DeadlineInterface;
use Jul6Art\AdminBundle\Deadline\DeadlineNotifier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A deadline typed in the screen. `data_class` is the application's entity, given by the
 * controller. The rules — a label, a date, a period of 1 to 3,650 days, an http(s) address — are
 * checked by the controller: this bundle does not require the validator.
 *
 * @extends AbstractType<DeadlineInterface>
 */
final class DeadlineType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'deadline.field.label',
                // Vide, le champ rend '' et non null : le contrôleur dit l'erreur sur le champ.
                'empty_data' => '',
                'attr' => ['maxlength' => 120, 'autofocus' => true],
            ])
            ->add('dueOn', DateType::class, [
                'label' => 'deadline.field.due_on',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                // Une date effacée ne s'écrit pas : le contrôleur refuse le formulaire sur ce champ.
                'setter' => static function (DeadlineInterface $deadline, ?\DateTimeImmutable $dueOn): void {
                    if ($dueOn instanceof \DateTimeImmutable) {
                        $deadline->setDueOn($dueOn);
                    }
                },
            ])
            ->add('periodDays', IntegerType::class, [
                'label' => 'deadline.field.period_days',
                'required' => false,
                'help' => 'deadline.help.period_days',
                'attr' => ['min' => 1, 'max' => 3650],
            ])
            ->add('renewUrl', UrlType::class, [
                'label' => 'deadline.field.renew_url',
                'required' => false,
                'default_protocol' => null,
                'help' => 'deadline.help.renew_url',
                'attr' => ['maxlength' => 500, 'placeholder' => 'https://'],
            ])
            ->add('note', TextareaType::class, [
                'label' => 'deadline.field.note',
                'required' => false,
                'help' => 'deadline.help.note',
                'attr' => ['rows' => 3],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => DeadlineNotifier::DOMAIN,
        ]);
    }
}
