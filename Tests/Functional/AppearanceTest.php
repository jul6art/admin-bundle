<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AdminBundle\Appearance\AccentColor;
use Jul6Art\AdminBundle\Appearance\ColorMode;
use Jul6Art\AdminBundle\Appearance\DisplayDensity;
use Jul6Art\AdminBundle\Appearance\FontScale;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Account;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class AppearanceTest extends AbstractFunctionalTestCase
{
    /**
     * Le point de tout l'exercice : le trait doit produire EXACTEMENT les colonnes `appearance_*`
     * qu'un embeddable `columnPrefix: false` produisait, sinon l'adoption coûte une migration à
     * chaque application qui en vient.
     */
    public function testTheTraitMapsTheAppearanceColumns(): void
    {
        $metadata = $this->entityManager()->getClassMetadata(Account::class);

        foreach ([
            'accent' => 'appearance_accent',
            'density' => 'appearance_density',
            'fontScale' => 'appearance_font_scale',
            'highContrast' => 'appearance_high_contrast',
            'reducedMotion' => 'appearance_reduced_motion',
        ] as $field => $column) {
            self::assertSame($column, $metadata->getColumnName($field));
        }
    }

    public function testTheEnumColumnsAreMappedAsEnums(): void
    {
        $metadata = $this->entityManager()->getClassMetadata(Account::class);

        self::assertSame(AccentColor::class, $metadata->getFieldMapping('accent')->enumType);
        self::assertSame(DisplayDensity::class, $metadata->getFieldMapping('density')->enumType);
        self::assertSame(FontScale::class, $metadata->getFieldMapping('fontScale')->enumType);
    }

    /**
     * Les défauts sont déclarés deux fois — initialiseur PHP et `options['default']` — et les deux
     * comptent : le premier pour un `new Account()`, le second pour une ligne insérée autrement et
     * pour que `doctrine:schema:validate` reste synchrone.
     */
    public function testTheDefaultsAreDeclaredOnBothSides(): void
    {
        $account = new Account();

        // ⚠️ `Brand` depuis le 2026-09-08, et non `Indigo` : l'ancien défaut faisait écrire
        // `data-accent` sur TOUT compte de tout produit, donc la rampe de marque qu'un
        // consommateur déclare sur `:root` ne s'appliquait jamais. Voir le docbloc du trait.
        self::assertSame(AccentColor::Brand, $account->getAccent());
        self::assertSame(DisplayDensity::Comfortable, $account->getDensity());
        self::assertSame(FontScale::Md, $account->getFontScale());
        self::assertFalse($account->isHighContrast());
        self::assertFalse($account->isReducedMotion());

        $metadata = $this->entityManager()->getClassMetadata(Account::class);
        self::assertSame('brand', $this->columnDefault($metadata, 'accent'));
        self::assertSame('comfortable', $this->columnDefault($metadata, 'density'));
        self::assertSame('md', $this->columnDefault($metadata, 'fontScale'));
    }

    public function testPreferencesRoundTripThroughTheDatabase(): void
    {
        $entityManager = $this->entityManager();
        $this->createSchema($entityManager);

        $account = new Account()
            ->setEmail('a@example.test')
            ->setFullName('Ada Lovelace')
            ->setAccent(AccentColor::Teal)
            ->setDensity(DisplayDensity::Compact)
            ->setFontScale(FontScale::Lg)
            ->setHighContrast(true)
            ->setColorMode(ColorMode::Dark);

        $entityManager->persist($account);
        $entityManager->flush();
        $entityManager->clear();

        $reloaded = $entityManager->getRepository(Account::class)->findOneBy(['email' => 'a@example.test']);
        self::assertInstanceOf(Account::class, $reloaded);

        self::assertSame(AccentColor::Teal, $reloaded->getAccent());
        self::assertSame(DisplayDensity::Compact, $reloaded->getDensity());
        self::assertSame(FontScale::Lg, $reloaded->getFontScale());
        self::assertTrue($reloaded->isHighContrast());
        self::assertFalse($reloaded->isReducedMotion());

        // Le mode de couleur passe par la colonne que l'application possédait déjà — c'est
        // précisément pour cela qu'il n'est pas dans le trait.
        self::assertSame(ColorMode::Dark, $reloaded->getColorMode());
        self::assertSame('dark', $reloaded->getTheme());
    }

    /**
     * Une valeur inconnue en base ne doit pas rendre la page. Elle peut venir d'une écriture
     * manuelle ou d'un temps où l'ensemble n'était pas fermé, et la préférence de couleur d'un
     * compte ne vaut pas une 500.
     */
    public function testAnUnknownStoredColorModeFallsBackToLight(): void
    {
        self::assertSame(ColorMode::Light, ColorMode::fromStorage('solarized'));
        self::assertSame(ColorMode::Light, ColorMode::fromStorage(null));
        self::assertSame(ColorMode::System, ColorMode::fromStorage('system'));
    }

    /**
     * ⚠️ **`Brand` est l'EXCEPTION, et elle est nommée** : le bundle ne peut pas connaître la
     * couleur d'un produit, donc son échantillon est une propriété personnalisée que le
     * consommateur déclare, avec l'indigo du bundle en repli — la même valeur vers laquelle la
     * rampe `:root` retombe, pour qu'un produit qui ne déclare ni l'une ni l'autre reste cohérent.
     *
     * ⚠️ Le repli est vérifié comme une VRAIE couleur : un `var()` sans repli rendrait l'échantillon
     * transparent chez un consommateur qui n'a rien déclaré, et un point invisible dans un
     * sélecteur de couleur se lit comme un défaut d'affichage.
     */
    public function testEveryAccentCarriesAReferenceSwatch(): void
    {
        foreach (AccentColor::cases() as $accent) {
            if (AccentColor::Brand === $accent) {
                self::assertSame('var(--brand-swatch, #6366f1)', $accent->swatch());

                continue;
            }

            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $accent->swatch(), $accent->value);
        }
    }

    /**
     * ⚠️ **Le bundle ne déclare AUCUNE rampe pour `brand`**, et c'est la propriété qui rend le cas
     * utile : si une version future en ajoutait une, chaque consommateur se verrait imposer une
     * couleur au lieu de déclarer la sienne — exactement le défaut que `Brand` existe pour
     * corriger. Le repli est la rampe `:root`, qui reste indigo.
     */
    public function testTheBundleShipsNoRampForTheBrandAccent(): void
    {
        $tokens = (string) file_get_contents(__DIR__.'/../../assets/styles/tokens.css');

        // ⚠️ Les commentaires sont retirés d'abord : le fichier EXPLIQUE cette absence, et un
        // garde-fou satisfait — ou accusé — par la prose qui le documente est circulaire.
        $regles = (string) preg_replace('!/\*.*?\*/!s', '', $tokens);

        self::assertStringNotContainsString("[data-accent='brand']", $regles);
        self::assertStringNotContainsString('[data-accent="brand"]', $regles);

        // ⚠️ Et la rampe de repli DOIT exister : sans elle, un consommateur qui ne déclare rien
        // n'aurait aucune couleur d'accent du tout.
        self::assertStringContainsString('--accent-500:', $regles);
    }

    /**
     * @param ClassMetadata<Account> $metadata
     */
    private function columnDefault(ClassMetadata $metadata, string $field): mixed
    {
        $options = $metadata->getFieldMapping($field)->options;
        self::assertIsArray($options, \sprintf('Le champ "%s" doit déclarer ses options de colonne.', $field));
        self::assertArrayHasKey('default', $options);

        return $options['default'];
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = $this->boot(withOrm: true)->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function createSchema(EntityManagerInterface $entityManager): void
    {
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }
}
