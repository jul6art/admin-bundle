<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Jul6Art\AdminBundle\Controller\AppearanceController;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The first test to write, and the one that keeps paying: a real container, built with the bundle
 * registered.
 *
 * It catches what no unit test can — a services.yaml that does not parse, a reference to a service
 * that does not exist, a configuration node the extension reads under another name. Every one of
 * those is invisible until something boots.
 */
#[CoversNothing]
final class ContainerTest extends AbstractFunctionalTestCase
{
    public function testTheBundleBoots(): void
    {
        self::assertTrue($this->boot()->getParameter('admin.enabled'));
    }

    /**
     * `enabled: false` must leave the bundle installed and inert — an application should be able
     * to switch it off without uninstalling it, and without its optional dependencies becoming
     * required.
     */
    public function testItCanBeDisabled(): void
    {
        self::assertFalse($this->boot('test', ['enabled' => false])->hasParameter('admin.enabled'));
    }

    /**
     * ⚠️ Le contrôleur d'apparence ne doit JAMAIS porter le tag `routing.controller`.
     *
     * L'autoconfiguration de FrameworkBundle le pose sur toute classe à attribut `#[Route]`, et le
     * loader `routing.controllers` — le `config/routes.yaml` des recipes Symfony ≥ 7.3 — importe
     * alors la route automatiquement, SANS le préfixe que l'application avait choisi :
     * `/account/appearance` au lieu de `/admin/account/appearance`, hors des `access_control` qui
     * visent le préfixe. D'où `autoconfigure: false` + les deux tags posés à la main dans
     * `controller.yaml`.
     */
    public function testTheAppearanceControllerIsNotAutoImportedByTheRoutingLoader(): void
    {
        // `withOrm: true` : sans Doctrine, le pass retire le contrôleur et il n'y a rien à tester.
        $container = $this->boot(withOrm: true);

        self::assertNotContains(
            AppearanceController::class,
            (array) $container->getParameter('test.routing_controller_tags'),
            'La route doit naître de l\'import explicite du projet, pas de l\'autoconfiguration.',
        );
        self::assertContains(
            AppearanceController::class,
            (array) $container->getParameter('test.controller_service_arguments_tags'),
        );
    }
}
