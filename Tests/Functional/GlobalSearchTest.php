<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AdminBundle\Search\GlobalSearch;
use Jul6Art\AdminBundle\Search\GlobalSearchSourceInterface;
use Jul6Art\AdminBundle\Search\SearchResult;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Account;
use Jul6Art\AdminBundle\Tests\Fixtures\RecordingSearchSource;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The header search engine — moved here from three products that each carried it (cereezer
 * ADR-0031, superp ADR-0012, cegeta ADR-0037).
 *
 * What the engine owns, and these cases pin: the two-character threshold, the comparison
 * (`LOWER(field) LIKE LOWER('%term%')`, the one `api-bundle`'s `OrSearchFilter` makes, so a panel
 * and the list it links to count alike), five rows, a `COUNT` only when saturated, the order, the
 * "see all" URL, the JSON shape. What it does NOT own: which families, who may search them, and
 * the tenant — that is the product's source, and the case with a scope proves the engine applies it.
 */
#[CoversNothing]
final class GlobalSearchTest extends AbstractFunctionalTestCase
{
    private EntityManagerInterface $entityManager;

    private UrlGeneratorInterface $urls;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->boot(withOrm: true);
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $urls = $container->get('router');
        self::assertInstanceOf(UrlGeneratorInterface::class, $urls);
        $this->entityManager = $entityManager;
        $this->urls = $urls;

        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testTheEngineIsRemovedWithoutDoctrine(): void
    {
        self::assertFalse($this->boot()->has(GlobalSearch::class), 'Sans DoctrineBundle, le moteur ne doit pas casser la compilation : il disparaît.');
    }

    public function testBelowTwoCharactersNothingIsAskedOfTheSource(): void
    {
        $source = $this->source();

        self::assertSame([], $this->engine($source)->search(' a '));
        self::assertSame(0, $source->asked, 'Sous le seuil, les familles ne sont même pas demandées : ni permission, ni requête.');
    }

    public function testFieldsAreSearchedCaseInsensitivelyWithinTheFamily(): void
    {
        $this->accounts(['ada@lovelace.test' => 'Ada Lovelace', 'grace@hopper.test' => 'Grace Hopper', 'alan@turing.test' => 'Alan TURING']);

        $groups = $this->engine($this->source())->search('turing');

        self::assertSame(['accounts'], array_keys($groups));
        self::assertSame(1, $groups['accounts']->total);
        self::assertSame('Alan TURING', $groups['accounts']->results[0]->label);
        self::assertMatchesRegularExpression('#^/admin/widgets/\d+$#', $groups['accounts']->results[0]->url);
        self::assertSame([], $this->engine($this->source())->search('nobody'), 'Une famille vide est omise.');
    }

    /**
     * ⚠️ **The comparison lowers BOTH sides**, and it has to be read from the query: the test
     * database is SQLite, whose `LIKE` already ignores ASCII case — removing both `LOWER()` keeps
     * every case above green there and breaks every uppercase keystroke on PostgreSQL.
     */
    public function testTheComparisonLowersTheFieldAndTheTerm(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2).'/Search/GlobalSearch.php');

        self::assertStringContainsString("'LOWER(e.%s) LIKE LOWER(:term)'", $source);
    }

    public function testFiveRowsAtMostTheRealTotalAndAnOrderedPanel(): void
    {
        $rows = [];
        foreach (['delta', 'Alpha', 'charlie', 'Echo', 'bravo', 'foxtrot', 'golf'] as $name) {
            $rows[$name.'@team.test'] = $name.' Team';
        }
        $this->accounts($rows);

        $group = $this->engine($this->source())->search('team')['accounts'];

        self::assertCount(5, $group->results, 'L\'en-tête aiguille, il ne liste pas.');
        self::assertSame(7, $group->total, 'Le total est le vrai décompte, pour « sur 7 ».');
        self::assertSame(
            ['Alpha Team', 'bravo Team', 'charlie Team', 'delta Team', 'Echo Team'],
            array_map(static fn (SearchResult $result): string => $result->label, $group->results),
            'Ordonné sans casse, comme `CaseInsensitiveOrderFilter` ordonne la liste.',
        );
    }

    public function testTheSeeAllUrlIsTheListFilteredByTheSameTerm(): void
    {
        $this->accounts(['ada@lovelace.test' => 'Ada Lovelace']);

        self::assertSame('/admin/widgets?search=ada', $this->engine($this->source(listRoute: 'admin_widget_index'))->search('ada')['accounts']->url);
        self::assertNull($this->engine($this->source())->search('ada')['accounts']->url, 'Sans route de liste, pas de lien.');
    }

    /**
     * The tenant is the SOURCE's: the engine must apply what it is handed, or a product's scope is a
     * line that reads right and filters nothing.
     */
    public function testTheSourcesScopeNarrowsTheQuery(): void
    {
        $this->accounts(['ada@alpha.test' => 'Ada One', 'ada@beta.test' => 'Ada Two']);

        $group = $this->engine($this->source(scope: "e.email LIKE '%@alpha.test'"))->search('ada')['accounts'];

        self::assertSame(1, $group->total);
        self::assertSame('Ada One', $group->results[0]->label);
    }

    public function testASourceWithNoFamilySearchesNothing(): void
    {
        $this->accounts(['ada@lovelace.test' => 'Ada Lovelace']);

        self::assertSame([], $this->engine($this->source(families: false))->search('ada'));
    }

    public function testTheJsonCarriesTheTotalTheSeeAllUrlAndLabelUrlPairsOnly(): void
    {
        $this->accounts(['ada@lovelace.test' => 'Ada Lovelace']);

        $payload = json_decode((string) $this->engine($this->source(listRoute: 'admin_widget_index'))->respond('ada')->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertSame(['accounts'], array_keys($payload));
        $group = $payload['accounts'];
        self::assertIsArray($group);
        self::assertSame(['total', 'url', 'results'], array_keys($group));
        self::assertIsArray($group['results']);
        self::assertIsArray($group['results'][0]);
        self::assertSame(['label', 'url'], array_keys($group['results'][0]));
        self::assertSame('[]', (string) $this->engine($this->source())->respond('x')->getContent(), 'Rien trouvé : un tableau vide, que le contrôleur du panneau lit comme « aucun résultat ».');
    }

    public function testTheEngineIsWiredWhenDoctrineIsThere(): void
    {
        // The engine is private — a product's controller only injects it; `TestKernel` exposes it.
        $container = $this->boot(withOrm: true);

        self::assertTrue($container->has(GlobalSearch::class));
        $engine = $container->get(GlobalSearch::class);
        self::assertInstanceOf(GlobalSearch::class, $engine);
        self::assertSame([], $engine->search('ada'), 'Sans source branchée, la recherche ne rend rien — elle ne devine aucune famille.');
    }

    /**
     * @param array<string, string> $rows email → full name
     */
    private function accounts(array $rows): void
    {
        foreach ($rows as $email => $name) {
            $this->entityManager->persist(new Account()->setEmail($email)->setFullName($name));
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function engine(GlobalSearchSourceInterface $source): GlobalSearch
    {
        return new GlobalSearch($this->entityManager, $this->urls, $source);
    }

    private function source(?string $listRoute = null, ?string $scope = null, bool $families = true): RecordingSearchSource
    {
        return new RecordingSearchSource($listRoute, $scope, $families);
    }
}
