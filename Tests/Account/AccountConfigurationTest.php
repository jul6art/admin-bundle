<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Account;

use Jul6Art\AdminBundle\Account\AccountSpaces;
use Jul6Art\AdminBundle\Controller\Security\LoginController;
use Jul6Art\AdminBundle\Controller\Security\RegistrationController;
use Jul6Art\AdminBundle\Controller\Security\ResetPasswordController;
use Jul6Art\AdminBundle\DependencyInjection\AdminExtension;
use Jul6Art\AdminBundle\DependencyInjection\Configuration;
use Jul6Art\AdminBundle\Form\Security\NewPasswordFormType;
use Jul6Art\AdminBundle\Form\Security\ProfileFormType;
use Jul6Art\AdminBundle\Form\Security\RegistrationFormType;
use Jul6Art\AdminBundle\Form\Security\ResetPasswordRequestFormType;
use Jul6Art\AdminBundle\Tests\Account\Fixtures\Member;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * `admin.account`: off by default, and what it refuses to start without (ADR-0001, T20 lot 3).
 */
#[CoversClass(Configuration::class)]
#[CoversClass(AdminExtension::class)]
#[CoversClass(AccountSpaces::class)]
final class AccountConfigurationTest extends TestCase
{
    public function testTheScreensAreOffByDefault(): void
    {
        $account = $this->process([])['account'] ?? null;
        self::assertIsArray($account);
        self::assertFalse($account['enabled']);

        $container = $this->load([]);
        self::assertFalse($container->hasDefinition(LoginController::class), 'An application keeps its controllers until it turns the bundle\'s on.');
        self::assertFalse($container->hasDefinition(RegistrationController::class));
    }

    public function testTurnedOnTheyNeedTheAccountEntity(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('admin.account needs user_class');

        $this->process(['account' => ['enabled' => true]]);
    }

    public function testTheActiveFlagIsABooleanOrNothing(): void
    {
        $account = $this->process(['account' => ['enabled' => true, 'user_class' => Member::class, 'registration' => ['active' => null]]])['account'] ?? null;
        self::assertIsArray($account);
        self::assertIsArray($account['registration']);
        self::assertNull($account['registration']['active']);

        $this->expectException(InvalidConfigurationException::class);
        $this->process(['account' => ['enabled' => true, 'user_class' => Member::class, 'registration' => ['active' => 'yes']]]);
    }

    public function testAnAccountWithoutActiveFlagIsLeftAlone(): void
    {
        $container = $this->load(['account' => ['enabled' => true, 'user_class' => Member::class, 'reset_password' => false, 'registration' => ['active' => null]]]);
        self::assertNull($container->getDefinition(RegistrationController::class)->getArgument('$active'), '~ is no flag to write, not false.');

        $container = $this->load(['account' => ['enabled' => true, 'user_class' => Member::class, 'reset_password' => false]]);
        self::assertFalse($container->getDefinition(RegistrationController::class)->getArgument('$active'), 'Inactive by default.');
    }

    public function testTheForgottenPasswordNeedsASender(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('admin.account.reset_password.email.from is empty');

        $this->load(['account' => ['enabled' => true, 'user_class' => Member::class]]);
    }

    public function testWithoutTheForgottenPasswordNoSenderIsAsked(): void
    {
        $container = $this->load(['account' => ['enabled' => true, 'user_class' => Member::class, 'reset_password' => false]]);

        self::assertTrue($container->hasDefinition(LoginController::class));
        self::assertTrue($container->hasDefinition(RegistrationController::class));
        self::assertFalse($container->hasDefinition(ResetPasswordController::class));
    }

    public function testTheFormsTakeTheApplicationsCsrfTokenId(): void
    {
        $container = $this->load(['account' => ['enabled' => true, 'user_class' => Member::class, 'reset_password' => false]]);

        foreach ([RegistrationFormType::class, ProfileFormType::class, ResetPasswordRequestFormType::class, NewPasswordFormType::class] as $type) {
            self::assertTrue($container->getDefinition($type)->isAutoconfigured(), \sprintf('%s: FrameworkBundle\'s autoconfiguration gives it form.csrf_protection.token_id.', $type));
        }
    }

    public function testAnUnknownEntityIsRefusedByName(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('admin.account.user_class names no class: "App\Entity\Nobody"');

        $this->load(['account' => ['enabled' => true, 'user_class' => 'App\Entity\Nobody']]);
    }

    public function testASpaceDeclaresOnlyWhatDiffersFromAdmin(): void
    {
        $spaces = new AccountSpaces([
            'portal' => ['routes' => ['login' => 'portal_security_login'], 'context' => ['layout' => 'portal.html.twig'], 'redirect_authenticated_to' => 'portal_dashboard'],
        ]);
        $admin = new Request();
        $portal = new Request(attributes: [AccountSpaces::ATTRIBUTE => 'portal']);

        self::assertSame('admin_security_login', $spaces->route($admin, 'login'));
        self::assertSame('portal_security_login', $spaces->route($portal, 'login'));
        self::assertSame('admin_reset_password_request', $spaces->route($portal, 'reset_password_request'), 'Not declared: the admin space\'s.');
        self::assertSame('@Admin/security/login.html.twig', $spaces->template($portal, 'login'));
        self::assertSame(['space' => 'portal', 'layout' => 'portal.html.twig'], $spaces->context($portal));
        self::assertSame(['space' => 'admin'], $spaces->context($admin));
        self::assertSame('portal_dashboard', $spaces->redirectAuthenticatedTo($portal));
        self::assertNull($spaces->redirectAuthenticatedTo($admin));

        $withPage = new AccountSpaces(['admin' => ['routes' => ['profile_show' => 'admin_user_show']], 'portal' => ['routes' => ['profile_show' => '']], 'other' => []]);
        self::assertSame('admin_user_show', $withPage->optionalRoute($admin, 'profile_show'));
        self::assertNull($withPage->optionalRoute($portal, 'profile_show'), 'Declared empty: none in this space.');
        self::assertSame('admin_user_show', $withPage->optionalRoute(new Request(attributes: [AccountSpaces::ATTRIBUTE => 'other']), 'profile_show'), 'Not declared: the admin space\'s.');
        self::assertNull(new AccountSpaces()->optionalRoute($admin, 'profile_show'));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<mixed>
     */
    private function process(array $config): array
    {
        return new Processor()->processConfiguration(new Configuration(), [$config]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', \dirname(__DIR__, 2));
        $container->setParameter('kernel.bundles', []);
        new AdminExtension()->load([$config], $container);

        return $container;
    }
}
