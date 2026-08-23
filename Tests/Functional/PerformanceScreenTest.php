<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Jul6Art\AdminBundle\Controller\PerformanceController;
use Jul6Art\CoreBundle\Performance\Store\PerformanceStoreInterface;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * L'écran du profileur, et surtout : sa disparition propre.
 *
 * `admin-bundle` ne requiert PAS `core-bundle`. Une application peut prendre la coquille pour ses
 * pages d'authentification sans vouloir la brique de profilage — et son conteneur doit compiler.
 * Ce test prouve les DEUX cas, parce que seul le second casse silencieusement.
 */
#[CoversNothing]
final class PerformanceScreenTest extends AbstractFunctionalTestCase
{
    public function testTheScreenExistsWhenTheCoreBundleProvidesTheStore(): void
    {
        $container = $this->boot(withCore: true);

        self::assertTrue($container->has(PerformanceStoreInterface::class), 'Le core doit fournir le store.');

        /** @var list<string> $tagged */
        $tagged = (array) $container->getParameter('test.controller_service_arguments_tags');
        self::assertContains(
            PerformanceController::class,
            $tagged,
            'Le contrôleur doit être taggué comme contrôleur-service.',
        );
    }

    /**
     * ⚠️ Sans le core, le contrôleur doit être RETIRÉ. Laissé en place, il ferait échouer la
     * compilation sur `PerformanceStoreInterface` introuvable — en désignant un écran que le
     * projet n'a jamais demandé.
     */
    public function testTheScreenDisappearsWithoutTheCoreBundle(): void
    {
        $container = $this->boot();

        self::assertFalse($container->has(PerformanceStoreInterface::class));
        self::assertNotContains(
            PerformanceController::class,
            (array) $container->getParameter('test.controller_service_arguments_tags'),
            'Le contrôleur ne doit pas survivre à l\'absence de sa dépendance.',
        );
    }

    /**
     * Le préfixe de nom des routes est un CONTRAT avec le core : c'est lui que
     * `core.performance.ignored_route_prefix` exclut de la collecte. Les désynchroniser ferait
     * mesurer au tableau de bord sa propre page, à chaque visite.
     */
    public function testTheRouteNamePrefixMatchesWhatTheCoreIgnoresByDefault(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2).'/Controller/PerformanceController.php');

        self::assertSame(3, substr_count($source, "name: 'admin_performance_"), 'Les trois routes portent le préfixe que le core ignore.');
    }
}
