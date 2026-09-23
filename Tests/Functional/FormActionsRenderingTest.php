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
 * The cancel link of `_form_actions` can carry a keyboard action of the product's own.
 *
 * superp wanted "back to the list" on a key (Ctrl+B), overridable like the others. The link is
 * rendered HERE, so a product could not reach it without copying the partial — and a copy is what
 * this partial exists to prevent. The action stays the product's: nothing is added to the shell's
 * catalogue, and a product that passes nothing renders exactly what it rendered before.
 */
#[CoversNothing]
final class FormActionsRenderingTest extends AbstractFunctionalTestCase
{
    private const array CONFIG = ['keyboard' => ['actions' => [
        'form.back' => ['default' => 'ctrl+b', 'label' => 'keyboard.action.form_back'],
    ]]];

    public function testTheCancelLinkCarriesTheActionItIsHanded(): void
    {
        $html = $this->render("{{ include('@Admin/partials/_form_actions.html.twig', {
            save_label: 'Save', cancel_url: '/items', cancel_label: 'Cancel',
            cancel_shortcut: 'form.back', cancel_shortcut_label: 'Back to the list',
        }) }}");

        self::assertMatchesRegularExpression(
            '#<a href="/items"[^>]*data-shortcut="ctrl\+b"[^>]*data-shortcut-label="Back to the list"#',
            $html,
            'Le lien d\'annulation doit porter la combinaison EN VIGUEUR de l\'action, et son libellé pour l\'antisèche.',
        );
        self::assertMatchesRegularExpression('#<a href="/items"[^>]*data-shortcut-hint="ctrl\+b"#', $html, 'Comme les deux boutons, il affiche son raccourci.');
    }

    public function testWithoutAnActionTheCancelLinkIsUnchanged(): void
    {
        $html = $this->render("{{ include('@Admin/partials/_form_actions.html.twig', {
            save_label: 'Save', cancel_url: '/items', cancel_label: 'Cancel',
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
