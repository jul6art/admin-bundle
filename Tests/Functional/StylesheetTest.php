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

        // ⚠️ The steps are 700 / 800 since 1.20.2, not 600 / 500: white on emerald-600 is 3.77 and
        // on emerald-500 2.54, both below AA. The contrast itself is `SemanticContrastTest`'s.
        self::assertMatchesRegularExpression('/\.btn-success \{[^}]*bg-emerald-700[^}]*\}/s', $css);
        self::assertMatchesRegularExpression('/\.btn-success \{[^}]*hover:bg-emerald-800[^}]*\}/s', $css);
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
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function alertClasses(): iterable
    {
        yield 'alert-info' => ['.alert-info', 'sky'];
        yield 'alert-success' => ['.alert-success', 'emerald'];
        yield 'alert-warning' => ['.alert-warning', 'amber'];
        yield 'alert-danger' => ['.alert-danger', 'red'];
    }

    /**
     * Les quatre alertes existent, et c'est la leçon des variantes de badge répétée un cran plus
     * loin : **écrire une classe ne la dessine pas**.
     *
     * Un produit consommateur a écrit `class="alert-warning"` en croyant la classe fournie. Elle
     * n'existait nulle part — ni dans ce bundle, ni dans son propre CSS — donc la bannière
     * s'affichait sans aucune mise en forme. Et le test qui la gardait,
     * `assertSelectorExists('.alert-warning')`, passait très bien : **asserter la présence d'une
     * classe dans le HTML ne dit rien de son existence en CSS.**
     */
    #[DataProvider('alertClasses')]
    public function testAnAlertVariantIsDrawn(string $selector, string $palette): void
    {
        $css = self::components();

        self::assertMatchesRegularExpression(
            '/'.preg_quote($selector, '/').' \{[^}]*bg-'.$palette.'-50[^}]*\}/s',
            $css,
            $selector.' doit exister et porter la palette `'.$palette.'` de son badge homologue.',
        );
    }

    /**
     * ⚠️ Chaque alerte porte SES deux nuances sombres. C'est le défaut qui a suivi le premier :
     * la bannière avait bien été écrite en Tailwind inline, mais deux de ses classes sombres
     * n'avaient jamais été générées — donc `bg-amber-50` restait peint sur `border-amber-800`, un
     * fond clair dans une bordure sombre. Mesuré au `getComputedStyle`, en mode sombre forcé.
     */
    #[DataProvider('alertClasses')]
    public function testAnAlertVariantSurvivesDarkMode(string $selector, string $palette): void
    {
        $css = self::components();

        foreach (['dark:bg-'.$palette.'-950', 'dark:border-'.$palette.'-800', 'dark:text-'.$palette.'-300'] as $needed) {
            self::assertMatchesRegularExpression(
                '/'.preg_quote($selector, '/').' \{[^}]*'.preg_quote($needed, '/').'[^}]*\}/s',
                $css,
                $selector.' doit porter `'.$needed.'` : sans ses trois nuances sombres, l\'alerte '
                .'mélange un fond clair et une bordure sombre.',
            );
        }
    }

    /**
     * L'icône est réservée comme sur les boutons, donc
     * `<i class="fa-solid fa-triangle-exclamation"></i> <span>…</span>` suffit sans une classe de
     * plus.
     *
     * `items-start` et non `items-center` : un message d'alerte tient souvent sur deux lignes, et
     * l'icône doit rester à hauteur de la PREMIÈRE, pas flotter au milieu du bloc.
     */
    #[DataProvider('alertClasses')]
    public function testAnAlertReservesTheGapItsIconNeedsAndKeepsItOnTheFirstLine(string $selector, string $palette): void
    {
        self::assertMatchesRegularExpression(
            '/'.preg_quote($selector, '/').' \{[^}]*flex items-start gap-3[^}]*\}/s',
            self::components(),
            $selector.' doit porter `flex items-start gap-3`.',
        );
    }

    /**
     * ⚠️ **La cible tactile des boutons est écrite en CSS, et le relèvement est celui du MOBILE.**.
     *
     * Mesuré sur un produit consommateur : `.btn-secondary` rendait **32 px** de haut,
     * `.btn-primary` 36 — le `py-2` de leur définition sur une police de 14 px. Le minimum tenable
     * pour un doigt est 44.
     *
     * Deux propriétés que ce cas fixe, et qui ont chacune déjà été perdues une fois :
     *
     * 1. la règle est du **CSS écrit**, pas `@apply min-h-11` — un utilitaire, et surtout sa
     *    variante `lg:`, n'existe que si le consommateur l'a dans son contenu scanné, et les
     *    consommateurs ne scannent pas les gabarits de ce bundle. C'est exactement ce qui a fait
     *    échouer le premier correctif de cible tactile (commit `4f6a11c`) ;
     * 2. c'est le **mobile** qui monte et le palier `lg:` qui annule, jamais l'inverse : le rendu
     *    de bureau des trois produits reste identique au pixel.
     */
    public function testTheButtonsCarryATouchTargetWrittenAsCss(): void
    {
        $css = self::components();

        self::assertMatchesRegularExpression(
            '/\.btn-primary,\s*\.btn-secondary,\s*\.btn-danger,\s*\.btn-warning,\s*\.btn-success \{\s*min-height: 2\.75rem;/s',
            $css,
            'Les cinq boutons doivent porter `min-height: 2.75rem` en CSS écrit — 44 px, la cible '
            .'tactile minimale. `@apply min-h-11` ne serait pas généré chez le consommateur.',
        );
        // ⚠️ Le motif s'ancre sur `.btn-success,` **avec sa virgule**, et pas sur « `.btn-success`
        // suivi d'une accolade ». La première version supposait ce bouton DERNIER de son groupe :
        // le jour où les champs l'ont rejoint dans la même media query (2026-09-05), le cas a rougi
        // sur un CSS parfaitement correct. Un motif qui dépend de l'ordre des sélecteurs teste la
        // mise en forme, pas la propriété.
        self::assertMatchesRegularExpression(
            '/@media \(min-width: 1024px\) \{[\s\S]*?\.btn-success,[\s\S]*?min-height: 0;/s',
            $css,
            'Le palier `lg:` doit ANNULER le relèvement, pour que le rendu de bureau soit '
            .'inchangé. Si c\'est le mobile qui annule, les trois produits changent d\'apparence '
            .'là où ils sont le plus regardés.',
        );
    }

    /**
     * ⚠️ **Les CHAMPS portent la même cible tactile, et c'était le manque de la v1.9.0.**.
     *
     * On y avait relevé les boutons, et rien d'autre. Mesuré à 360 px sur un produit consommateur
     * le 2026-09-05 : `.form-control` rend **38 px**. Un champ de saisie est une cible tactile — on
     * tape dedans — donc c'était **chaque champ de chaque formulaire des trois produits** sous le
     * minimum, sans que le correctif précédent l'ait vu.
     *
     * ⚠️ **Le symptôme était ailleurs que la cause**, et c'est ce qui rend ce cas utile : le bouton
     * « afficher le mot de passe » est en `absolute inset-y-0`, donc il épouse la hauteur du champ.
     * Il mesurait 44 × 38, et on aurait pu le relever LUI — corrigeant l'écran regardé, laissant
     * tous les autres.
     */
    public function testTheFieldsCarryTheSameTouchTargetAsTheButtons(): void
    {
        $css = self::components();

        self::assertMatchesRegularExpression(
            '/\.form-control,\s*\.form-select \{\s*min-height: 2\.75rem;/s',
            $css,
            'Un champ de saisie est une cible tactile : il doit porter `min-height: 2.75rem` comme '
            .'les boutons. À 38 px, c\'est tout formulaire de tout produit qui est sous le minimum.',
        );
        self::assertMatchesRegularExpression(
            '/@media \(min-width: 1024px\) \{[\s\S]*?\.form-control,\s*\.form-select,[\s\S]*?min-height: 0;/s',
            $css,
            'Le palier `lg:` doit annuler le relèvement des champs aussi : la densité de bureau des '
            .'formulaires ne change pas.',
        );
    }

    /**
     * ⚠️ **Un lien en ligne ne prend pas de hauteur, quelle que soit sa `min-height`.**.
     *
     * `.auth-link` doit donc changer de `display`. Sans `inline-flex`, la règle serait écrite,
     * appliquée, et **sans aucun effet** — le pire des trois cas, parce qu'elle a l'air corrigée et
     * qu'on cesse de chercher. « Créer un compte » mesurait 123 × **17**.
     */
    public function testAnAuthLinkBecomesABlockToCarryItsTouchTarget(): void
    {
        $css = self::components();

        self::assertMatchesRegularExpression(
            '/\.auth-link \{\s*display: inline-flex;\s*align-items: center;\s*min-height: 2\.75rem;/s',
            $css,
            'Sans `display: inline-flex`, la `min-height` d\'un lien en ligne est inopérante : la '
            .'règle existe et ne fait rien.',
        );
        self::assertMatchesRegularExpression(
            '/@media \(min-width: 1024px\) \{[\s\S]*?\.auth-link \{\s*display: inline;/s',
            $css,
            'Sur un écran de bureau, un lien redevient un lien en ligne — sinon la phrase « Pas '
            .'encore de compte ? Créer un compte » se casse en deux blocs.',
        );
    }

    /**
     * ⚠️ **`.admin-touch-row` portait un nom qu'elle ne tenait pas.**.
     *
     * Elle existe pour donner une cible tactile aux en-têtes de section du menu, et ne donnait que
     * du rembourrage : `0.75rem` × 2 sur un texte de 12 px font **40 px** — mesuré à 360 px le
     * 2026-09-05. Un rembourrage n'est pas une hauteur : il dépend de la police, de la casse et de
     * l'interlignage, donc il tient par accident.
     */
    public function testTheTouchRowCarriesAHeightAndNotOnlyPadding(): void
    {
        self::assertMatchesRegularExpression(
            '/\n\.admin-touch-row \{\s*min-height: 2\.75rem;/s',
            self::components(),
            'Une classe qui s\'appelle `admin-touch-row` doit porter une HAUTEUR. Le rembourrage '
            .'seul donnait 40 px, et dépendait de la police du consommateur.',
        );
    }

    /** ⚠️ L'interrupteur « se souvenir de moi » rendait 40 px — la seule autre cible de l'écran. */
    public function testTheToggleRowCarriesATouchTarget(): void
    {
        self::assertMatchesRegularExpression(
            '/\.toggle-row \{\s*min-height: 2\.75rem;/s',
            self::components(),
            '`.toggle-row` est la cible de l\'interrupteur : à 40 px, elle est sous le minimum.',
        );
    }

    /**
     * ⚠️ **La règle ne sert à rien si aucun gabarit ne porte la classe.**.
     *
     * C'est le défaut symétrique du précédent : là, la règle était inopérante faute de `display` ;
     * ici, elle serait inopérante faute d'appelant. Les cinq liens des écrans d'authentification
     * sont comptés — un lien ajouté sans la classe se verra.
     */
    public function testEveryAuthLinkTemplateCarriesTheClass(): void
    {
        $vues = \dirname(__DIR__, 2).'/Resources/views/security';
        $sansClasse = [];

        foreach (glob($vues.'/*.twig') ?: [] as $fichier) {
            $contenu = (string) file_get_contents($fichier);

            preg_match_all('/<a\s[^>]*class="([^"]*text-accent-600[^"]*)"/', $contenu, $matches);

            foreach ($matches[1] as $classes) {
                if (!str_contains($classes, 'auth-link')) {
                    $sansClasse[] = basename($fichier).' : '.$classes;
                }
            }
        }

        self::assertSame(
            [],
            $sansClasse,
            \sprintf(
                "Ces liens d'authentification ne portent pas `auth-link`, donc aucune cible "
                ."tactile :\n%s",
                implode("\n", $sansClasse),
            ),
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
     * ⚠️ **A list box has no chevron.** `select[multiple]`, or a `size` above one, renders its
     * options in place; the drop-down arrow `select.form-control` injects painted over the first
     * option and `pr-9` kept a gutter for it — a consumer wrote its own field class to escape it
     * (2026-09-23). The reset must be MORE specific than `select.form-control`, remove the image
     * and give the right padding back, and must leave `size="1"` (a drop-down) alone.
     */
    public function testAListBoxSelectCarriesNoChevron(): void
    {
        $css = self::withoutComments(self::components());

        self::assertSame(
            1,
            preg_match('/((?:[^{}]*,\s*)?select\.form-control\[multiple\](?:\s*,[^{}]*)?)\{([^{}]*)\}/', $css, $rule),
            'No rule resets `select.form-control[multiple]`: a list box gets the drop-down chevron.',
        );

        // Split on the commas of the list, not those inside `:not(…)`.
        $selectors = array_map(trim(...), preg_split('/,(?![^(]*\))/', $rule[1]) ?: []);
        self::assertContains('select.form-control[multiple]', $selectors);
        self::assertContains(
            'select.form-control[size]:not([size="0"], [size="1"])',
            $selectors,
            'A `size` above one is a list box too; `size="1"` stays a drop-down.',
        );
        self::assertMatchesRegularExpression('/background-image:\s*none/', $rule[2], 'The list box keeps the chevron.');
        self::assertMatchesRegularExpression('/@apply [^;]*\bpr-3\b/', $rule[2], 'The list box keeps the chevron gutter (`pr-9`).');
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
            "/\[data-density='cozy'\] \.panel:not\(\[class~='p-0'\], \.dt-container, :has\(> table, > \.dt-container\)\),\s*"
            ."\[data-density='compact'\] \.panel:not\(\[class~='p-0'\], \.dt-container, :has\(> table, > \.dt-container\)\) \{\s*padding: var\(--density-panel-p\);/",
            $css,
            'Cozy and compact must retune every panel except a flush one: a panel hosting a table '
            .'has rows that carry their own gutter, and padding it doubles that gutter.',
        );
    }

    /**
     * ⚠️ **The DEFAULT density pads a panel too** — it did not until 2026-09-23.
     *
     * `--density-panel-p` was declared for comfortable and applied only to cozy and compact, so a
     * `.panel` without its own `p-*` rendered its text against the border — every panel of a
     * consumer's customer space, found by its review. The value is read, not only the property: a
     * `:root` token of `0` would be the same defect with the rule still written.
     */
    public function testAPanelIsPaddedInTheDefaultDensityAndAFlushOneIsNot(): void
    {
        $css = self::withoutComments(self::components());

        self::assertSame(1, preg_match('/\n    \.panel \{([^}]*)\}/', $css, $panel), '`.panel` is not found in the sheet.');
        self::assertMatchesRegularExpression(
            '/^\s*@apply [^;]+;\s*padding: var\(--density-panel-p\);\s*$/',
            $panel[1],
            '`.panel` must carry the density padding itself, inside the layer — so a `p-*` utility on '
            .'the element still wins — and declare nothing else: a `display` or a margin here would '
            .'land on every panel of three products.',
        );

        self::assertSame(1, preg_match('/:root\s*\{[^}]*--density-panel-p:\s*([0-9.]+)rem;/', self::tokens(), $token));
        self::assertGreaterThanOrEqual(1.0, (float) $token[1], 'The comfortable panel padding is the widest of the three densities.');

        self::assertMatchesRegularExpression(
            '/\.panel:where\(\.dt-container, :has\(> table, > \.dt-container\)\) \{\s*padding: 0;\s*\}/',
            $css,
            'A panel hosting a table stays flush in the default density, at the weight of `.panel` '
            .'alone (`:where`), so a `p-*` utility still overrides the exemption.',
        );
    }

    /**
     * ⚠️ **iOS Safari zooms on any field under 16 px** — on focus, every time, and leaves the page
     * zoomed. `.form-control` is `text-sm` (14 px) for the desktop density, so a phone must get 16.
     *
     * The rule existed since 2026-09-08 under `pointer: coarse` only, and a review measured 14 px
     * at 360 px on 2026-09-23 in a window whose pointer was fine. The query is EVALUATED here, for
     * a 360-px viewport with a fine pointer and for a 1280-px one: a regex on the rule's text
     * would stay green while the query no longer matched a phone.
     */
    public function testAFieldIsSixteenPixelsOnAPhoneAndKeepsTheDesktopDensity(): void
    {
        $css = self::withoutComments(self::components());
        $phone = [];
        $desktop = [];

        preg_match_all('/@media ([^{]+)\{\s*((?:[^{}]+\{[^{}]*\}\s*)+)\}/', $css, $blocks, \PREG_SET_ORDER);

        foreach ($blocks as [, $query, $body]) {
            preg_match_all('/([^{}]+)\{([^{}]*)\}/', $body, $rules, \PREG_SET_ORDER);

            foreach ($rules as [, $selectors, $declarations]) {
                if (1 !== preg_match('/font-size:\s*(\d+)px/', $declarations, $size)) {
                    continue;
                }

                foreach (array_map(trim(...), explode(',', $selectors)) as $selector) {
                    if (self::queryMatches($query, 360, false)) {
                        $phone[$selector] = max($phone[$selector] ?? 0, (int) $size[1]);
                    }

                    if (self::queryMatches($query, 1280, false)) {
                        $desktop[$selector] = (int) $size[1];
                    }
                }
            }
        }

        // ⚠️ `input.` / `select.` / `textarea.`: one element of specificity above a `text-sm`
        // utility, so a template writing `form-control text-sm` cannot bring the zoom back.
        // `select.form-select` as well: the class only had a `min-height`, so a
        // `<select class="form-select text-sm">` stayed at 14 px (2026-09-23).
        foreach (['input.form-control', 'select.form-control', 'select.form-select', 'textarea.form-control', 'input.admin-search-input'] as $selector) {
            self::assertGreaterThanOrEqual(16, $phone[$selector] ?? 0, \sprintf(
                '`%s` is under 16 px on a 360-px screen: Safari zooms on every focus.',
                $selector,
            ));
            self::assertArrayNotHasKey($selector, $desktop, \sprintf('`%s` must keep the desktop density above `lg`.', $selector));
        }

        self::assertMatchesRegularExpression('/\.form-control \{\s*@apply [^;]*\btext-sm\b/', $css, 'The desktop field is 14 px.');
        self::assertStringContainsString(
            'class="admin-search-input ',
            (string) file_get_contents(\dirname(__DIR__, 2).'/Resources/views/partials/_global_search.html.twig'),
            'The header search field must carry the class the 16-px rule targets.',
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
        // ⚠️ Les trois ajoutées le 2026-09-06, et la raison est la MÊME que celle des quatre
        // précédentes : un projet consommateur les écrivait déjà — `badge-success` dans onze
        // énumérations — et aucune n'existait. C'est la quatrième fois. Voir le garde de
        // complétude juste en dessous, qui empêche la cinquième.
        yield 'success' => ['.badge-success', 'emerald'];
        yield 'violet' => ['.badge-violet', 'violet'];
        yield 'accent' => ['.badge-accent', 'accent'];
    }

    /**
     * Toute classe `.badge-*` de la feuille est ÉPROUVÉE par le fournisseur ci-dessus.
     *
     * ## Pourquoi ce garde-là, en plus de l'autre
     *
     * ⚠️ `testEveryBadgeVariantIsPainted` prouve que les classes ATTENDUES sont peintes ; il ne dit
     * rien de celles que la feuille contient et que personne n'a inscrites. Une variante ajoutée
     * sans son entrée dans le fournisseur serait publiée sans test — et le fournisseur est un
     * inventaire écrit à la main, donc exactement le genre de liste qui vieillit.
     *
     * ⚠️ Il ne peut pas, en revanche, détecter les classes MANQUANTES : le bundle ne connaît pas le
     * vocabulaire qu'un consommateur écrit. C'est au projet consommateur de croiser ce qu'il écrit
     * avec ce qui est défini — quatre fois ce bundle a publié un vocabulaire incomplet, et quatre
     * fois c'est un consommateur qui l'a découvert à l'écran.
     */
    public function testEveryBadgeClassOfTheSheetIsCovered(): void
    {
        preg_match_all('/\.(badge-[a-z][a-z0-9-]*)\s*\{/', self::components(), $matches);

        $declared = array_values(array_unique($matches[1]));
        sort($declared);

        $covered = [];

        foreach (self::badgeVariants() as [$selector]) {
            $covered[] = ltrim($selector, '.');
        }

        sort($covered);

        self::assertSame($covered, $declared, \sprintf(
            "La feuille déclare des badges que le fournisseur n'éprouve pas, ou l'inverse :\n"
            ."  feuille      : %s\n  fournisseur  : %s\n\n"
            .'Une variante publiée sans son entrée ici est une variante sans test — et le '
            .'fournisseur est un inventaire écrit à la main, donc exactement le genre de liste qui '
            .'vieillit en silence.',
            implode(', ', $declared),
            implode(', ', $covered),
        ));
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
        // Its colour is not pinned here: `SemanticContrastTest` reads it and measures it, in both
        // themes — a step written in two places is the one that stays behind.
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

    /**
     * Les cibles tactiles ont leur règle DANS cette feuille, densité de bureau comprise.
     *
     * ⚠️ **La version qui écrivait `py-3 lg:py-2` dans les gabarits a été livrée, puis reprise.**
     * Un utilitaire Tailwind n'existe que s'il apparaît dans le contenu SCANNÉ, et les
     * consommateurs ne mettent que `ui-bundle` dans leur `TEMPLATE_BUNDLES` : les gabarits d'ici
     * ne sont pas scannés. Mesuré chez un consommateur le 2026-09-03 — `.py-3` était présent
     * (utilisé ailleurs dans SES gabarits) et `.lg\:py-2` **absent**, donc le relèvement mobile
     * s'appliquait au bureau aussi. En silence, et dans le sens qu'on voulait éviter.
     *
     * ⚠️ C'est le même défaut que celui du logo, commis dans le MÊME commit après en avoir écrit
     * la leçon deux blocs plus haut. D'où ce test : la règle est vérifiable, la discipline non.
     *
     * ## Pourquoi il n'y a PAS de garde-fou général « aucune variante dans les gabarits »
     *
     * ⚠️ Il a été écrit, puis retiré : les gabarits de ce bundle contiennent des **centaines** de
     * `dark:*`, et ils fonctionnent — tout consommateur en génère abondamment depuis ses propres
     * gabarits, donc les utilitaires existent de toute façon.
     *
     * La distinction utile n'est pas mécanique : une variante COURANTE (`dark:text-slate-100`) est
     * sûre en pratique, une variante RARE (`lg:py-2`, qu'aucun gabarit de consommateur n'écrivait)
     * ne l'est pas. Un test qui exigerait de réécrire cinq cents lignes qui marchent n'est pas un
     * garde-fou, c'est une exigence — d'où un test qui fige les deux règles dont ce bundle a
     * réellement besoin, et cette note pour la prochaine personne qui aura la même idée.
     */
    public function testTheTouchTargetsCarryTheirOwnRules(): void
    {
        $css = self::components();

        // ⚠️ La VALEUR, pas seulement le nom de la classe : retirer la règle mobile laisse le
        // nom présent dans le bloc `@media` du bureau, et une assertion sur le nom seul reste
        // verte — vérifié par mutation le 2026-09-03.
        foreach (['.admin-touch-row' => '0.75rem', '.admin-touch-tight' => '0.625rem'] as $rule => $padding) {
            self::assertMatchesRegularExpression(
                \sprintf('/%s\s*\{[^}]*padding-top:\s*%s/', preg_quote($rule, '/'), preg_quote($padding, '/')),
                $css,
                \sprintf(
                    'Sans son rembourrage de %s, « %s » ne fait pas 44 px au doigt — la ligne '
                    .'cliquable retombe à la hauteur de son texte.',
                    $padding,
                    $rule,
                ),
            );
        }

        // ⚠️ Et la densité de BUREAU : sans son bloc, les 44 px du doigt s'appliquent partout.
        self::assertMatchesRegularExpression(
            '/@media \(min-width: 1024px\)[\s\S]*?\.admin-touch-row/',
            $css,
            'La densité de bureau doit vivre dans un bloc `@media (min-width: 1024px)` : sans lui, '
            .'le relèvement tactile restyle tous les produits qui prennent cette coquille.',
        );
    }

    /**
     * The header search's targets: 44 px to a finger, the desktop density above `lg`.
     *
     * Measured at 360 px in cegeta on 2026-09-23 (ADR-0037): the result rows rendered **36 px**,
     * the "see all" link **16**, the magnifier and the × **38.5 wide**. Written as CSS for the
     * reason `.admin-touch-row` is: the consumers do not scan this bundle's templates, and the
     * result rows are built by a Stimulus controller no scanner reads at all.
     */
    public function testTheSearchTargetsCarryTheirOwnRules(): void
    {
        $css = self::components();

        self::assertMatchesRegularExpression(
            '/\n\.admin-search-target\s*\{[^}]*min-height:\s*2\.75rem;[^}]*min-width:\s*2\.75rem;/',
            $css,
            'La loupe et la croix de la recherche doivent faire 44 × 44 px au doigt.',
        );
        self::assertDoesNotMatchRegularExpression(
            '/\n\.admin-search-target\s*\{[^}]*display:/',
            $css,
            'Hors couche, un `display` sur la cible écraserait le `md:hidden` des deux boutons.',
        );
        self::assertMatchesRegularExpression(
            '/\n\.admin-search-row\s*\{[^}]*display:\s*flex;[^}]*min-height:\s*2\.75rem;/',
            $css,
            'Chaque ligne du panneau — un résultat, « voir tout » — doit faire 44 px de haut ; '
            .'`flex`, sinon un lien en ligne ne prend pas de hauteur.',
        );
        self::assertMatchesRegularExpression(
            '/@media \(min-width: 1024px\)[\s\S]*?\.admin-search-row[\s\S]*?min-height:\s*0/',
            $css,
            'La densité de bureau revient au-dessus de `lg`.',
        );
    }

    /**
     * Whether a media query list matches a viewport — the three features this sheet uses.
     */
    private static function queryMatches(string $query, int $width, bool $coarse): bool
    {
        foreach (explode(',', $query) as $alternative) {
            preg_match_all('/\(\s*([a-z-]+)\s*:\s*([^)]+?)\s*\)/', $alternative, $features, \PREG_SET_ORDER);
            self::assertNotSame([], $features, \sprintf('`@media %s`: no feature read.', trim($query)));

            $matches = true;

            foreach ($features as [, $feature, $value]) {
                $matches = $matches && match ($feature) {
                    'max-width' => $width <= (int) $value,
                    'min-width' => $width >= (int) $value,
                    'pointer' => ('coarse' === $value) === $coarse,
                    default => self::fail(\sprintf('`%s` is a media feature this guard does not evaluate.', $feature)),
                };
            }

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    private static function withoutComments(string $css): string
    {
        return (string) preg_replace('!/\*.*?\*/!s', '', $css);
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
