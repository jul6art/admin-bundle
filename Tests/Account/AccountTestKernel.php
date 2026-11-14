<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Account;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Jul6Art\AdminBundle\AdminBundle;
use Jul6Art\AdminBundle\Tests\Account\Fixtures\CapturingMailer;
use Jul6Art\AdminBundle\Tests\Account\Fixtures\Member;
use Jul6Art\AdminBundle\Tests\Account\Fixtures\ResetPasswordRequestRepository;
use Jul6Art\AuthBundle\AuthBundle;
use Jul6Art\CoreBundle\CoreBundle;
use Jul6Art\DataflowBundle\DataflowBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\TemplateController;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use SymfonyCasts\Bundle\ResetPassword\SymfonyCastsResetPasswordBundle;

/**
 * An application of the sign-in screens, as the skeleton's back-office wires them: its account entity, a firewall
 * with a form login, the routes it names — the `admin` space, and a customer portal.
 */
final class AccountTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<mixed> $account the "admin.account" configuration, beyond enabled and user_class
     */
    public function __construct(
        private readonly array $account = [],
    ) {
        parent::__construct('test', false);
    }

    #[\Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new TwigBundle();
        yield new SymfonyCastsResetPasswordBundle();
        yield new CoreBundle();
        yield new AuthBundle();
        yield new DataflowBundle();
        yield new AdminBundle();
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    #[\Override]
    public function getCacheDir(): string
    {
        return $this->buildDir().'/cache';
    }

    #[\Override]
    public function getLogDir(): string
    {
        return $this->buildDir().'/log';
    }

    public function buildDir(): string
    {
        return \sprintf('%s/jul6art-admin-bundle-account-tests/%s', sys_get_temp_dir(), substr(md5(serialize($this->account)), 0, 12));
    }

    private function configureContainer(ContainerBuilder $container): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'admin-bundle-account-tests',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'form' => ['csrf_protection' => ['token_id' => 'submit']],
            // Stateless, as in the applications: the bundle's forms must take the application's token id.
            'csrf_protection' => ['stateless_token_ids' => ['submit', 'authenticate', 'logout']],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file', 'handler_id' => null],
            'validation' => ['enabled' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
            'assets' => true,
            'rate_limiter' => ['sign_up' => ['policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 hour']],
        ]);

        $container->loadFromExtension('twig', [
            'strict_variables' => true,
            'paths' => [__DIR__.'/Fixtures/templates'],
        ]);

        $container->loadFromExtension('doctrine', [
            // A file, not memory: the browser reboots the kernel between two requests, the accounts must survive it.
            'dbal' => ['driver' => 'pdo_sqlite', 'path' => $this->buildDir().'/accounts.sqlite'],
            'orm' => [
                'controller_resolver' => ['auto_mapping' => false],
                'mappings' => [
                    'Account' => [
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Fixtures',
                        'prefix' => 'Jul6Art\AdminBundle\Tests\Account\Fixtures',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $container->loadFromExtension('symfonycasts_reset_password', [
            'request_password_repository' => ResetPasswordRequestRepository::class,
        ]);
        $container->register(ResetPasswordRequestRepository::class, ResetPasswordRequestRepository::class)
            ->setAutowired(true)
            ->addTag('doctrine.repository_service');

        $container->loadFromExtension('security', [
            'password_hashers' => [Member::class => ['algorithm' => 'plaintext']],
            'providers' => ['members' => ['entity' => ['class' => Member::class, 'property' => 'email']]],
            'firewalls' => [
                'portal' => [
                    'pattern' => '^/portal',
                    'lazy' => true,
                    'provider' => 'members',
                    'form_login' => ['login_path' => 'portal_security_login', 'check_path' => 'portal_security_login', 'enable_csrf' => true],
                    'logout' => ['path' => 'portal_security_logout'],
                ],
                'main' => [
                    'lazy' => true,
                    'provider' => 'members',
                    'form_login' => ['login_path' => 'admin_security_login', 'check_path' => 'admin_security_login', 'enable_csrf' => true, 'default_target_path' => 'admin_dashboard'],
                    'logout' => ['path' => 'admin_security_logout'],
                ],
            ],
        ]);

        $container->loadFromExtension('core', ['encryption_key' => base64_encode(str_repeat('k', 32))]);

        $container->loadFromExtension('admin', [
            'routes' => [
                'register' => 'admin_security_register',
                'reset_password_request' => 'admin_reset_password_request',
                'profile' => 'admin_account_profile_edit',
            ],
            'account' => array_replace_recursive([
                'enabled' => true,
                'user_class' => Member::class,
                'reset_password' => ['email' => ['from' => 'Demo <noreply@example.test>']],
                'spaces' => [
                    'portal' => [
                        'templates' => [
                            'login' => 'portal/security/login.html.twig',
                            'reset_password_request' => 'portal/security/reset_password_request.html.twig',
                            'check_email' => 'portal/security/check_email.html.twig',
                        ],
                        'routes' => [
                            'login' => 'portal_security_login',
                            'reset_password_request' => 'portal_reset_password_request',
                            'reset_password_check_email' => 'portal_reset_password_check_email',
                            'reset_password_reset' => 'portal_reset_password_reset',
                        ],
                        'redirect_authenticated_to' => 'portal_dashboard',
                    ],
                ],
            ], $this->account),
        ]);

        // The accounts' import (1.44), on dataflow-bundle's screens: the right is a role here, the voter of a role.
        $container->loadFromExtension('dataflow', ['import' => ['enabled' => true, 'directory' => $this->buildDir().'/import', 'layout' => 'layout.html.twig']]);
        $container->loadFromExtension('admin', ['user_import' => [
            'enabled' => true,
            'user_class' => Member::class,
            'roles' => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
            'permission' => 'ROLE_ADMIN',
        ]]);

        $container->register(MailerInterface::class, CapturingMailer::class)->setPublic(true);
        // The 404 of a closed sign-up is expected: no logger line on the test's output.
        $container->register('logger', NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $screens = 'Jul6Art\AdminBundle\Controller\Security\\';
        $routes->add('admin_security_login', '/login')->controller($screens.'LoginController::login')->methods(['GET', 'POST']);
        $routes->add('admin_security_logout', '/logout')->controller($screens.'LoginController::logout')->methods(['POST']);
        $routes->add('admin_security_register', '/register')->controller($screens.'RegistrationController')->methods(['GET', 'POST']);
        $routes->add('admin_reset_password_request', '/reset-password')->controller($screens.'ResetPasswordController::request')->methods(['GET', 'POST']);
        $routes->add('admin_reset_password_check_email', '/reset-password/check-email')->controller($screens.'ResetPasswordController::checkEmail')->methods(['GET']);
        $routes->add('admin_reset_password_reset', '/reset-password/reset/{token}')->controller($screens.'ResetPasswordController::reset')->defaults(['token' => null])->methods(['GET', 'POST']);
        $routes->add('admin_account_profile_edit', '/admin/account/profile')->controller($screens.'AccountProfileController::edit')->methods(['GET', 'POST']);
        $routes->add('app_account_profile_edit', '/app/account/profile')->controller($screens.'AccountProfileController::edit')->defaults(['_auth_space' => 'customer'])->methods(['GET', 'POST']);
        $routes->add('admin_dashboard', '/admin')->controller(TemplateController::class)->defaults(['template' => 'admin/account/profile.html.twig']);
        $routes->add('admin_user_show', '/admin/users/{id}')->controller('Symfony\Bundle\FrameworkBundle\Controller\RedirectController::urlRedirectAction')->defaults(['path' => '/admin']);

        $import = 'Jul6Art\DataflowBundle\Import\Web\ImportController::';
        $routes->add('admin_user_index', '/admin/users')->controller(TemplateController::class)->defaults(['template' => 'layout.html.twig']);
        $routes->add('admin_user_import_new', '/admin/users/import/new')->controller($import.'upload')->defaults(['_import' => 'user'])->methods(['GET', 'POST']);
        $routes->add('admin_user_import_mapping', '/admin/users/import/mapping')->controller($import.'mapping')->defaults(['_import' => 'user'])->methods(['GET']);
        $routes->add('admin_user_import_execute', '/admin/users/import/execute')->controller($import.'run')->defaults(['_import' => 'user'])->methods(['POST']);

        $portal = ['_auth_space' => 'portal'];
        $routes->add('portal_security_login', '/portal/login')->controller($screens.'LoginController::login')->defaults($portal)->methods(['GET', 'POST']);
        $routes->add('portal_security_logout', '/portal/logout')->controller($screens.'LoginController::logout')->defaults($portal)->methods(['POST']);
        $routes->add('portal_reset_password_request', '/portal/reset-password')->controller($screens.'ResetPasswordController::request')->defaults($portal)->methods(['GET', 'POST']);
        $routes->add('portal_reset_password_check_email', '/portal/reset-password/check-email')->controller($screens.'ResetPasswordController::checkEmail')->defaults($portal)->methods(['GET']);
        $routes->add('portal_reset_password_reset', '/portal/reset-password/reset/{token}')->controller($screens.'ResetPasswordController::reset')->defaults($portal + ['token' => null])->methods(['GET', 'POST']);
        $routes->add('portal_dashboard', '/portal')->controller('Symfony\Bundle\FrameworkBundle\Controller\RedirectController::urlRedirectAction')->defaults(['path' => '/portal/login']);
    }
}
