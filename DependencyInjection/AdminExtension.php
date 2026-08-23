<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\AdminBundle\Ui\Branding;
use Jul6Art\CoreBundle\Performance\Store\PerformanceStoreInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Form\AbstractType;
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
class AdminExtension extends Extension
{
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
            ->setArgument('$showName', !\is_bool($branding['show_name'] ?? null) || $branding['show_name']);

        // Les noms de route et les deux valeurs Mercure atteignent les gabarits par des paramètres
        // de conteneur plutôt que par un global Twig : un gabarit du bundle peut les lire, et
        // `debug:container --parameter` dit la vérité sur ce qui est branché.
        $routes = \is_array($config['routes'] ?? null) ? $config['routes'] : [];
        foreach (['login', 'logout', 'register', 'reset_password_request', 'profile', 'change_password', 'appearance', 'privacy', 'performance'] as $key) {
            $container->setParameter('admin.route.'.$key, self::asString($routes[$key] ?? null, ''));
        }

        $mercure = \is_array($config['mercure'] ?? null) ? $config['mercure'] : [];
        $container->setParameter('admin.mercure.hub_url', self::asString($mercure['hub_url'] ?? null, ''));
        $container->setParameter('admin.mercure.token_route', self::asString($mercure['token_route'] ?? null, ''));

        if (class_exists(Environment::class)) {
            $loader->load('twig.yaml');
        }

        // L'écran du profileur vient de `jul6art/core-bundle`, que ce bundle ne requiert pas :
        // une application peut prendre la coquille sans la brique de profilage. Le pass affine
        // ensuite — la classe peut exister sans que le service soit enregistré.
        if (interface_exists(PerformanceStoreInterface::class)) {
            $loader->load('performance.yaml');
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

    private static function asString(mixed $value, string $fallback): string
    {
        return \is_string($value) && '' !== $value ? $value : $fallback;
    }
}
