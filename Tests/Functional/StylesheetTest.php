<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Garde-fous sur les feuilles de style de la coquille.
 *
 * Une règle CSS n'a pas de sortie observable en test : la seule chose vérifiable sans navigateur
 * est qu'elle est encore écrite. C'est peu — et c'est exactement ce qui a manqué chaque fois qu'un
 * de ces réglages a disparu au détour d'un remaniement, pour ne se voir qu'à l'écran, en mode
 * sombre, sur la machine de quelqu'un d'autre.
 *
 * Ces assertions viennent d'un projet qui les portait avant que le CSS entre dans ce bundle. Elles
 * le suivent, comme leur code : un test resté côté projet garde une garantie sur du code qu'il ne
 * possède plus.
 */
#[CoversNothing]
final class StylesheetTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function buttonClasses(): iterable
    {
        yield 'btn-primary' => ['.btn-primary'];
        yield 'btn-secondary' => ['.btn-secondary'];
        yield 'btn-danger' => ['.btn-danger'];
        yield 'btn-warning' => ['.btn-warning'];
        yield 'btn-success' => ['.btn-success'];
    }

    /**
     * Les cinq variantes existent, et ce n'est pas une commodité : une classe absente de la
     * feuille ne lève RIEN, elle ne peint rien. Un bouton « Activer » écrit avec `.btn-success`
     * dans un projet dont le bundle ne la déclare pas sort en texte nu au milieu d'un formulaire
     * stylé — et seul l'écran le montre.
     *
     * `emerald` et non `green` : c'est la couleur de `.badge-active` et celle de l'action
     * « Activer » des tableaux. Une action porte la même couleur partout où elle apparaît.
     */
    public function testTheSuccessVariantIsEmeraldLikeTheActiveBadge(): void
    {
        $css = self::components();

        self::assertMatchesRegularExpression('/\.btn-success \{[^}]*bg-emerald-600[^}]*\}/s', $css);
        self::assertMatchesRegularExpression('/\.btn-success \{[^}]*hover:bg-emerald-500[^}]*\}/s', $css);
    }

    /**
     * Les cinq boutons réservent l'espacement de leur icône. C'est ce qui permet d'écrire
     * `<i class="fa-solid fa-floppy-disk"></i> Enregistrer` sans une seule classe de plus —
     * la règle « couleurs et icônes » des checklists de formulaire en dépend.
     */
    #[DataProvider('buttonClasses')]
    public function testAButtonReservesTheGapItsIconNeeds(string $selector): void
    {
        self::assertMatchesRegularExpression(
            '/'.preg_quote($selector, '/').' \{[^}]*inline-flex items-center gap-2[^}]*\}/s',
            self::components(),
            $selector.' doit porter `inline-flex items-center gap-2` pour recevoir une icône.',
        );
    }

    /**
     * Chaque bouton pose son propre anneau de focus et neutralise l'outline natif. Sans
     * `focus-visible:outline-none`, Chrome peint son `outline: auto` par-dessus : un double trait
     * bleu + halo blanc, illisible sur fond sombre.
     */
    #[DataProvider('buttonClasses')]
    public function testAButtonCarriesAnAccentFocusRingAndNoNativeOutline(string $selector): void
    {
        $css = self::components();

        self::assertMatchesRegularExpression(
            '/'.preg_quote($selector, '/').' \{[^}]*focus-visible:outline-none[^}]*focus-visible:ring-2[^}]*\}/s',
            $css,
            $selector.' doit poser focus-visible:outline-none + focus-visible:ring-2.',
        );
    }

    /**
     * Et surtout pas de `ring-offset` : l'offset est peint dans la couleur de fond de la page,
     * donc blanc — ce qui ré-introduit exactement le trait clair que l'anneau accent remplace.
     */
    #[DataProvider('buttonClasses')]
    public function testAButtonHasNoRingOffset(string $selector): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/'.preg_quote($selector, '/').' \{[^}]*ring-offset/s',
            self::components(),
        );
    }

    /**
     * Un `<select>` natif garde `appearance: auto` et peint SA bordure de focus par-dessus celle
     * de `.form-control` — claire, donc blanche en mode sombre, là où un `<input>` ne le fait pas.
     * La neutraliser oblige à réinjecter un chevron.
     */
    public function testANativeSelectNeutralisesItsOwnChrome(): void
    {
        $css = self::components();

        self::assertMatchesRegularExpression('/select\.form-control \{[\s\S]*?appearance-none/', $css);
        self::assertMatchesRegularExpression('/select\.form-control \{[\s\S]*?background-image:/', $css);
    }

    /**
     * La densité pilote le padding des panneaux, et pas seulement la hauteur de ligne d'un
     * tableau : sans cette règle, choisir « compact » ne se voyait que sur les pages qui portent
     * une datatable, ce qui ressemblait à un réglage cassé.
     */
    public function testDensityDrivesPanelPadding(): void
    {
        $css = self::tokens();

        self::assertStringContainsString('--density-panel-p', $css);
        self::assertMatchesRegularExpression(
            "/\[data-density='cozy'\] \.panel,\s*\[data-density='compact'\] \.panel \{\s*padding: var\(--density-panel-p\);/",
            $css,
        );
    }

    /**
     * L'autre moitié de la densité : la hauteur de ligne d'un tableau. La règle vise un sélecteur
     * du `datatable-bundle` (`table.dataTable td`) mais vit ici, avec le réglage de compte qui la
     * produit — un projet qui prend le socle de tableau sans cette coquille n'a pas de préférence
     * de densité à appliquer.
     */
    public function testDensityAlsoDrivesDatatableRowHeight(): void
    {
        $css = self::tokens();

        self::assertStringContainsString('--density-cell-py', $css);
        self::assertMatchesRegularExpression("/\[data-density='compact'\] table\.dataTable td/", $css);
    }

    /**
     * Le contraste élevé doit être perceptible : bordures plus sombres ET plus épaisses, texte
     * secondaire dé-atténué. Un simple demi-ton de plus sur une bordure ne se voit pas, ce qui
     * rend le réglage inutile pour qui en a besoin.
     */
    public function testHighContrastIsReinforcedOnBothThemes(): void
    {
        $css = self::tokens();

        self::assertStringContainsString('border-color: rgb(51 65 85)', $css);
        self::assertMatchesRegularExpression('/\[data-contrast=\'high\'\][\s\S]*?border-width: 2px/', $css);
        self::assertStringContainsString("[data-contrast='high'] .text-slate-500", $css);
        self::assertStringContainsString(".dark[data-contrast='high'] .panel", $css);
    }

    /** Les sept accents doivent tous exister : l'enum en propose sept, le CSS doit suivre. */
    public function testEveryAccentHasItsVariableBlock(): void
    {
        $css = self::tokens();

        foreach (['emerald', 'rose', 'amber', 'sky', 'violet', 'teal'] as $accent) {
            self::assertStringContainsString("[data-accent='".$accent."']", $css, $accent);
        }

        // `indigo` est le défaut : il vit sur `:root`, pas dans un bloc d'attribut.
        self::assertMatchesRegularExpression('/:root \{[\s\S]*?--accent-500: 99 102 241;/', $css);
    }

    /**
     * L'aperçu doit contenir un `table.dataTable` — c'est ce que la densité re-padde, donc le seul
     * élément qui rend le réglage démontrable en direct.
     */
    public function testTheAppearancePreviewShowsWhatDensityChanges(): void
    {
        $twig = (string) file_get_contents(\dirname(__DIR__, 2).'/Resources/views/partials/_appearance_form.html.twig');

        self::assertStringContainsString('class="dataTable', $twig);
        self::assertStringContainsString('appearance.preview.table.col_name', $twig);
    }

    /**
     * L'icône d'un texte d'aide est posée sur la ligne du texte, pas au-dessus.
     *
     * Font Awesome impose `line-height: 1` à ses icônes — 12px pour un `text-xs` — alors que le
     * texte est en `leading-relaxed`, soit 19,5px. `.form-help` étant un `flex items-start`, les
     * deux boîtes s'alignent par le HAUT et le centre de l'icône remonte de (19,5 − 12) / 2 =
     * 3,75px : mesuré à l'écran, elle flottait visiblement trop haut. Le défaut datait du jour où
     * le thème a été écrit (superp, 2026-04-16) et a survécu à l'extraction dans ce bundle.
     *
     * Le décalage plutôt qu'un `items-center` sur le parent : un texte d'aide passe sur plusieurs
     * lignes, et le centrage porterait alors sur le bloc — l'icône glisserait au milieu du
     * paragraphe.
     */
    public function testAHelpTextIconSitsOnTheTextLine(): void
    {
        $css = self::components();

        self::assertMatchesRegularExpression('/\.form-help i \{[\s\S]*?relative/', $css);
        self::assertMatchesRegularExpression('/\.form-help i \{[\s\S]*?top-1/', $css);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function badgeVariants(): iterable
    {
        yield 'active' => ['.badge-active', 'emerald'];
        yield 'inactive' => ['.badge-inactive', 'slate'];
        yield 'info' => ['.badge-info', 'sky'];
        yield 'warning' => ['.badge-warning', 'amber'];
        yield 'danger' => ['.badge-danger', 'red'];
        yield 'neutral' => ['.badge-neutral', 'slate'];
    }

    /**
     * Toutes les variantes de badge existent, et chacune peint.
     *
     * ⚠️ Une classe absente de la feuille ne lève RIEN : `<span class="badge-warning">` sort en
     * texte nu au milieu d'une fiche stylée, et seul l'écran le montre. Le bundle ne publiait que
     * `active` et `inactive` alors qu'un projet consommateur écrivait déjà `badge-warning` pour un
     * équipement « à remplacer » (trouvé le 2026-08-26 en instrumentant une machine à états à neuf
     * états). C'est la troisième fois que ce bundle publie un vocabulaire sans le comportement qui
     * va avec — d'où ce test, qui ferme la famille plutôt qu'un cas.
     *
     * Les couleurs ne sont pas libres : elles sont celles des boutons homologues
     * (`.btn-warning` en `amber`, `.btn-danger` en `red`), pour qu'un état et l'action qui y mène
     * se lisent de la même couleur.
     */
    #[DataProvider('badgeVariants')]
    public function testEveryBadgeVariantIsPainted(string $selector, string $palette): void
    {
        $css = self::components();

        self::assertMatchesRegularExpression(
            \sprintf('/%s \{[^}]*rounded-full[^}]*\}/s', preg_quote($selector, '/')),
            $css,
            \sprintf('%s n\'est pas déclarée : elle ne peindra rien, sans lever.', $selector),
        );
        self::assertMatchesRegularExpression(
            \sprintf('/%s \{[^}]*%s-[0-9]{2,3}[^}]*\}/s', preg_quote($selector, '/'), $palette),
            $css,
            \sprintf('%s doit rester dans la palette %s, celle de son bouton homologue.', $selector, $palette),
        );
    }

    /**
     * Le marqueur d'obligation d'un champ EXISTE.
     *
     * ⚠️ Symfony pose la classe `required` sur le label d'un champ obligatoire — le thème de base
     * le fait depuis toujours — mais une classe que personne ne dessine ne dessine rien. Sur
     * wovex, le 2026-08-26, **aucun formulaire du back-office n'affichait d'astérisque** :
     * `class="form-label required"` dans le DOM, `content: none` en style calculé, et zéro
     * sélecteur `required` dans les deux feuilles chargées. Le formulaire ne disait plus ce qu'il
     * exigeait, et rien ne le signalait.
     *
     * C'est la famille de défauts que ce bundle connaît déjà : publier un vocabulaire de classes
     * sans le comportement qui va avec (`.dropzone-*` sans son contrôleur, le menu ⋮ sans sa
     * fonction globale).
     */
    public function testARequiredLabelCarriesItsMarker(): void
    {
        $css = self::components();

        self::assertMatchesRegularExpression(
            '/\.form-label\.required::after \{[^}]*content:\s*[\'"]\*[\'"][^}]*\}/s',
            $css,
            'Sans cette règle, un champ obligatoire ne se distingue pas d\'un champ facultatif.',
        );
        self::assertMatchesRegularExpression('/\.form-label\.required::after \{[^}]*text-red-500[^}]*\}/s', $css);
    }

    /**
     * Les deux variantes du logo ont leur règle de visibilité DANS cette feuille.
     *
     * ⚠️ **Des classes nommées et non `dark:hidden` / `dark:block`.** Un utilitaire Tailwind n'est
     * généré que s'il apparaît dans le contenu SCANNÉ, et les projets qui prennent ce socle ne
     * mettent que `ui-bundle` dans leur `TEMPLATE_BUNDLES` : les gabarits d'`admin-bundle` ne sont
     * pas scannés. Un `dark:hidden` écrit dans un gabarit d'ici serait donc une classe inerte —
     * balisage juste, aucune règle derrière, et le mauvais logo servi. Une règle écrite dans cette
     * feuille est en revanche toujours émise, puisque la feuille est compilée.
     *
     * C'est la moitié qu'un test de balisage ne voit pas, et celle qui a manqué chez un
     * consommateur le 2026-09-03.
     */
    public function testTheLogoVariantsCarryTheirVisibilityRules(): void
    {
        $css = self::components();

        foreach ([
            '.admin-logo-light',
            '.admin-logo-dark',
            '.dark .admin-logo-light',
            '.dark .admin-logo-dark',
        ] as $rule) {
            self::assertStringContainsString(
                $rule,
                $css,
                \sprintf('Sans « %s », le gabarit rend deux images et aucune n\'est masquée.', $rule),
            );
        }
    }

    private static function components(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2).'/assets/styles/components.css');
    }

    private static function tokens(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2).'/assets/styles/tokens.css');
    }
}
