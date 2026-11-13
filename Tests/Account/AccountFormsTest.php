<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Account;

use Jul6Art\AdminBundle\Form\Security\AccountFields;
use Jul6Art\AdminBundle\Form\Security\ProfileFormType;
use Jul6Art\AdminBundle\Form\Security\RegistrationFormType;
use Jul6Art\AdminBundle\Tests\Account\Fixtures\Member;
use Jul6Art\AuthBundle\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

/**
 * The account forms ask what the entity keeps: the skeleton's web and admin modes run on auth-bundle's User, which
 * has neither names nor picture.
 */
#[CoversClass(RegistrationFormType::class)]
#[CoversClass(ProfileFormType::class)]
#[CoversClass(AccountFields::class)]
final class AccountFormsTest extends TestCase
{
    public function testTheSignUpAsksNamesOnlyOfAnAccountThatKeepsThem(): void
    {
        $member = $this->factory(Member::class)->create(RegistrationFormType::class);
        self::assertSame(['firstName', 'lastName', 'email', 'plainPassword'], array_keys($member->all()));

        $user = $this->factory(User::class)->create(RegistrationFormType::class);
        self::assertSame(['email', 'plainPassword'], array_keys($user->all()));
    }

    public function testTheProfileAsksThePictureOnlyOfAnAccountThatKeepsOne(): void
    {
        self::assertSame(['firstName', 'lastName', 'avatarFile'], array_keys($this->factory(Member::class)->create(ProfileFormType::class)->all()));
        self::assertSame([], array_keys($this->factory(User::class)->create(ProfileFormType::class)->all()));
    }

    public function testAnEmptiedNameWritesAnEmptyStringNeverNull(): void
    {
        $form = $this->factory(Member::class)->create(RegistrationFormType::class, new Member());
        $form->submit(['firstName' => null, 'lastName' => '', 'email' => null, 'plainPassword' => ['first' => '', 'second' => '']]);

        self::assertFalse($form->isValid());
        $member = $form->getData();
        self::assertInstanceOf(Member::class, $member);
        self::assertSame('', $member->getFirstName());
    }

    public function testWithoutUiBundleTheWidgetsAreSymfonys(): void
    {
        self::assertSame(class_exists(AccountFields::UI_EMAIL) ? AccountFields::UI_EMAIL : EmailType::class, AccountFields::email());
        self::assertSame(class_exists(AccountFields::UI_PASSWORD) ? AccountFields::UI_PASSWORD : PasswordType::class, AccountFields::password());
    }

    /**
     * @param class-string $userClass
     */
    private function factory(string $userClass): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new RegistrationFormType($userClass))
            ->addType(new ProfileFormType($userClass))
            ->getFormFactory();
    }
}
