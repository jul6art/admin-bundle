<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;

/**
 * The cancel link of `_form_actions` answers `form.back` — `Ctrl+B`, the keyboard twin of Cancel.
 *
 * Since 1.20 it is a SHELL action, on by default: the decider replaced the `Esc` back-jump with it on
 * 2026-09-23, for every product. A product may hand the link another action, or none.
 */
#[CoversNothing]
final class FormActionsRenderingTest extends AbstractFunctionalTestCase
{
    private const array CONFIG = ['keyboard' => ['actions' => [
        'app.leave' => ['default' => 'alt+q', 'label' => 'keyboard.action.app_leave'],
    ]]];

    /**
     * ⚠️ **On by default since 1.20**: `form.back` is a shell action, and every product's Cancel link
     * answers `Ctrl+B` — or the combo an organisation chose.
     */
    public function testTheCancelLinkAnswersTheBackActionByDefault(): void
    {
        $html = $this->render("{{ include('@Admin/partials/_form_actions.html.twig', {
            save_label: 'Save', cancel_url: '/items', cancel_label: 'Cancel',
        }) }}");

        self::assertMatchesRegularExpression('#<a href="/items"[^>]*data-shortcut="ctrl\+b"[^>]*data-shortcut-label="Cancel"#', $html);
        self::assertMatchesRegularExpression('#<a href="/items"[^>]*data-shortcut-hint="ctrl\+b"#', $html, 'Comme les deux boutons, il affiche son raccourci.');
    }

    public function testTheCancelLinkCarriesAnotherActionItIsHanded(): void
    {
        $html = $this->render("{{ include('@Admin/partials/_form_actions.html.twig', {
            save_label: 'Save', cancel_url: '/items', cancel_label: 'Cancel',
            cancel_shortcut: 'app.leave', cancel_shortcut_label: 'Leave',
        }) }}");

        self::assertMatchesRegularExpression('#<a href="/items"[^>]*data-shortcut="alt\+q"[^>]*data-shortcut-label="Leave"#', $html);
    }

    public function testAProductCanTakeTheShortcutOffTheCancelLink(): void
    {
        $html = $this->render("{{ include('@Admin/partials/_form_actions.html.twig', {
            save_label: 'Save', cancel_url: '/items', cancel_label: 'Cancel', cancel_shortcut: false,
        }) }}");

        self::assertMatchesRegularExpression('#<a href="/items" class="btn-secondary">#', $html);
        self::assertStringNotContainsString('data-shortcut=', $html);
    }

    private function render(string $source): string
    {
        $container = $this->boot(bundleConfig: self::CONFIG);

        $requestStack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $request = Request::create('http://localhost/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->attributes->set('_route', 'admin_widget_index');
        $requestStack->push($request);

        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->createTemplate($source)->render();
    }
}
