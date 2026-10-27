<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

/**
 * The record page (1.35): `@Admin/record/_page.html.twig`, its macros and the ⋯ menu.
 *
 * A record page reads in three beats — who (the header), where it stands (figures, steps, tabs),
 * then the detail (the content, and a column of properties from 1280 px). Each case below renders
 * the template the way a product embeds it and reads the HTML, because a template no test renders
 * is a template nobody knows anything about.
 */
#[CoversNothing]
final class RecordRenderingTest extends AbstractFunctionalTestCase
{
    public function testTheTitleIsThePageHeadingAndEmptyBlocksLeaveNoTag(): void
    {
        $html = $this->render("{% embed '@Admin/record/_page.html.twig' %}
            {% block record_title %}Acme Construction{% endblock %}
            {% block record_main %}<p>Body</p>{% endblock %}
        {% endembed %}");

        self::assertMatchesRegularExpression('#<h1 class="admin-record__title">Acme Construction</h1>#', $html);
        self::assertSame(1, substr_count($html, '<h1'), 'The record writes the only h1 of the page: `ui--page-title` copies the first one.');
        self::assertStringNotContainsString('admin-record__meta', $html, 'An empty meta leaves no empty paragraph under the title.');
        self::assertStringNotContainsString('admin-record__actions', $html);
        self::assertStringNotContainsString('<aside', $html, 'Without an aside, the content takes the full width.');
        self::assertStringNotContainsString('admin-record__body--with-aside', $html);
    }

    public function testAnAsideOpensTheSecondColumnAndComesFirstByDefault(): void
    {
        $html = $this->render("{% embed '@Admin/record/_page.html.twig' %}
            {% block record_title %}Acme{% endblock %}
            {% block record_meta %}<span>Construction</span><span>Luxembourg</span>{% endblock %}
            {% block record_main %}<p>Body</p>{% endblock %}
            {% block record_aside %}<div class=\"panel\">Details</div>{% endblock %}
        {% endembed %}");

        self::assertStringContainsString('<p class="admin-record__meta"><span>Construction</span><span>Luxembourg</span></p>', $html);
        self::assertStringContainsString('admin-record__body admin-record__body--with-aside admin-record__body--aside-first', $html);
        self::assertStringContainsString('<aside class="admin-record__aside"><div class="panel">Details</div></aside>', $html);
    }

    public function testARecordWhoseContentIsTheWorkPutsTheAsideAfterIt(): void
    {
        $html = $this->render("{% embed '@Admin/record/_page.html.twig' with { aside_first: false, title_mono: true } %}
            {% block record_title %}WO-2026-00412{% endblock %}
            {% block record_aside %}<p>Planning</p>{% endblock %}
        {% endembed %}");

        self::assertStringContainsString('class="admin-record__body admin-record__body--with-aside"', $html);
        self::assertStringContainsString('<h1 class="admin-record__title admin-record__title--mono">', $html);
    }

    /**
     * The preview of a list (jul6art/datatable-bundle ≥ 2.13) shows `[data-preview]` minus its
     * `[data-preview-exclude]` regions: who, where it stands and the column — not the way back, the
     * tabs, the content nor what follows it, unless a panel of the content asks to stay.
     */
    public function testThePreviewOfAListKeepsTheHeaderFiguresAndColumnOnly(): void
    {
        $html = $this->render("{% embed '@Admin/record/_page.html.twig' %}
            {% import '@Admin/record/_macros.html.twig' as record %}
            {% block record_breadcrumb %}<nav id=\"way-back\">Customers</nav>{% endblock %}
            {% block record_title %}Acme{% endblock %}
            {% block record_stats %}<div id=\"figures\"></div>{% endblock %}
            {% block record_tabs %}{{ record.tabs([{key: 'a', label: 'A', href: '?tab=a'}], 'a') }}{% endblock %}
            {% block record_main %}<div id=\"content\"></div><div id=\"kept\" data-preview-keep></div>{% endblock %}
            {% block record_aside %}<div id=\"details\"></div>{% endblock %}
            {% block record_after %}<div id=\"audit\"></div>{% endblock %}
        {% endembed %}");

        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $count = static fn (string $query): int => ($nodes = $xpath->query($query)) instanceof \DOMNodeList ? $nodes->length : 0;
        $excluded = static fn (string $id): bool => 1 === $count(\sprintf('//*[@id="%s"]/ancestor-or-self::*[@data-preview-exclude]', $id));

        self::assertSame(1, $count('//*[@data-preview][contains(@class, "admin-record")]'), 'The record is what a preview shows.');
        foreach (['way-back', 'content', 'kept', 'audit'] as $id) {
            self::assertTrue($excluded($id), $id.' is left out of the preview.');
        }
        self::assertSame(1, $count('//nav[contains(@class, "admin-record__tabs")][@data-preview-exclude]'), 'The tabs too: the preview has one page, not its sections.');
        self::assertSame(1, $count('//*[@id="kept"][@data-preview-keep]'), 'A panel of the content can ask to stay.');
        foreach (['figures', 'details'] as $id) {
            self::assertFalse($excluded($id), $id.' is part of the preview.');
        }
        self::assertSame(0, $count('//h1/ancestor-or-self::*[@data-preview-exclude]'), 'Nor is the header.');
    }

    public function testAnEmptyBreadcrumbOrAfterLeavesNoWrapper(): void
    {
        $html = $this->render("{% embed '@Admin/record/_page.html.twig' %}
            {% block record_title %}Acme{% endblock %}
        {% endembed %}");

        self::assertSame(1, substr_count($html, 'data-preview-exclude'), 'Only the content column: no empty wrapper for an absent breadcrumb or after.');
    }

    /**
     * ⚠️ A capture keeps the escaping of the template that rendered it: a name the user typed is
     * escaped ONCE, never interpreted and never escaped twice.
     */
    public function testWhatTheBlocksPrintIsEscapedOnceAndOnlyOnce(): void
    {
        $html = $this->render("{% embed '@Admin/record/_page.html.twig' %}
            {% block record_title %}{{ name }}{% endblock %}
            {% block record_meta %}<span>{{ name }}</span>{% endblock %}
            {% block record_aside %}<b>{{ name }}</b>{% endblock %}
        {% endembed %}", ['name' => '<script>&']);

        self::assertStringNotContainsString('<script>', $html);
        self::assertSame(3, substr_count($html, '&lt;script&gt;&amp;'));
        self::assertStringNotContainsString('&amp;lt;', $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideNames(): iterable
    {
        yield 'two words' => ['Acme Construction', 'AC'];
        yield 'three words take two' => ['élodie van Berg', 'ÉV'];
        yield 'one word' => ['Brasserie', 'B'];
        yield 'extra spaces' => ['  Julie   Hansen ', 'JH'];
    }

    #[DataProvider('provideNames')]
    public function testTheAvatarTakesTheInitialsOfTheFirstTwoWords(string $name, string $initials): void
    {
        $html = $this->render("{% import '@Admin/record/_macros.html.twig' as record %}{{ record.avatar(name) }}", ['name' => $name]);

        self::assertSame(\sprintf('<span class="admin-record__avatar" aria-hidden="true">%s</span>', $initials), trim($html));
    }

    public function testTheAvatarCanCarryAnIconAndBeRound(): void
    {
        $html = $this->render("{% import '@Admin/record/_macros.html.twig' as record %}{{ record.avatar(null, {icon: 'fa-wrench', round: true}) }}");

        self::assertSame('<span class="admin-record__avatar admin-record__avatar--round" aria-hidden="true"><i class="fa-solid fa-wrench"></i></span>', trim($html));
    }

    /** A category stores its icon whole — `fa-solid fa-car` —, and the style is not written twice. */
    public function testAnIconHandedWithItsStyleKeepsIt(): void
    {
        $html = $this->render("{% import '@Admin/record/_macros.html.twig' as record %}{{ record.avatar(null, {icon: 'fa-regular fa-car'}) }}");

        self::assertStringContainsString('<i class="fa-regular fa-car"></i>', $html);
    }

    public function testAPhotoTakesThePlaceOfTheInitialsAndOnlyWhenThereIsOne(): void
    {
        $html = $this->render("{% import '@Admin/record/_macros.html.twig' as record %}[{{ record.avatar('Julie Hansen', {round: true, image: '/media/julie.webp'}) }}][{{ record.avatar('Julie Hansen', {image: null}) }}]");

        self::assertStringContainsString('<span class="admin-record__avatar admin-record__avatar--round" aria-hidden="true"><img src="/media/julie.webp" alt="" class="admin-record__avatar-image"></span>', $html);
        self::assertStringContainsString('<span class="admin-record__avatar" aria-hidden="true">JH</span>', $html, 'Sans photo, les initiales.');
    }

    public function testAnEmptyPropertyReadsAsADashAndMarkupItIsHandedStaysMarkup(): void
    {
        $html = $this->render("{% import '@Admin/record/_macros.html.twig' as record %}
            {{ record.property('Phone', null, {icon: 'fa-phone'}) }}
            {{ record.property('Site', include('@Admin/record/_test_link.html.twig')) }}
            {{ record.property('Name', name) }}", ['name' => '<b>x</b>'], ['@Admin/record/_test_link.html.twig' => '<a href="https://acme.test">acme.test</a>']);

        self::assertMatchesRegularExpression('#<dt><i class="fa-solid fa-phone" aria-hidden="true"></i>Phone</dt>\s*<dd>—</dd>#', $html);
        self::assertStringContainsString('<dd><a href="https://acme.test">acme.test</a></dd>', $html);
        self::assertStringContainsString('<dd>&lt;b&gt;x&lt;/b&gt;</dd>', $html);
    }

    /**
     * ⚠️ The fold is only a phone's: from 1280 px the stylesheet shows the folded list and hides the
     * button (`StylesheetTest`). Here, the HTML: both lists are definition lists, the button says how
     * many details it opens and carries `aria-expanded`.
     */
    public function testPropertiesBeyondTheVisibleCountFoldBehindAButton(): void
    {
        $html = $this->render("{% import '@Admin/record/_macros.html.twig' as record %}
            {{ record.properties([
                {label: 'A', value: '1'}, {label: 'B', value: '2'}, {label: 'Hidden', value: 'x', condition: false},
                {label: 'C', value: '3'}, {label: 'D', value: '4'},
            ], {visible: 2}) }}");

        self::assertStringContainsString('<div class="admin-record__properties" data-controller="ui--collapsible">', $html);
        self::assertSame(2, substr_count($html, '<dl class="admin-record__props'));
        self::assertMatchesRegularExpression('#<dl class="admin-record__props">(?:(?!</dl>).)*<dt>A</dt>(?:(?!</dl>).)*<dt>B</dt>(?:(?!</dl>).)*</dl>#s', $html);
        self::assertMatchesRegularExpression('#<dl class="admin-record__props admin-record__props-extra" data-ui--collapsible-target="content">(?:(?!</dl>).)*<dt>C</dt>(?:(?!</dl>).)*<dt>D</dt>#s', $html);
        self::assertStringNotContainsString('Hidden', $html, 'A property whose condition is false is not rendered.');
        self::assertMatchesRegularExpression('#<button type="button" class="admin-record__props-toggle" aria-expanded="false"[^>]*data-ui--collapsible-target="trigger">\s*record\.properties\.show_all#', $html);
    }

    public function testPropertiesThatFitDoNotFold(): void
    {
        $html = $this->render("{% import '@Admin/record/_macros.html.twig' as record %}{{ record.properties([{label: 'A', value: '1'}]) }}");

        self::assertStringNotContainsString('data-controller', $html);
        self::assertStringNotContainsString('admin-record__props-toggle', $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAmounts(): iterable
    {
        yield 'fr' => ['48 500,00 €', '48 500<span class="admin-record__decimals">,00 €</span>'];
        yield 'en' => ['€2,450.00', '€2,450<span class="admin-record__decimals">.00</span>'];
        yield 'de' => ['1.234,56 €', '1.234<span class="admin-record__decimals">,56 €</span>'];
        yield 'no separator' => ['37', '37'];
    }

    /**
     * ⚠️ The decimal separator is the one nearest the end, whatever the locale: splitting on the
     * last comma would read an English `€2,450.00` as « €2 » and « ,450.00 ».
     */
    #[DataProvider('provideAmounts')]
    public function testAFigurePutsItsDecimalsInRetreatInAnyLocale(string $amount, string $expected): void
    {
        $html = $this->render("{% import '@Admin/record/_macros.html.twig' as record %}[{{ record.figure(amount) }}]", ['amount' => $amount]);

        self::assertSame('['.$expected.']', trim($html));
    }

    public function testAStatCarriesItsTrendItsToneAndItsGauge(): void
    {
        $html = $this->render("{% import '@Admin/record/_macros.html.twig' as record %}
            {{ record.stat('Billed', record.figure('84 230,00 €'), {trend: {label: '+12 %', direction: 'up'}, tone: 'success', hint: 'vs 2025'}) }}
            {{ record.stat('Warranty', 'until 2027', {progress: {value: 69}}) }}");

        self::assertMatchesRegularExpression('#<span class="admin-record__stat-value admin-record__stat-value--success">84 230<span class="admin-record__decimals">,00 €</span></span>#', $html);
        self::assertStringContainsString('<span class="admin-record__trend admin-record__trend--up"><i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i>+12 %</span>', $html);
        self::assertStringContainsString('<span class="admin-record__stat-hint">vs 2025</span>', $html);
        self::assertStringContainsString('<progress class="admin-record__progress" value="69" max="100">69</progress>', $html);
    }

    public function testTheStepsMarkTheCurrentOneAndCaptionThePosition(): void
    {
        $html = $this->render("{% import '@Admin/record/_macros.html.twig' as record %}
            {{ record.steps([
                {label: 'Draft', state: 'done', hint: '21/10'},
                {label: 'Scheduled', state: 'done'},
                {label: 'On site', state: 'current', tone: 'warning'},
                {label: 'Done'},
            ]) }}");

        self::assertStringContainsString('<p class="admin-record__steps-caption">record.steps.caption</p>', $html);
        self::assertSame(1, substr_count($html, 'aria-current="step"'));
        self::assertMatchesRegularExpression('#<li class="admin-record__step admin-record__step--current admin-record__step--warning" aria-current="step">#', $html);
        self::assertStringContainsString('<span class="admin-record__step-hint">21/10</span>', $html);
        self::assertMatchesRegularExpression('#<li class="admin-record__step">#', $html, 'A step still to come carries no modifier.');
    }

    /**
     * ⚠️ A pipeline stage is coloured by a user and the colour lands in a `style` attribute: anything
     * that is not a hexadecimal colour is dropped, never written.
     */
    public function testAStepColourIsWrittenOnlyWhenItIsAHexadecimalColour(): void
    {
        $html = $this->render("{% import '@Admin/record/_macros.html.twig' as record %}
            {{ record.steps([
                {label: 'Proposal', state: 'done', color: '#F59E0B'},
                {label: 'Negotiation', state: 'current', color: 'red;background:url(//evil.test)'},
            ]) }}");

        self::assertStringContainsString('style="--admin-step-color: #F59E0B"', $html);
        self::assertSame(1, substr_count($html, 'style='));
        self::assertStringNotContainsString('evil', $html);
    }

    /**
     * ⚠️ `<time>` without `datetime` must hold a valid date itself, and "26/10 09:12" is none: the
     * validator would refuse the page. The machine date makes it a `<time>`, its absence a `<span>`.
     */
    public function testATimelineWritesATimeElementOnlyWithAMachineDate(): void
    {
        $html = $this->render("{% import '@Admin/record/_macros.html.twig' as record %}
            {{ record.timeline([
                {title: 'On site', time: '09:07', datetime: '2026-10-26T09:07:00+01:00', state: 'current', text: 'Luc Meyer'},
                {title: 'On the way', time: '08:41'},
            ]) }}");

        self::assertStringContainsString('<time class="admin-record__event-time" datetime="2026-10-26T09:07:00+01:00">09:07</time>', $html);
        self::assertStringContainsString('<span class="admin-record__event-time">08:41</span>', $html);
        self::assertStringContainsString('<li class="admin-record__event admin-record__event--current">', $html);
        self::assertStringContainsString('<li class="admin-record__event admin-record__event--done">', $html);
        self::assertStringContainsString('<p class="admin-record__event-text">Luc Meyer</p>', $html);
    }

    public function testTabsAreLinksThatMarkTheOpenOne(): void
    {
        $html = $this->render("{% import '@Admin/record/_macros.html.twig' as record %}
            {{ record.tabs([
                {key: 'overview', label: 'Overview', href: '?tab=overview'},
                {key: 'contacts', label: 'Contacts', href: '?tab=contacts', count: 4},
                {key: 'deals', label: 'Deals', href: '?tab=deals', count: 0},
                {key: 'audit', label: 'History', href: '?tab=audit', condition: false},
            ], 'contacts') }}");

        self::assertStringContainsString('<nav class="admin-record__tabs" aria-label="record.tabs" data-preview-exclude>', $html);
        self::assertMatchesRegularExpression('#<a href="\?tab=contacts" class="admin-record__tab" aria-current="page">Contacts<span class="admin-record__tab-count">4</span></a>#', $html);
        self::assertSame(1, substr_count($html, 'aria-current'));
        self::assertStringContainsString('Deals<span class="admin-record__tab-count">0</span>', $html, 'Zero is a count: an empty tab says so.');
        self::assertStringNotContainsString('History', $html);
    }

    public function testTheMenuIsNotRenderedWithoutAVisibleItem(): void
    {
        $html = $this->render("{% from '@Admin/partials/_actions_dropdown.html.twig' import dropdown %}
            [{{ dropdown('Actions', [{label: 'Edit', href: '/edit', condition: false}, {divider: true}]) }}]");

        self::assertSame('[]', trim($html));
    }

    public function testTheMenuDropsTheDividersItsHiddenItemsLeaveBehind(): void
    {
        $html = $this->render("{% from '@Admin/partials/_actions_dropdown.html.twig' import dropdown %}
            {{ dropdown('Actions', [
                {divider: true},
                {label: 'Edit', icon: 'fa-pen', href: '/edit'},
                {divider: true},
                {label: 'Share', href: '/share', condition: false},
                {divider: true},
                {label: 'Delete', icon: 'fa-trash-can', danger: true, form_action: '/delete', csrf: 'delete',
                 confirm: {title: 'Delete?', message: 'Gone for good.'}},
                {divider: true},
            ]) }}");

        self::assertSame(1, substr_count($html, 'admin-dropdown__divider'), 'One divider between the two groups — none first, none doubled, none last.');
        self::assertMatchesRegularExpression('#<a href="/edit" class="admin-dropdown__item"><i class="fa-solid fa-pen" aria-hidden="true"></i>\s*<span>Edit</span>#', $html);
        self::assertMatchesRegularExpression('#<button type="submit" class="admin-dropdown__item admin-dropdown__item--danger">#', $html);
        self::assertStringContainsString('data-ui--modal-confirm-variant-value="danger"', $html);
        self::assertStringContainsString('data-ui--modal-cancel-label-value="record.cancel"', $html);
        self::assertMatchesRegularExpression('#<input type="hidden" name="_token" value="[^"]+">#', $html);
        self::assertStringContainsString('<div class="admin-dropdown__menu hidden" data-ui--dropdown-target="menu">', $html);
    }

    /** A transition is coloured like its button would be: « Confirm » green, « Ship » in the accent. */
    public function testAnItemTakesTheToneOfItsTransition(): void
    {
        $html = $this->render("{% from '@Admin/partials/_actions_dropdown.html.twig' import dropdown %}
            {{ dropdown('Actions', [
                {label: 'Confirm', href: '/confirm', tone: 'success'},
                {label: 'Ship', href: '/ship', tone: 'accent'},
                {label: 'Cancel', form_action: '/cancel', tone: 'danger', confirm: {title: 'Cancel?', message: 'Sure?'}},
            ]) }}");

        self::assertStringContainsString('<a href="/confirm" class="admin-dropdown__item admin-dropdown__item--success">', $html);
        self::assertStringContainsString('<a href="/ship" class="admin-dropdown__item admin-dropdown__item--accent">', $html);
        self::assertStringContainsString('<button type="submit" class="admin-dropdown__item admin-dropdown__item--danger">', $html);
        self::assertStringContainsString('data-ui--modal-confirm-variant-value="danger"', $html, 'A red item opens a red modal.');
    }

    public function testACompactMenuNamesItselfForReaders(): void
    {
        $html = $this->render("{% from '@Admin/partials/_actions_dropdown.html.twig' import dropdown %}
            {{ dropdown('Row actions', [{label: 'Edit', href: '/edit'}], {compact: true}) }}");

        self::assertMatchesRegularExpression('#<button type="button" class="btn-secondary" data-action="click->ui--dropdown\#toggle" aria-haspopup="true" aria-expanded="false" aria-label="Row actions" title="Row actions">#', $html);
        self::assertStringNotContainsString('admin-dropdown__chevron', $html);
    }

    /**
     * ⚠️ **The consumers do not scan this bundle's templates**: a utility class written here exists
     * in no product's stylesheet (the lesson of `.admin-touch-row`). Every class the record templates
     * write is either declared by `components.css` or one of the families a product always has.
     */
    public function testEveryClassTheRecordTemplatesWriteIsDeclaredByTheBundle(): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2).'/assets/styles/components.css');
        $missing = [];

        foreach (['record/_page.html.twig', 'record/_macros.html.twig', 'partials/_actions_dropdown.html.twig'] as $template) {
            $source = (string) file_get_contents(\dirname(__DIR__, 2).'/Resources/views/'.$template);
            // Twig comments carry usage examples, whose classes are the product's.
            $source = (string) preg_replace('/\{#.*?#\}/s', '', $source);

            // ⚠️ A class built by concatenation is read by no scanner, so Tailwind purges it: the
            // overdue maintenance of a cegeta item stayed black because `--danger` was composed.
            self::assertDoesNotMatchRegularExpression('/--\'\s*~|--\{\{/', $source, $template.' builds a class by concatenation.');
            preg_match_all('/class="([^"]*)"/', $source, $matches);

            foreach ($matches[1] as $attribute) {
                // A class built by Twig is checked through its literal prefix only.
                $attribute = (string) preg_replace('/\{\{.*?\}\}/s', ' ', $attribute);

                foreach (preg_split('/\s+/', trim($attribute)) ?: [] as $class) {
                    if ('' === $class || preg_match('/^(fa-|btn-|badge-|panel$|hidden$)/', $class)) {
                        continue;
                    }

                    if (!str_contains($css, '.'.$class.' ') && !str_contains($css, '.'.$class.'.') && !str_contains($css, '.'.$class.',') && !str_contains($css, '.'.$class.'[') && !str_contains($css, '.'.$class.':') && !str_contains($css, '.'.$class.'>')) {
                        $missing[] = $template.' → '.$class;
                    }
                }
            }
        }

        self::assertSame([], $missing);
    }

    /**
     * @param array<string, mixed>  $context
     * @param array<string, string> $extraTemplates
     */
    private function render(string $source, array $context = [], array $extraTemplates = []): string
    {
        $container = $this->boot();

        $requestStack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $request = Request::create('http://localhost/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($request);

        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        if ([] !== $extraTemplates) {
            $twig->setLoader(new ChainLoader([new ArrayLoader($extraTemplates), $twig->getLoader()]));
        }

        return $twig->createTemplate($source)->render($context);
    }
}
