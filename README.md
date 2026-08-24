<p align="center">
    <a href="https://devinthehood.com"><img src="https://github.com/jul6art/symfony-skeleton-generator/blob/master/public/img/logo.png?raw=true" alt="logo dev in the hood" width="400"></a>
</p>

<p align="center">
    <a href="https://opensource.org/licenses/MIT" target="_blank"><img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License"></a>
    <img src="https://img.shields.io/static/v1?label=stable&message=v1&color=orange" alt="Version">
</p>

Symfony admin backoffice bundle
===============================

The shell of a back office: a sidebar layout, a theme, per-user appearance, the sign-in pages, and
a navigation contract the application fills.

Everything that makes a back office look like a back office, and nothing that makes it yours.

Requirements
------------

- PHP ^8.5
- Symfony ^7.4 || ^8.0
- `symfony/security-bundle` — a hard requirement, not a suggestion: the navigation builder needs
  `security.authorization_checker` to **compile**, and a package the container needs to compile is
  not a development dependency.

Suggested, and what each unlocks:

| Package | Without it |
| --- | --- |
| `twig/twig` | no shell, no auth pages, no appearance screen — only the navigation contract, the enums and the entity trait |
| `symfony/form` | no `AppearanceType` |
| `doctrine/orm` | no appearance controller (its compiler pass removes it when no entity manager is registered) |
| `symfony/asset` | logo and favicon paths render as-is instead of going through `asset()` |
| `jul6art/datatable-bundle` | no tables, and the `ui--modal` / `ui--tooltip` controllers the shell assumes are registered |

Installation
------------

```shell
composer require jul6art/admin-bundle
```

```php
// config/bundles.php — Flex does this for you
Jul6Art\AdminBundle\AdminBundle::class => ['all' => true],
```

Configuration
-------------

```yaml
# config/packages/admin.yaml
admin:
    enabled: true

    # ⚠️ The single most important key. See "The base template" below.
    base_template: 'base.html.twig'

    branding:
        name: 'Acme Admin'
        logo: 'img/logo.png'        # asset paths, passed through asset()
        favicon: 'img/favicon.ico'
        home_route: admin_dashboard
        # Logo width in pixels on the authentication pages and in e-mails.
        # `~` — or leaving the key out — keeps the historical fixed height (h-12) and lets the
        # width follow. Both spellings work: a node whose default is null accepts an explicit null.
        logo_width: ~
        # `false` drops the name written under the logo (auth pages) and beside it (sidebar):
        # the wordmark case, where the logo already says the name. The logo then takes the room.
        show_name: true

### Performance dashboard (optional)

When `jul6art/core-bundle` is installed, the bundle also ships the screen that renders its
per-request profiler: slowest routes, N+1 suspects, CSV/JSON export, and a button to clear the
store. Without the core bundle the controller is **removed from the container** — the shell alone
does not require the profiler.

```yaml
# config/routes/admin.yaml — the application decides the URL and the firewall around it
admin_performance:
    resource: '@AdminBundle/Controller/PerformanceController.php'
    type: attribute
    prefix: /admin

# config/packages/admin.yaml
when@dev:
    admin:
        routes:
            performance: admin_performance_dashboard   # empty elsewhere ⇒ no link in the menu
```

⚠️ The route **names** must keep the `admin_performance_` prefix: that is what
`core.performance.ignored_route_prefix` excludes from collection. Rename them and the dashboard
starts measuring its own page, adding a record on every visit to what it displays.

⚠️ Pages shipped by this bundle extend `admin.layout_template` (default `@Admin/layout.html.twig`).
An application whose own pages go through its own layout — the one exposing `window.jwtToken`, an
extra top bar — points that key at it, and makes that layout extend `@Admin/layout.html.twig`.


    # An empty route name HIDES its link rather than breaking the render — which is what makes
    # the multi-area case below work.
    routes:
        login: admin_security_login
        logout: admin_security_logout
        register: admin_security_register      # empty closes public sign-up
        reset_password_request: admin_reset_password_request
        profile: ''
        change_password: admin_account_password_edit
        appearance: admin_account_appearance_edit
        privacy: ''

    mercure:
        hub_url: '%env(MERCURE_PUBLIC_URL)%'
        token_route: admin_mercure_token
```

> ⚠️ **`routes` is a GLOBAL table — one route per entry, for the whole application.**
>
> An application with several areas, where the same screen exists twice — `/admin/account/appearance`
> and `/organization/account/appearance` — cannot name both here. Leave that entry **empty** and let
> each layout add its own link:
>
> ```twig
> {% block admin_account_menu_extra %}
>     {{ include('@Admin/partials/_menu_link.html.twig', {
>         route: 'app_organization_account_appearance_edit',
>         icon: 'fa-solid fa-palette',
>         label: 'nav.appearance'|trans,
>     }) }}
> {% endblock %}
> ```
>
> A single value sends everyone to the same place, and the audience of the other area to a 403 —
> the page works, the *link* is wrong, and nothing in a controller test looks at links.

### The base template

`@Admin/layout.html.twig` extends whatever `base_template` names, through Twig's dynamic
inheritance. The default is the bundle's own base, which loads **no assets at all** — a bundle
cannot choose between `encore_entry_link_tags()` and `importmap()` for its consumer.

So an application points `base_template` at its own base, and makes that one extend
`@Admin/base.html.twig`:

```twig
{# templates/base.html.twig #}
{% extends '@Admin/base.html.twig' %}

{% block stylesheets %}{{ encore_entry_link_tags('app') }}{% endblock %}
{% block javascripts %}{{ encore_entry_script_tags('app') }}{% endblock %}
```

> ⚠️ **Forget the key and an admin page bypasses your base entirely** — it renders with no
> stylesheet, and nothing points at the cause. This was found by adopting the bundle in a real
> application, not by writing it.

Usage
-----

### The shell, and its blocks

```twig
{% extends '@Admin/layout.html.twig' %}

{% block content %}…{% endblock %}
```

| Block | What goes in |
| --- | --- |
| `admin_sidebar_brand` | the sidebar header, logo included |
| `admin_sidebar_nav` | the menu — by default, the providers' |
| `admin_sidebar_footer` | the account block at the bottom |
| `admin_topbar_left` | left of the top bar, after the mobile toggle |
| `admin_topbar_center` | centre — an impersonation banner, a search field |
| `admin_topbar_actions` | right, BEFORE the account menu — a bell, a locale switcher |
| `admin_account_menu_extra` | extra entries in the account menu |
| `admin_body_end` | after `<main>` — a global JS variable, a floating widget |
| `container_class` | the content's max width |
| `content` | the page |

### The menu

One provider per module, so a module removed takes its menu with it:

```php
final class UserNavigation implements NavigationProviderInterface
{
    public function sections(): iterable
    {
        yield new NavSection('access', 'nav.access', 'fa-solid fa-shield-halved', [
            new NavItem('admin_dashboard', 'nav.dashboard', 'fa-solid fa-house'),
            new NavItem('admin_user_index', 'nav.users', 'fa-solid fa-users', permission: 'user:read'),
            new NavItem('admin_report_index', 'nav.reports', 'fa-solid fa-chart-pie', feature: 'reporting'),
        ], priority: 100);
    }
}
```

Declaring the class as a service is enough — `AdminBundle` autoconfigures the tag.

**The gate belongs next to the link.** A menu entry whose guard drifts from its route's guard
produces a visible link that answers 403 — an interface bug no controller test sees, because the
controller is right.

- `permission` goes to `isGranted()`, so it accepts a permission code, a role, anything a voter
  answers. ⚠️ A code no voter recognises is **granted**, not refused: Symfony's default strategy
  returns true when every voter abstains. Cover the menu with a test that walks it.
- `feature` goes to a {@see FeatureVisibilityInterface} the application implements. ⚠️ **With no
  checker registered, a feature-gated item is hidden.** Deliberately: the other direction turns
  every paid module into a free one, and the suite stays green.
- A section whose items all disappear disappears too — a group header opening onto nothing
  advertises a module the account cannot reach.

An application that already has its menu in Twig overrides `admin_sidebar_nav` and keeps it. Both
paths are supported; the contract is for projects starting from scratch.

### Appearance

Four things on the `User`, and one line in the layout does the rest:

```php
#[ORM\Entity]
class User implements AppearanceAwareInterface, AdminUserInterface
{
    use AppearancePreferencesTrait;   // the five appearance_* columns

    #[ORM\Column(length: 10, options: ['default' => 'light'])]
    private string $theme = 'light';

    public function getColorMode(): ColorMode { return ColorMode::fromStorage($this->theme); }
    public function setColorMode(ColorMode $m): static { $this->theme = $m->value; return $this; }

    public function getDisplayName(): string { return $this->firstName.' '.$this->lastName; }
    public function getInitials(): string { /* … */ }
    public function getAvatarPath(): ?string { return $this->avatarPath; }
}
```

**A trait and not an embeddable**: an embeddable shipped by a bundle needs a Doctrine mapping entry
for the bundle's namespace, and in this ecosystem those are switched off — one of them would map a
vendor `User` and create a second `user` table. A trait needs nothing, and the columns are named
`appearance_*` explicitly, which is what a `columnPrefix: false` embeddable produced: an
application migrating from one to the other has **no schema change**.

**The colour mode stays out of the trait** on purpose: almost every application already has a
column for it under its own name. Those two methods are the wiring, not a redundancy.

Then import the screen where it belongs in your URL map:

```yaml
# config/routes/admin.yaml
admin_account_appearance:
    resource: '@AdminBundle/Controller/AppearanceController.php'
    type: attribute
    prefix: /admin
```

### The theme

```css
/* assets/styles/app.css */
@tailwind base;
@tailwind components;
@tailwind utilities;

@import '@jul6art/admin-bundle/styles/tokens.css';      /* accents, density, contrast, motion */
@import '@jul6art/admin-bundle/styles/components.css';  /* .panel, .btn-*, .badge-*, .form-* */
```

```js
// tailwind.config.js
module.exports = {
    presets: [require('./vendor/jul6art/admin-bundle/assets/tailwind/preset.js')],
    content: ['./templates/**/*.html.twig', './assets/**/*.js', /* + the bundle's assets */],
};
```

> ⚠️ **The bundle's `assets/` must be in Tailwind's `content`.** A class used only in the bundle's
> templates is otherwise purged from the production stylesheet — and only from that one.

Eleven Stimulus controllers ship with it (`appearance`, `theme`, `dropdown`, `collapsible`, `tabs`,
`sidebar-section`, `toast`, `locale-switcher`, `cookie-consent`, `sidebar`, `password`). Register
them under `ui--<name>`, which is what the templates address.

### The sign-in pages

`@Admin/security/{login,register,reset_password_request,reset_password,check_email}.html.twig`.
They carry the branding and honour `admin.routes` — closing public sign-up is emptying one key, not
overriding a template. The register and reset templates expect a `form` from the application: the
bundle does not decide what an account is made of, it draws the screen.

> ⚠️ The password-reset flow must never reveal whether an address exists. Both cases lead to the
> same confirmation page with the same text — a different message turns the form into an
> account-enumeration oracle. The shipped templates already read that way; keep them that way.

Quality assurance
-----------------

```shell
composer qa            # cs-check + rector-check + phpstan (level max) + phpunit
```

Run `composer qa`, not the single tool you have in mind: the CI's "Coding standards" job runs
Rector too, and its `lowest deps` job installs the minimum of every constraint — which is where
this ecosystem has repeatedly found what a local run could not.

License
-------

The Admin bundle is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

&copy; 2026 [jul6art](https://devinthehood.com)
