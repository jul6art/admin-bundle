<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\AclBundle\Contract\AclTenantInterface;
use Jul6Art\AclBundle\Contract\AclUserInterface;
use Jul6Art\AclBundle\Contract\PermissionCatalogInterface;
use Jul6Art\AclBundle\Doctrine\DoctrinePermissionStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who may do what: the grid of the roles' permissions (acl-bundle's ADR-0001, phase 2b), on acl-bundle's
 * storage and the application's catalog. The template is the application's (`admin.role_permissions.template`):
 * eight screens, five renderings, each fixed on screen for a reason of its own. This controller hands it a fixed
 * contract — `roles`, `grouped`, `granted`, `defaults`, `superAdminOnly`, `changeable`, `organization`.
 *
 * - **The super admin is no column of the grid**: the engine lets it through before reading the storage, so an
 *   unticked cell would take nothing from it while saying it did — and, made removable, would let someone lock
 *   themselves out.
 * - **One transaction for the whole grid**: fifty cells never become fifty writes, half of which would survive
 *   an error in the middle.
 * - **The scope** (`scope: tenant`): an account edits its own tenant's grid, where only what differs from the
 *   platform's default is written; the super admin edits the platform.
 * - **The escalation guard** (`escalation_guard`): a cell changes only if the account holds its code; forging
 *   the grant of a locked cell is refused, its absence from the form (a disabled box is not posted) changes
 *   nothing.
 *
 * No route is declared: the application names them (`admin_role_permission_index`, `…_save`, `…_reset`).
 */
final class RolePermissionController extends AbstractController
{
    public const string CSRF_TOKEN_ID = 'role_permissions';
    public const string SCOPE_PLATFORM = 'platform';
    public const string SCOPE_TENANT = 'tenant';

    /**
     * @param list<string> $roles
     */
    public function __construct(
        private readonly DoctrinePermissionStore $store,
        private readonly PermissionCatalogInterface $catalog,
        private readonly EntityManagerInterface $entityManager,
        private readonly array $roles,
        private readonly string $readAttribute,
        private readonly string $updateAttribute,
        private readonly ?string $accessAttribute,
        private readonly string $scope,
        private readonly bool $escalationGuard,
        private readonly string $template,
        private readonly string $indexRoute,
    ) {
    }

    public function index(): Response
    {
        $this->denyUnlessAllowed($this->readAttribute);
        $tenant = $this->tenant();

        return $this->render($this->template, [
            'roles' => $this->roles,
            'grouped' => $this->catalog->grouped(),
            'granted' => $this->store->grantedMatrix($this->roles, $tenant),
            'defaults' => $this->catalog->defaults(),
            'superAdminOnly' => $this->catalog->superAdminOnly(),
            'changeable' => $this->changeable(),
            'organization' => $tenant,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $this->denyUnlessAllowed($this->updateAttribute);
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');

            return $this->redirectToRoute($this->indexRoute);
        }

        $submitted = $request->request->all('permissions');
        $wanted = [];
        foreach ($this->roles as $role) {
            $ticked = $submitted[$role] ?? [];
            $wanted[$role] = \is_array($ticked) ? array_values(array_map(strval(...), array_keys($ticked))) : [];
        }
        $this->apply($wanted, forgedIsDenied: true);
        $this->addFlash('success', 'permission.flash.saved');

        return $this->redirectToRoute($this->indexRoute);
    }

    /**
     * Back to the catalog's defaults, for the cells this account may change — the others keep what they hold.
     */
    public function reset(Request $request): RedirectResponse
    {
        $this->denyUnlessAllowed($this->updateAttribute);
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');

            return $this->redirectToRoute($this->indexRoute);
        }

        $defaults = $this->catalog->defaults();
        $wanted = [];
        foreach ($this->roles as $role) {
            $wanted[$role] = $defaults[$role] ?? [];
        }
        $this->apply($wanted, forgedIsDenied: false);
        $this->addFlash('success', 'permission.flash.reset');

        return $this->redirectToRoute($this->indexRoute);
    }

    /**
     * @param array<string, list<string>> $wanted role → the codes it should hold
     */
    private function apply(array $wanted, bool $forgedIsDenied): void
    {
        $tenant = $this->tenant();
        if ($this->escalationGuard) {
            $granted = $this->store->grantedMatrix($this->roles, $tenant);
            $changeable = $this->changeable();
            foreach ($this->roles as $role) {
                foreach ($this->catalog->all() as $code) {
                    $want = \in_array($code, $wanted[$role] ?? [], true);
                    $has = \in_array($code, $granted[$role] ?? [], true);
                    if ($want === $has || ($changeable[$role][$code] ?? false)) {
                        continue;
                    }
                    if ($want && $forgedIsDenied) {
                        throw $this->createAccessDeniedException('This account may not grant a permission it does not hold.');
                    }
                    // A locked cell keeps what it holds.
                    $wanted[$role] = $has ? [...$wanted[$role] ?? [], $code] : array_values(array_diff($wanted[$role] ?? [], [$code]));
                }
            }
        }

        $this->store->saveRoleGrid($this->roles, $this->catalog->all(), $wanted, $tenant);
        $this->entityManager->flush();
    }

    /**
     * Role → code → whether this account may change the cell: every cell without the guard; with it, the codes
     * the account holds itself (a super admin holds them all).
     *
     * @return array<string, array<string, bool>>
     */
    private function changeable(): array
    {
        $mayUpdate = $this->isGranted($this->updateAttribute);
        $changeable = [];
        foreach ($this->catalog->all() as $code) {
            $cell = $mayUpdate && (!$this->escalationGuard || $this->isGranted($code));
            foreach ($this->roles as $role) {
                $changeable[$role][$code] = $cell;
            }
        }

        return $changeable;
    }

    /**
     * The grid's scope: the account's tenant when the grid varies by tenant — the super admin edits the
     * platform's defaults —, the platform otherwise.
     */
    private function tenant(): ?AclTenantInterface
    {
        if (self::SCOPE_TENANT !== $this->scope) {
            return null;
        }

        $user = $this->getUser();

        return $user instanceof AclUserInterface && !$user->isSuperAdmin() ? $user->getTenant() : null;
    }

    private function denyUnlessAllowed(string $attribute): void
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->denyAccessUnlessGranted($attribute);
        if (null !== $this->accessAttribute) {
            $this->denyAccessUnlessGranted($this->accessAttribute);
        }
    }
}
