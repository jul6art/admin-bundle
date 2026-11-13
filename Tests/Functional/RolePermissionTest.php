<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AclBundle\Doctrine\DoctrinePermissionStore;
use Jul6Art\AclBundle\Doctrine\PermissionSeeder;
use Jul6Art\AclBundle\Security\AclPermissionReadService;
use Jul6Art\AdminBundle\Controller\RolePermissionController;
use Jul6Art\AdminBundle\Tests\Fixtures\Acl\Entity\Member;
use Jul6Art\AdminBundle\Tests\Fixtures\Acl\Entity\Organization;
use Jul6Art\AdminBundle\Tests\Fixtures\Acl\Entity\RolePermission;
use Jul6Art\AdminBundle\Tests\Fixtures\Acl\PermissionCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The grid of the roles' permissions (acl-bundle's ADR-0001, phase 2b), through the real container: acl-bundle's
 * storage on SQLite, the engine's voter, the CSRF manager, the application's template. One grid for everyone
 * (devingame), an extra access (cegeta), the grid by tenant (cereezer), the escalation guard and the reset
 * (tradebot).
 */
#[CoversClass(RolePermissionController::class)]
final class RolePermissionTest extends AbstractFunctionalTestCase
{
    private ContainerInterface $container;
    private EntityManagerInterface $entityManager;

    public function testOffByDefaultTheApplicationKeepsItsOwnScreen(): void
    {
        $container = $this->boot('test', [], withAcl: true);

        self::assertFalse($container->has(RolePermissionController::class));
    }

    public function testTheTemplateReceivesItsContract(): void
    {
        $this->grid();
        $this->signIn(new Member('root@example.test', ['ROLE_SUPER_ADMIN']));

        $contract = $this->index();

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $contract['roles']);
        self::assertSame(['permission' => ['permission:read', 'permission:update'], 'user' => ['user:read', 'user:update'], 'invoice' => ['invoice:cancel']], $contract['grouped']);
        self::assertEqualsCanonicalizing(['permission:read', 'permission:update', 'user:read', 'user:update'], self::column($contract['granted'], 'ROLE_ADMIN'));
        self::assertSame(['user:read'], self::column($contract['granted'], 'ROLE_USER'));
        self::assertSame(['invoice:cancel'], $contract['superAdminOnly']);
        self::assertTrue(self::column($contract['changeable'], 'ROLE_USER')['invoice:cancel'] ?? null, 'Without the guard, every cell changes.');
        self::assertNull($contract['organization']);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $this->container->getParameter('admin.role_permissions.roles'));
    }

    public function testTheGridIsSavedAsTicked(): void
    {
        $this->grid();
        $this->signIn(new Member('root@example.test', ['ROLE_SUPER_ADMIN']));

        $response = $this->save(['ROLE_ADMIN' => ['permission:read', 'permission:update', 'user:read', 'user:update'], 'ROLE_USER' => ['user:update']]);

        self::assertSame('/admin/role-permissions', $response->getTargetUrl());
        self::assertSame(['success' => ['permission.flash.saved']], $this->flashes());
        self::assertSame(['user:update'], $this->store()->grantedForRoles(['ROLE_USER']));
        self::assertFalse($this->row('ROLE_USER', 'user:read')?->isGranted(), 'Unticked: a refusal kept, never a removed row.');
    }

    public function testAForgedTokenChangesNothing(): void
    {
        $this->grid();
        $this->signIn(new Member('root@example.test', ['ROLE_SUPER_ADMIN']));

        $this->save(['ROLE_USER' => []], token: 'forged');

        self::assertSame(['error' => ['flash.invalid_csrf']], $this->flashes());
        self::assertSame(['user:read'], $this->store()->grantedForRoles(['ROLE_USER']));
    }

    public function testReadingAndSavingAskTheirOwnAttributes(): void
    {
        $this->grid();
        $this->signIn(new Member('ada@example.test', ['ROLE_USER']));

        $this->expectException(AccessDeniedException::class);

        $this->index();
    }

    public function testAnExtraAccessClosesTheScreenToTheTenantsAdministrators(): void
    {
        $this->grid(['access' => 'ROLE_SUPER_ADMIN']);
        $this->signIn(new Member('admin@example.test', ['ROLE_ADMIN']));

        $this->expectException(AccessDeniedException::class);

        $this->index();
    }

    public function testATenantsAdministratorEditsItsTenantOnly(): void
    {
        $this->grid(['scope' => 'tenant']);
        $acme = new Organization('acme');
        $this->entityManager->persist($acme);
        $this->signIn(new Member('admin@acme.test', ['ROLE_ADMIN'], $acme));

        self::assertSame('acme', $this->index()['organization']);
        // Acme gives its users user:update; user:read stays as the platform says.
        $this->save(['ROLE_ADMIN' => PermissionCatalog::DEFAULTS['ROLE_ADMIN'], 'ROLE_USER' => ['user:read', 'user:update']]);

        self::assertSame(1, $this->entityManager->getRepository(RolePermission::class)->count(['organization' => $acme]), 'One difference, one row.');
        self::assertEqualsCanonicalizing(['user:read', 'user:update'], $this->store()->grantedForRoles(['ROLE_USER'], $acme));
        self::assertSame(['user:read'], $this->store()->grantedForRoles(['ROLE_USER']), 'The platform does not move.');
    }

    public function testTheGuardLocksWhatTheAccountDoesNotHold(): void
    {
        $this->grid(['escalation_guard' => true]);
        $root = new Member('root@example.test', ['ROLE_SUPER_ADMIN']);
        $admin = new Member('admin@example.test', ['ROLE_ADMIN']);
        $this->signIn($root);
        $this->save(['ROLE_ADMIN' => PermissionCatalog::DEFAULTS['ROLE_ADMIN'], 'ROLE_USER' => ['user:read', 'invoice:cancel']]);

        $this->signIn($admin);
        $contract = $this->index();
        self::assertFalse(self::column($contract['changeable'], 'ROLE_USER')['invoice:cancel'] ?? null, 'Not held by the administrator: locked.');
        self::assertTrue(self::column($contract['changeable'], 'ROLE_USER')['user:update'] ?? null);

        // The locked cell is disabled, so not posted: its absence keeps the grant.
        $this->save(['ROLE_ADMIN' => PermissionCatalog::DEFAULTS['ROLE_ADMIN'], 'ROLE_USER' => ['user:read', 'user:update']]);
        self::assertEqualsCanonicalizing(['user:read', 'user:update', 'invoice:cancel'], $this->store()->grantedForRoles(['ROLE_USER']));

        // Forging the grant of a locked cell is refused.
        $this->store()->revokeFromRole('ROLE_USER', 'invoice:cancel', null);
        $this->entityManager->flush();
        $this->expectException(AccessDeniedException::class);
        $this->save(['ROLE_ADMIN' => PermissionCatalog::DEFAULTS['ROLE_ADMIN'], 'ROLE_USER' => ['user:read', 'invoice:cancel']]);
    }

    public function testTheResetBringsBackTheDefaultsItMayChange(): void
    {
        $this->grid(['escalation_guard' => true]);
        $this->signIn(new Member('root@example.test', ['ROLE_SUPER_ADMIN']));
        // The users lose user:read, which the administrator holds, and get invoice:cancel, which they do not.
        $this->save(['ROLE_ADMIN' => PermissionCatalog::DEFAULTS['ROLE_ADMIN'], 'ROLE_USER' => ['invoice:cancel']]);
        $this->flashes();

        $this->signIn(new Member('admin@example.test', ['ROLE_ADMIN']));
        $response = $this->controller()->reset($this->post([]));

        self::assertSame('/admin/role-permissions', $response->getTargetUrl());
        self::assertSame(['success' => ['permission.flash.reset']], $this->flashes());
        self::assertEqualsCanonicalizing(PermissionCatalog::DEFAULTS['ROLE_ADMIN'], $this->store()->grantedForRoles(['ROLE_ADMIN']));
        self::assertEqualsCanonicalizing(['user:read', 'invoice:cancel'], $this->store()->grantedForRoles(['ROLE_USER']), 'user:read comes back; invoice:cancel, which the administrator does not hold, stays as the super admin left it.');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function grid(array $config = []): void
    {
        $this->container = $this->boot('test', ['role_permissions' => ['enabled' => true, 'template' => 'role_permission.html.twig'] + $config], withAcl: true);
        $entityManager = $this->container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $seeder = $this->container->get(PermissionSeeder::class);
        self::assertInstanceOf(PermissionSeeder::class, $seeder);
        $seeder->seed();

        $request = Request::create('/admin/role-permissions');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack = $this->container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $stack->push($request);
    }

    private function signIn(Member $member): void
    {
        if (null === $member->getId()) {
            $this->entityManager->persist($member);
            $this->entityManager->flush();
        }
        $tokens = $this->container->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken($member, 'main', $member->getRoles()));
        // The engine keeps its answers per account and per request: a new request, a fresh read.
        $read = $this->container->get(AclPermissionReadService::class);
        self::assertInstanceOf(AclPermissionReadService::class, $read);
        $read->flush($member);
    }

    /**
     * What the controller handed the template, decoded.
     *
     * @return array<mixed>
     */
    private function index(): array
    {
        $contract = json_decode((string) $this->controller()->index()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($contract);

        return $contract;
    }

    /**
     * One role's column of a matrix the template received.
     *
     * @return array<mixed>
     */
    private static function column(mixed $matrix, string $role): array
    {
        self::assertIsArray($matrix);
        $column = $matrix[$role] ?? null;
        self::assertIsArray($column);

        return $column;
    }

    /**
     * @param array<string, list<string>> $ticked
     */
    private function save(array $ticked, ?string $token = null): RedirectResponse
    {
        $permissions = [];
        foreach ($ticked as $role => $codes) {
            $permissions[$role] = array_fill_keys($codes, '1');
        }

        return $this->controller()->save($this->post(['permissions' => $permissions], $token));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(array $body, ?string $token = null): Request
    {
        $stack = $this->container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $csrf = $this->container->get('security.csrf.token_manager');
        self::assertInstanceOf(CsrfTokenManagerInterface::class, $csrf);
        $body['_token'] = $token ?? $csrf->getToken(RolePermissionController::CSRF_TOKEN_ID)->getValue();
        $request = Request::create('/admin/role-permissions', 'POST', $body);
        $request->setSession($stack->getCurrentRequest()?->getSession() ?? new Session(new MockArraySessionStorage()));
        $stack->push($request);

        return $request;
    }

    /**
     * @return array<string, list<string>>
     */
    private function flashes(): array
    {
        $stack = $this->container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $session = $stack->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        /** @var array<string, list<string>> $flashes */
        $flashes = $session->getFlashBag()->all();

        return $flashes;
    }

    private function controller(): RolePermissionController
    {
        $controller = $this->container->get(RolePermissionController::class);
        self::assertInstanceOf(RolePermissionController::class, $controller);

        return $controller;
    }

    private function store(): DoctrinePermissionStore
    {
        $store = $this->container->get(DoctrinePermissionStore::class);
        self::assertInstanceOf(DoctrinePermissionStore::class, $store);

        return $store;
    }

    private function row(string $role, string $code): ?RolePermission
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(RolePermission::class)->findOneBy(['roleCode' => $role, 'permission' => $code, 'organization' => null]);
    }
}
