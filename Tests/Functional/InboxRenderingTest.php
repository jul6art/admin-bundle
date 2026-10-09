<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Dom\Element;
use Dom\HTMLDocument;
use Jul6Art\AdminBundle\Tests\Fixtures\InboxMessage;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;

/**
 * La messagerie (1.25) : ce que le gabarit rend d'une liste de messages, avec ou sans message
 * ouvert, avec ou sans les actions clavier que l'application déclare.
 */
#[CoversNothing]
final class InboxRenderingTest extends AbstractFunctionalTestCase
{
    private const array INBOX_KEYBOARD = ['keyboard' => ['actions' => [
        'inbox.previous' => ['default' => 'k', 'label' => 'keyboard.action.inbox_previous'],
        'inbox.next' => ['default' => 'j', 'label' => 'keyboard.action.inbox_next'],
    ]]];

    public function testTheListLinksEveryMessageAndSaysWhichAreUnread(): void
    {
        $page = $this->render($this->inbox());

        $items = self::all($page, '.admin-inbox__item');
        self::assertCount(3, $items);
        self::assertSame('/admin/inbox/1', $items[0]->getAttribute('href'));
        self::assertSame('/admin/inbox/3', $items[2]->getAttribute('href'));

        // Le point n'est pas une information pour un lecteur d'écran : le mot l'est.
        self::assertStringContainsString('inbox.unread', (string) $items[0]->textContent);
        self::assertStringNotContainsString('inbox.unread', (string) $items[1]->textContent);
        self::assertTrue($items[0]->classList->contains('is-unread'));

        self::assertSame('2026-10-01T09:30:00+00:00', self::one($items[0], 'time')->getAttribute('datetime'));
        self::assertSame('status.answered', self::text(self::one($items[1], '.admin-inbox__status')));
        self::assertSame('list', self::one($page, '.admin-inbox')->getAttribute('data-inbox-pane'));
        self::assertSame('inbox.select', self::text(self::one($page, '.admin-inbox__placeholder')));
    }

    public function testTheFoldersSayWhereWeAre(): void
    {
        $folders = self::all($this->render($this->inbox()), '.admin-inbox__folder');

        self::assertCount(2, $folders);
        self::assertSame('page', $folders[0]->getAttribute('aria-current'));
        self::assertNull($folders[1]->getAttribute('aria-current'));
        self::assertSame('2', self::text(self::one($folders[0], '.admin-inbox__count')));
        self::assertNull($folders[1]->querySelector('.admin-inbox__count'), 'Un dossier vide n\'affiche pas de zéro.');
    }

    public function testAnOpenMessageGetsTheReaderAndItsNeighbours(): void
    {
        $page = $this->render($this->inbox(current: 2));

        self::assertSame('reader', self::one($page, '.admin-inbox')->getAttribute('data-inbox-pane'));
        $items = self::all($page, '.admin-inbox__item');
        self::assertSame('true', $items[1]->getAttribute('aria-current'));
        self::assertNull($items[0]->getAttribute('aria-current'));

        self::assertSame('Grace Hopper', self::text(self::one($page, '#admin-inbox-subject')));
        self::assertSame('mailto:grace@example.com', self::one($page, '.admin-inbox__email')->getAttribute('href'));
        self::assertSame('ACTION-ARCHIVE', self::text(self::one($page, '.admin-inbox__actions')));
        self::assertSame('BODY-OF-THE-MESSAGE', self::text(self::one($page, '.admin-inbox__body')));

        self::assertSame(['/admin/inbox', '/admin/inbox/1', '/admin/inbox/3'], self::hrefs($page, '.admin-inbox__nav a'));
    }

    public function testTheFirstAndLastMessagesHaveOneNeighbourEach(): void
    {
        self::assertSame(['/admin/inbox', '/admin/inbox/2'], self::hrefs($this->render($this->inbox(current: 1)), '.admin-inbox__nav a'), 'Le retour et le suivant.');
        self::assertSame(['/admin/inbox', '/admin/inbox/2'], self::hrefs($this->render($this->inbox(current: 3)), '.admin-inbox__nav a'), 'Le retour et le précédent.');
    }

    public function testUndeclaredKeyboardActionsLeavePlainLinks(): void
    {
        self::assertSame([], self::all($this->render($this->inbox(current: 2)), '.admin-inbox__nav [data-shortcut]'));
    }

    public function testDeclaredKeyboardActionsBecomeShortcuts(): void
    {
        $page = $this->render($this->inbox(current: 2), self::INBOX_KEYBOARD);

        $shortcuts = self::all($page, '.admin-inbox__nav [data-shortcut]');
        self::assertSame(['k', 'j'], array_map(static fn (Element $link): ?string => $link->getAttribute('data-shortcut'), $shortcuts));
        self::assertSame('/admin/inbox/3', self::one($page, '[data-shortcut="j"]')->getAttribute('href'));
    }

    /** Un dossier plus long que sa tranche : un lien vers la suivante, que `ui--inbox` suit au défilement. */
    public function testALongFolderLinksItsOlderSlice(): void
    {
        $page = $this->render(['more_url' => '/admin/inbox?before=3'] + $this->inbox());

        $link = self::one($page, '[data-ui--inbox-target="list"] [data-ui--inbox-target="more"] a');
        self::assertSame('/admin/inbox?before=3', $link->getAttribute('href'));
        self::assertSame('ui--inbox#more', $link->getAttribute('data-action'));
        self::assertSame('inbox.more', self::text($link));
        self::assertSame('inbox.loaded', self::one($page, '.admin-inbox')->getAttribute('data-ui--inbox-loaded-value'));
        self::assertSame('polite', self::one($page, '[data-ui--inbox-target="status"]')->getAttribute('aria-live'));
    }

    public function testAFolderThatFitsHasNoOlderLink(): void
    {
        $page = $this->render($this->inbox());

        self::assertSame([], self::all($page, '[data-ui--inbox-target="more"]'));
        self::assertNull(self::one($page, '.admin-inbox')->getAttribute('data-ui--inbox-loaded-value'), 'Sans tranche suivante, pas de clé à traduire.');
    }

    public function testAnEmptyFolderSaysSo(): void
    {
        $page = $this->render(['folders' => [], 'messages' => [], 'current' => null, 'message_route' => 'admin_inbox_show']);

        self::assertSame([], self::all($page, '.admin-inbox__item'));
        self::assertSame('inbox.empty', self::text(self::one($page, '.admin-inbox__empty')));
    }

    /**
     * Trois messages : un non lu, un répondu, un lu ; deux dossiers, le premier ouvert.
     *
     * @return array<string, mixed>
     */
    private function inbox(?int $current = null): array
    {
        $messages = [
            new InboxMessage(1, 'Ada Lovelace', 'ada@example.com', 'About the engine.', new \DateTimeImmutable('2026-10-01 09:30:00+00:00')),
            new InboxMessage(2, 'Grace Hopper', 'grace@example.com', 'A compiler, please.', new \DateTimeImmutable('2026-09-30 17:00:00+00:00'), read: true, status: 'status.answered'),
            new InboxMessage(3, 'Alan Turing', 'alan@example.com', 'Can machines think?', new \DateTimeImmutable('2026-09-29 08:00:00+00:00'), read: true),
        ];

        return [
            'folders' => [
                ['label' => 'Received', 'url' => '/admin/inbox', 'count' => 2, 'active' => true],
                ['label' => 'Archived', 'url' => '/admin/inbox?folder=archived', 'count' => 0, 'active' => false],
            ],
            'messages' => $messages,
            'current' => null === $current ? null : $messages[$current - 1],
            'message_route' => 'admin_inbox_show',
            'translation_domain' => 'contact',
        ];
    }

    /**
     * @param array<string, mixed> $inbox
     * @param array<string, mixed> $bundleConfig
     */
    private function render(array $inbox, array $bundleConfig = []): HTMLDocument
    {
        $container = $this->boot(bundleConfig: $bundleConfig);
        $this->pushRequest($container);

        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return HTMLDocument::createFromString($twig->render('inbox_page.html.twig', ['inbox' => $inbox]), \LIBXML_NOERROR);
    }

    private static function one(HTMLDocument|Element $root, string $selector): Element
    {
        $element = $root->querySelector($selector);
        self::assertInstanceOf(Element::class, $element, \sprintf('Rien ne répond à « %s ».', $selector));

        return $element;
    }

    /**
     * @return list<Element>
     */
    private static function all(HTMLDocument|Element $root, string $selector): array
    {
        return array_values(array_filter(iterator_to_array($root->querySelectorAll($selector)), static fn (mixed $node): bool => $node instanceof Element));
    }

    /**
     * @return list<?string>
     */
    private static function hrefs(HTMLDocument $page, string $selector): array
    {
        return array_map(static fn (Element $link): ?string => $link->getAttribute('href'), self::all($page, $selector));
    }

    private static function text(Element $element): string
    {
        return trim((string) $element->textContent);
    }

    private function pushRequest(ContainerInterface $container): void
    {
        $requestStack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);

        $request = Request::create('http://localhost/admin/inbox');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->attributes->set('_route', 'admin_inbox_index');
        $requestStack->push($request);
    }
}
