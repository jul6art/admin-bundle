<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Account;

use Symfony\Component\HttpFoundation\Request;

/**
 * The spaces of the sign-in screens: a set of templates, of route names and of template variables, chosen by the
 * `_auth_space` default of the route that served the request — `admin` when it has none.
 *
 * A second space is a second door to the same accounts: cereezer's customer portal (`/portal`, its own sign-in
 * card, its own firewall), cegeta's customer area (`/app`, its own layout). The screens are the same code; only
 * what they render and where they send the visitor next differ. A space declares only what differs from `admin`.
 *
 * ```yaml
 * admin:
 *     account:
 *         spaces:
 *             portal:
 *                 templates: { login: 'portal/security/login.html.twig' }
 *                 routes: { login: portal_security_login }
 *                 redirect_authenticated_to: portal_dashboard
 * ```
 */
final readonly class AccountSpaces
{
    /** The route default naming the space. */
    public const string ATTRIBUTE = '_auth_space';

    public const string DEFAULT = 'admin';

    /** @var array<string, string> */
    public const array ROUTES = [
        'login' => 'admin_security_login',
        'register' => 'admin_security_register',
        'reset_password_request' => 'admin_reset_password_request',
        'reset_password_check_email' => 'admin_reset_password_check_email',
        'reset_password_reset' => 'admin_reset_password_reset',
        'profile' => 'admin_account_profile_edit',
    ];

    /** @var array<string, string> */
    public const array TEMPLATES = [
        'login' => '@Admin/security/login.html.twig',
        'register' => '@Admin/security/register.html.twig',
        'reset_password_request' => '@Admin/security/reset_password_request.html.twig',
        'check_email' => '@Admin/security/check_email.html.twig',
        'reset_password' => '@Admin/security/reset_password.html.twig',
        // The profile has no template in the bundle: its page lives in the application's shell.
        'profile' => 'admin/account/profile.html.twig',
    ];

    /**
     * @param array<string, array{routes?: array<string, string>, templates?: array<string, string>, context?: array<string, mixed>, redirect_authenticated_to?: ?string}> $spaces
     */
    public function __construct(
        private array $spaces = [],
    ) {
    }

    public function name(Request $request): string
    {
        $space = $request->attributes->get(self::ATTRIBUTE);

        return \is_string($space) && '' !== $space ? $space : self::DEFAULT;
    }

    /**
     * The route of a step of the screens, in the space of the request.
     */
    public function route(Request $request, string $key): string
    {
        return $this->spaces[$this->name($request)]['routes'][$key]
            ?? $this->spaces[self::DEFAULT]['routes'][$key]
            ?? self::ROUTES[$key]
            ?? throw new \LogicException(\sprintf('No route "%s" in the sign-in screens\' spaces.', $key));
    }

    /**
     * A step a space may lack: none declared, or declared empty, is none. `profile_show`, the account's page the profile
     * returns to after saving, is one — a customer area has no page of the account to show.
     */
    public function optionalRoute(Request $request, string $key): ?string
    {
        $space = $this->spaces[$this->name($request)]['routes'] ?? [];
        $route = \array_key_exists($key, $space) ? $space[$key] : ($this->spaces[self::DEFAULT]['routes'][$key] ?? null);

        return null !== $route && '' !== $route ? $route : null;
    }

    public function template(Request $request, string $key): string
    {
        return $this->spaces[$this->name($request)]['templates'][$key]
            ?? $this->spaces[self::DEFAULT]['templates'][$key]
            ?? self::TEMPLATES[$key]
            ?? throw new \LogicException(\sprintf('No template "%s" in the sign-in screens\' spaces.', $key));
    }

    /**
     * What every template of the space receives besides its own variables — `space` always.
     *
     * @return array<string, mixed>
     */
    public function context(Request $request): array
    {
        $name = $this->name($request);

        return ['space' => $name] + ($this->spaces[$name]['context'] ?? []);
    }

    /**
     * Where a visitor already signed in is sent instead of seeing the sign-in or sign-up card — none by default.
     */
    public function redirectAuthenticatedTo(Request $request): ?string
    {
        $name = $this->name($request);
        if (\array_key_exists($name, $this->spaces) && \array_key_exists('redirect_authenticated_to', $this->spaces[$name])) {
            return $this->spaces[$name]['redirect_authenticated_to'];
        }

        return $this->spaces[self::DEFAULT]['redirect_authenticated_to'] ?? null;
    }
}
