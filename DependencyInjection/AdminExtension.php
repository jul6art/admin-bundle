<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Jul6Art\AdminBundle\Contract\DeadlineInterface;
use Jul6Art\AdminBundle\Controller\DeadlineController;
use Jul6Art\AdminBundle\Controller\TwoFactorController;
use Jul6Art\AdminBundle\Deadline\DeadlineNotifier;
use Jul6Art\AdminBundle\TwoFactor\ExemptRoutesCondition;
use Jul6Art\AdminBundle\Ui\Branding;
use Jul6Art\AdminBundle\Ui\EmailPalette;
use Jul6Art\CoreBundle\Performance\Store\PerformanceStoreInterface;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Twig\Environment;

/**
 * Wires the bundle's services and turns its configuration into something the services can read.
 *
 * Two rules this ecosystem arrived at the hard way:
 *
 * 1. **A brick whose dependency is optional is registered conditionally**, from here, guarded by
 *    `class_exists()` / `interface_exists()` — never by an attribute on the class. An
 *    `#[AsDecorator]` or `#[AsDoctrineListener]` on a vendor class is only honoured if the
 *    application autoconfigures `vendor/`, which it should not, and it makes the class
 *    unloadable when the package is absent.
 * 2. **A service that needs another *service* to exist is checked in a compiler pass**, not
 *    here: an extension runs before the other bundles have configured anything, so
 *    `$container->has('some.service')` is always false at this point.
 *
 * Here that means: the shell (Twig) and the settings screen (Form + Doctrine) are registered only
 * when their components are installed, and the navigation's feature checker — which the
 * application implements, or does not — is settled by a compiler pass.
 */
class AdminExtension extends Extension implements PrependExtensionInterface
{
    /** The limiter of the second factor: five failures per quarter of an hour and per account. */
    public const string TWO_FACTOR_LIMITER = 'admin_two_factor';

    /**
     * `admin.two_factor.enabled` configures scheb/2fa-bundle and the limiter FOR the application:
     * the TOTP provider, its sign-in template, the tokens it applies to, the routes it skips, the
     * replay protection. What only the application can say stays in its files — the `two_factor`
     * block of its firewall, and the routes it imports.
     */
    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig('admin'));
        $twoFactor = \is_array($config['two_factor'] ?? null) ? $config['two_factor'] : [];
        if (true !== ($config['enabled'] ?? true) || true !== ($twoFactor['enabled'] ?? false) || !$container->hasExtension('scheb_two_factor')) {
            return;
        }

        $branding = \is_array($config['branding'] ?? null) ? $config['branding'] : [];
        $container->prependExtensionConfig('scheb_two_factor', [
            // Le formulaire de connexion et les authentificateurs personnalisés (lien de connexion
            // compris) ; jamais le jeton « se souvenir de moi », ni celui d'une API sans état.
            'security_tokens' => [UsernamePasswordToken::class, PostAuthenticationToken::class],
            'two_factor_condition' => ExemptRoutesCondition::class,
            // Un code accepté ne resservira pas pendant sa fenêtre : rejouer un code intercepté échoue.
            'code_reuse_cache' => 'cache.app',
            'code_reuse_cache_duration' => 90,
            'totp' => [
                'enabled' => true,
                'issuer' => self::asString($twoFactor['issuer'] ?? null, self::asString($branding['name'] ?? null, 'Admin')),
                // Cinq secondes de tolérance au passage d'une fenêtre de 30 s : une horloge de
                // téléphone un peu en retard ne bloque pas une connexion, sans rouvrir l'ancien code.
                'leeway' => 5,
                'template' => '@Admin/security/two_factor.html.twig',
            ],
            // Les codes de secours : scheb les vérifie à la connexion, avant le code TOTP. Sans leur
            // paquet, rien n'est allumé ici : `load()` lève alors en nommant la commande à lancer.
            'backup_codes' => ['enabled' => true === ($twoFactor['backup_codes'] ?? false) && interface_exists(BackupCodeInterface::class)],
        ]);
        $container->prependExtensionConfig('framework', [
            'rate_limiter' => [self::TWO_FACTOR_LIMITER => ['policy' => 'sliding_window', 'limit' => 5, 'interval' => '15 minutes']],
        ]);
    }

    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        if (false === ($config['enabled'] ?? true)) {
            return;
        }

        $container->setParameter('admin.enabled', true);
        $container->setParameter('admin.base_template', self::asString($config['base_template'] ?? null, '@Admin/base.html.twig'));
        $container->setParameter('admin.layout_template', self::asString($config['layout_template'] ?? null, '@Admin/layout.html.twig'));

        $branding = \is_array($config['branding'] ?? null) ? $config['branding'] : [];
        $container->getDefinition(Branding::class)
            ->setArgument('$name', self::asString($branding['name'] ?? null, 'Admin'))
            ->setArgument('$logo', self::asString($branding['logo'] ?? null, ''))
            ->setArgument('$favicon', self::asString($branding['favicon'] ?? null, ''))
            ->setArgument('$homeRoute', self::asString($branding['home_route'] ?? null, 'admin_dashboard'))
            ->setArgument('$logoWidth', \is_int($branding['logo_width'] ?? null) ? $branding['logo_width'] : null)
            ->setArgument('$showName', !\is_bool($branding['show_name'] ?? null) || $branding['show_name'])
            ->setArgument('$logoDark', self::asString($branding['logo_dark'] ?? null, ''))
            ->setArgument('$email', self::emailPalette($branding['email'] ?? null));

        // Les noms de route et les deux valeurs Mercure atteignent les gabarits par des paramètres
        // de conteneur plutôt que par un global Twig : un gabarit du bundle peut les lire, et
        // `debug:container --parameter` dit la vérité sur ce qui est branché.
        $routes = \is_array($config['routes'] ?? null) ? $config['routes'] : [];
        foreach (['login', 'logout', 'register', 'reset_password_request', 'profile', 'change_password', 'appearance', 'privacy', 'performance'] as $key) {
            $container->setParameter('admin.route.'.$key, self::asString($routes[$key] ?? null, ''));
        }

        $twoFactor = \is_array($config['two_factor'] ?? null) ? $config['two_factor'] : [];
        $twoFactorEnabled = true === ($twoFactor['enabled'] ?? false);
        // L'entrée du menu de compte n'existe que si l'application PROPOSE le second facteur.
        $container->setParameter('admin.route.two_factor', $twoFactorEnabled ? self::asString($routes['two_factor'] ?? null, '') : '');
        if ($twoFactorEnabled) {
            $this->loadTwoFactor($loader, $container, $twoFactor, self::asString($routes['logout'] ?? null, ''));
        }

        $guide = \is_array($config['user_guide'] ?? null) ? $config['user_guide'] : [];
        $container->setParameter('admin.user_guide.route', self::asString($guide['route'] ?? null, ''));
        $container->setParameter('admin.user_guide.url', self::asString($guide['url'] ?? null, ''));

        $mercure = \is_array($config['mercure'] ?? null) ? $config['mercure'] : [];
        $container->setParameter('admin.mercure.hub_url', self::asString($mercure['hub_url'] ?? null, ''));
        $container->setParameter('admin.mercure.token_route', self::asString($mercure['token_route'] ?? null, ''));

        if (class_exists(Environment::class)) {
            $loader->load('twig.yaml');

            // ⚠️ Le clavier ne se charge qu'AVEC Twig, et c'est exact plutôt que prudent : ses
            // trois services n'existent que pour trois fonctions de gabarit. Une application qui
            // rend sa coquille autrement n'a rien à router.
            $keyboard = \is_array($config['keyboard'] ?? null) ? $config['keyboard'] : [];

            if (!\is_bool($keyboard['enabled'] ?? null) || $keyboard['enabled']) {
                /** @var array<string, array{default: string, label: string}> $actions */
                $actions = \is_array($keyboard['actions'] ?? null) ? $keyboard['actions'] : [];
                $container->setParameter('admin.keyboard.actions', $actions);
                $loader->load('keyboard.yaml');
            }
        }

        // L'écran du profileur vient de `jul6art/core-bundle`, que ce bundle ne requiert pas :
        // une application peut prendre la coquille sans la brique de profilage. Le pass affine
        // ensuite — la classe peut exister sans que le service soit enregistré.
        if (interface_exists(EntityManagerInterface::class)) {
            $loader->load('search.yaml');
        }

        if (interface_exists(PerformanceStoreInterface::class)) {
            $loader->load('performance.yaml');
        }

        $deadlines = \is_array($config['deadlines'] ?? null) ? $config['deadlines'] : [];
        if ('' !== self::asString($deadlines['entity'] ?? null, '')) {
            $this->loadDeadlines($loader, $container, $deadlines);
        }

        // Le formulaire d'apparence n'a de sens qu'avec le composant Form ; son contrôleur ajoute
        // Doctrine. Les deux sont des `suggest` : un projet peut vouloir la coquille sans l'écran
        // de préférences.
        if (class_exists(AbstractType::class)) {
            $loader->load('form.yaml');

            if (interface_exists(EntityManagerInterface::class)) {
                $loader->load('controller.yaml');
            }
        }
    }

    /**
     * The e-mail palette as a service argument: an inline definition — a container argument cannot
     * be an arbitrary object — built from the validated `branding.email` node, defaults filling in.
     */
    private static function emailPalette(mixed $email): Definition
    {
        $email = \is_array($email) ? $email : [];
        $value = static fn (string $key): string => self::asString($email[$key] ?? null, EmailPalette::DEFAULTS[$key]);

        return new Definition(EmailPalette::class, [
            $value('background'),
            $value('panel'),
            $value('border'),
            $value('text'),
            $value('heading'),
            $value('muted'),
            $value('accent'),
            $value('accent_text'),
            $value('logo'),
        ]);
    }

    /**
     * The second factor's services. Its four packages are `suggest`s, so turning the option on
     * without them fails HERE, with the command to run, rather than at the first sign-in.
     *
     * @param array<array-key, mixed> $twoFactor
     */
    private function loadTwoFactor(YamlFileLoader $loader, ContainerBuilder $container, array $twoFactor, string $logoutRoute): void
    {
        $missing = array_keys(array_filter([
            'scheb/2fa-bundle scheb/2fa-totp' => !interface_exists(TwoFactorInterface::class),
            'scheb/2fa-backup-code' => true === ($twoFactor['backup_codes'] ?? false) && !interface_exists(BackupCodeInterface::class),
            'endroid/qr-code' => !class_exists(Builder::class),
            'symfony/rate-limiter' => !class_exists(RateLimiterFactory::class),
            'symfony/form' => !class_exists(AbstractType::class),
            'doctrine/orm' => !interface_exists(EntityManagerInterface::class),
        ]));
        if ([] !== $missing) {
            throw new \LogicException(\sprintf('admin.two_factor.enabled needs: composer require %s', implode(' ', $missing)));
        }
        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];
        if (!\is_array($bundles) || !\array_key_exists('SchebTwoFactorBundle', $bundles)) {
            throw new \LogicException('admin.two_factor.enabled needs Scheb\\TwoFactorBundle\\SchebTwoFactorBundle in config/bundles.php (Symfony Flex adds it on `composer require scheb/2fa-bundle`).');
        }

        $exempt = self::stringList($twoFactor['exempt_routes'] ?? []);
        $container->setParameter('admin.two_factor.required', true === ($twoFactor['required'] ?? false));
        $container->setParameter('admin.two_factor.backup_codes', true === ($twoFactor['backup_codes'] ?? false));
        $container->setParameter('admin.two_factor.exempt_routes', $exempt);
        $loader->load('two_factor.yaml');
        if (true === ($twoFactor['backup_codes'] ?? false)) {
            $loader->load('two_factor_backup_codes.yaml');
        }

        if (true === ($twoFactor['required'] ?? false)) {
            // Ce qu'un compte sans second facteur atteint encore : son écran d'enrôlement, la
            // sortie, la saisie du code elle-même, et ce que l'application ajoute.
            $enrolment = self::asString($twoFactor['enrolment_route'] ?? null, TwoFactorController::ROUTE);
            $container->setParameter('admin.two_factor.enrolment_route', $enrolment);
            $container->setParameter('admin.two_factor.open_routes', array_values(array_unique(array_filter([
                $enrolment,
                $logoutRoute,
                '2fa_login',
                '2fa_login_check',
                ...$exempt,
                ...self::stringList($twoFactor['open_routes'] ?? []),
            ]))));
            $loader->load('two_factor_required.yaml');
        }
    }

    /**
     * The deadlines' screen and reminders. Their packages are `suggest`s, so naming an entity without
     * them fails HERE, with the command to run, rather than on the first morning.
     *
     * @param array<array-key, mixed> $deadlines
     */
    private function loadDeadlines(YamlFileLoader $loader, ContainerBuilder $container, array $deadlines): void
    {
        $missing = array_keys(array_filter([
            'doctrine/orm' => !interface_exists(EntityManagerInterface::class),
            'symfony/mailer' => !interface_exists(MailerInterface::class),
            'symfony/form' => !class_exists(AbstractType::class),
            'twig/twig' => !class_exists(Environment::class),
        ]));
        if ([] !== $missing) {
            throw new \LogicException(\sprintf('admin.deadlines needs: composer require %s', implode(' ', $missing)));
        }
        $entity = self::asString($deadlines['entity'] ?? null, '');
        if (!is_a($entity, DeadlineInterface::class, true)) {
            throw new \LogicException(\sprintf('admin.deadlines.entity must name a class implementing %s (with DeadlineTrait): "%s" does not.', DeadlineInterface::class, $entity));
        }

        $loader->load('deadlines.yaml');
        $recipients = $deadlines['recipients'] ?? [];
        $container->getDefinition(DeadlineNotifier::class)
            ->setArgument('$entityClass', $entity)
            ->setArgument('$recipients', \is_string($recipients) ? $recipients : self::stringList($recipients))
            ->setArgument('$from', \is_string($deadlines['from'] ?? null) ? $deadlines['from'] : '')
            ->setArgument('$locale', self::asString($deadlines['locale'] ?? null, 'en'))
            ->setArgument('$timezone', \is_string($deadlines['timezone'] ?? null) ? $deadlines['timezone'] : '');
        // La décision d'accès de l'écran, aussi en paramètre : le test de menu d'une application la compare à
        // celle de l'entrée qui y mène — l'écran ne la porte pas en `#[IsGranted]`, elle se configure.
        $container->setParameter('admin.deadlines.access', self::asString($deadlines['access'] ?? null, 'ROLE_SUPER_ADMIN'));
        $container->getDefinition(DeadlineController::class)
            ->setArgument('$entityClass', $entity)
            ->setArgument('$access', '%admin.deadlines.access%');
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        return \is_array($value) ? array_values(array_filter($value, \is_string(...))) : [];
    }

    private static function asString(mixed $value, string $fallback): string
    {
        return \is_string($value) && '' !== $value ? $value : $fallback;
    }
}
