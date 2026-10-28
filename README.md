<p align="center">
    <a href="https://devinthehood.com"><img src="https://github.com/jul6art/symfony-skeleton-generator/blob/master/public/img/logo.png?raw=true" alt="logo dev in the hood" width="400"></a>
</p>

Symfony admin backoffice bundle
===============================

<p align="left">
    <a href="https://opensource.org/licenses/MIT" target="_blank"><img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License"></a>
    <img src="https://img.shields.io/static/v1?label=stable&message=v1&color=0ea5e9" alt="Version">
</p>

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

### Content-Security-Policy (with `jul6art/core-bundle` ≥ 3.6)

The shell has one inline script — the anti-FOUC in the head, which must run before the first paint
— and the performance dashboard another. `script-src 'self'` refuses both. With core-bundle ≥ 3.6
they carry the page's nonce (`admin_csp_nonce()`, which reads the core's `csp_nonce()`), and the
core's security header listener adds it to the policy. Without the core there is no policy to
satisfy, and the scripts are written without the attribute.

No template of the bundle carries an inline event handler (`onclick`, `onsubmit`): it has no nonce,
the policy refuses it. A project's own inline script asks for the nonce the same way:

```twig
<script nonce="{{ csp_nonce() }}">window.jwtToken = {{ jwt_token()|json_encode|raw }};</script>
```

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

    # The product's user guide, linked from the account menu and from a help icon in the top
    # bar — both open a new tab. A route OR an url, never both; empty (the default) shows nothing.
    user_guide:
        route: public_guide_index     # or: url: '/docs'
```

The guide's label is `nav.user_guide`, read from the application's `messages` catalogue like
`nav.logout`: every consumer that fills `user_guide` defines that key in each of its languages.

The menu button of the top bar on a phone — an icon alone — is named by `nav.sidebar_toggle` (1.34.1),
in the same `messages` catalogue: every consumer defines it in each of its languages (« Ouvrir ou
fermer le menu », « Open or close the menu »).

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

#### `fixed-html` belongs to the layout (1.23.1)

`@Admin/layout.html.twig` puts `class="fixed-html"` on `<html>`, through the `html_class` block;
the base does not. Projects translate the class into `max-height: 100vh; overflow-y: hidden`,
which is right for the back-office shell — its columns scroll on their own — and wrong anywhere
else.

> ⚠️ **Until 1.23.0 the base set it on every page.** A page extending the base outside the layout —
> a customer portal, a public checkout page — grew past the window and the wheel did nothing.
> Projects worked around it page by page by rewriting `html_attr`; the page someone forgot stayed
> stuck. Those overrides can go: the base now writes the dark class and the appearance attributes
> on its own, and `class` only when it carries one.

A page of your own that scrolls inside its own columns opts in the way the layout does:

```twig
{% block html_class %}fixed-html{% endblock %}
```

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
| `admin_topbar_left` | left of the top bar, after the mobile toggle — an impersonation banner |
| `admin_topbar_center` | centre — the global search field (see below); the shell's container centres it |
| `admin_topbar_locale` | right, FIRST — the language picker |
| `admin_topbar_actions` | right, after the locale and BEFORE the account menu — a notification bell |
| `admin_account_menu_extra` | extra entries in the account menu |
| `admin_body_end` | after `<main>` — a global JS variable, a floating widget |
| `container_class` | the content's max width |
| `content` | the page |

#### The top bar is one norm, written by the shell (1.15)

Every back-office on this theme shows the same bar, because the shell writes its layout and a
project only fills slots:

- **the search is centred** — `admin_topbar_center` sits in the shell's own container (`flex-1`,
  centred from `md` up, allowed to shrink). Put the field in it, not a `w-full` wrapper: a
  wrapper that claims the whole width pushes the field to the left and crushes the account button;
- **the language picker is left of the bell** — `admin_topbar_locale`, then `admin_topbar_actions`,
  then the account menu. Before 1.15 a single slot held both, and two products ordered them
  oppositely;
- **the account name stays on one line** — up to `14rem`, cut beyond it (never wrapped), the full
  name in its `title`; the button does not shrink.

Projects that put the language picker in `admin_topbar_actions` still render — move it to
`admin_topbar_locale` to follow the norm.

#### The page title follows the page (1.29)

Down a long form, the name of the record being edited used to scroll away with the first fields.
Once the page's `<h1>` has left the screen, the shell now copies its text into the top bar, after
the left slot; it leaves as soon as the `<h1>` comes back. Nothing to write in the pages — a short
page never loses its `<h1>`, so nothing ever shows there. Register the controller as
`ui--page-title`:

```js
// assets/controllers/ui/page-title_controller.js
export { default } from '@jul6art/admin-bundle/controllers/page-title_controller';
```

- **It copies the first `<h1>` of `<main>`.** A page whose `<h1>` does not carry the right name
  marks another element: `data-ui--page-title-target="source"`.
- **It never moves the bar.** The copy is laid OVER the free space, up to the first thing of the
  centre slot (the search field, its magnifier on a phone) or to its end when it is empty — cut
  with an ellipsis, in full in its `title`, hidden when it neither fits whole nor has 6rem. In the flow, it
  shrank the centre slot and slid the search field sideways each time it came and went.
- **For the eyes only**: `aria-hidden`, the `<h1>` is already read. The fade is dropped under
  reduced motion.

Without the registration, the shell renders as before: the empty label stays invisible.

#### The global search field

```twig
{% block admin_topbar_center %}
    {{ include('@Admin/partials/_global_search.html.twig', {
        url: path('app_search_query'),        # answers ?q=<term> — see below
        labels: { customer: 'search.group.customer'|trans({}, 'search') },
        empty: 'search.empty'|trans({}, 'search'),
        more: 'search.more'|trans({ '%total%': '%total%' }, 'search'),
        placeholder: 'search.placeholder'|trans({}, 'search'),
        label: 'search.label'|trans({}, 'search'),
        close: 'search.close'|trans({}, 'search'),
    }) }}
{% endblock %}
```

Register the controller as `search--global`:

```js
// assets/controllers/search/global_controller.js
export { default } from '@jul6art/admin-bundle/controllers/global-search_controller';
```

**The engine is the bundle's** (since 1.19, with Doctrine ORM): `Jul6Art\AdminBundle\Search\GlobalSearch`
searches from two characters (on the server), with the comparison of `api-bundle`'s `OrSearchFilter`
(`LOWER(field) LIKE LOWER('%term%')`, so a panel and its list count alike — on **text** columns only:
it casts no number, and `LOWER()` on an integer is a type error on PostgreSQL), five rows per family
ordered case-insensitively, a `COUNT` only when a family is saturated, label and URL only — and keeps
nothing. **What is searched, and for whom, is the project's**: implement `GlobalSearchSourceInterface`
and alias it.

```php
final readonly class SearchSource implements GlobalSearchSourceInterface
{
    public function families(): iterable              // only what the CURRENT actor may open
    {
        if ($this->security->isGranted('customer:read')) {
            yield new SearchFamily('customer', Customer::class, ['name', 'vatNumber'], 'app_customer_show',
                listRoute: 'app_customer_index');   // "see all" → ?search=<term>, if the list reads it
        }
    }

    public function scope(SearchFamily $family, QueryBuilder $builder): void
    {
        $builder->andWhere('e.organization = :tenant')->setParameter('tenant', $this->tenant());
    }
}
```

> ⚠️ **Write the tenant in `scope()`**, even behind a Doctrine tenant filter: such filters are often
> off for a platform account, and a search that relied on one reads every tenant for exactly the
> account nobody tests.

The route stays the project's too — its path and its access decision — and is three lines:

```php
#[Route('/app/search', name: 'app_search_query', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
public function query(Request $request, GlobalSearch $search): JsonResponse
{
    return $search->respond((string) $request->query->get('q', ''));
}
```

`respond()` answers a JSON object keyed by family — `{ total, url, results: [{label, url}] }`, the
`SearchGroup` / `SearchResult` value objects — or `[]`. Without a source the engine searches nothing.
All strings reach the browser already rendered: the Stimulus controller translates nothing.

The controller debounces (250 ms), searches from two characters (enforce it on the server too),
aborts the request in flight, escapes every label, and opens on `/` unless a field has the focus —
pass `shortcut: false` to the partial (since 1.17) where the product's keyboard policy refuses it.
**Below `md`** the field would be crushed to a few pixels next to the icons: a magnifier replaces
it and opens a full-width row under the header, closed by Escape, the × or a tap outside.

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

**A count beside an entry** (1.25) — unread messages, pending requests:

```php
new NavItem('admin_message_index', 'nav.messages', 'fa-solid fa-inbox',
    permission: 'contact_message:list',
    badge: $this->messages->countUnread(),         // null or 0: no pill
    badgeLabelKey: 'nav.messages_unread',          // default 'nav.badge', receives %count%
),
```

The pill shows the number alone; a screen reader hears the sentence `badgeLabelKey` translates (in
the item's `labelDomain`) — "3", read after "Messages", does not say three what.

> ⚠️ **The provider computes the count each time the menu is built — on every page of the back
> office.** Keep it to one cheap query (a `COUNT` on an indexed column); a live count belongs to
> the screen that shows the messages.

**An entry that opens a new tab** (1.27) — the public site of a back office, a guide: a page read
beside the form one is filling, not instead of it:

```php
new NavItem('site_home', 'nav.site', 'fa-solid fa-globe',
    newTab: true,                                  // target="_blank" rel="noopener noreferrer"
    newTabLabelKey: 'nav.new_tab',                 // the default; what a screen reader hears
),
```

An arrow follows the label (`.admin-nav-new-tab`), and the label is followed, for a screen
reader, by the sentence `newTabLabelKey` translates in the item's `labelDomain` — the project adds
that key to its catalogue.

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

Twelve Stimulus controllers ship with it (`appearance`, `theme`, `dropdown`, `collapsible`, `tabs`,
`sidebar-section`, `toast`, `locale-switcher`, `cookie-consent`, `sidebar`, `password`,
`page-title`). Register
them under `ui--<name>`, which is what the templates address. Four more come with the keyboard
bricks below, under `core--` and `form--`.

#### The decimal input (`form--decimal`, 1.21)

The ui-bundle's decimal types (`CustomMoneyType`, `CustomUnitType`) attach `form--decimal` to their
input **and ship no controller**. This one formats the value the French way ("3 600,00") at rest,
gives back the plain value ("3600.00") on focus and on submit, and switches a `type="number"` input
(`html5: true`) to text — a number input cannot hold a comma and the browser would empty it.

```js
// assets/controllers/form/decimal_controller.js — the path gives the identifier `form--decimal`
export { default } from '@jul6art/admin-bundle/controllers/decimal_controller';
```

> ⚠️ **Without this file, nothing fails**: the identifier resolves to nothing, and every price and
> quantity field shows "89.00". `DecimalController.parse()` is static and public, for controllers
> that read a possibly-formatted value (line totals).

#### A short collection (`form--collection`, ordering 1.26)

Adds, removes and — since 1.26 — reorders the rows of a `CollectionType` in place: the links of a
footer, the steps of a checklist. Declare the collection `allow_add`, `allow_delete` and
`by_reference: false`, and render the prototype and the rows with the same template.

```twig
{% macro link_row(row) %}
    <div data-form--collection-target="item">
        {{ form_row(row.label) }}
        {{ form_widget(row.position, {attr: {'data-form--collection-target': 'position'}}) }}
        <button type="button" data-action="form--collection#up">Up</button>
        <button type="button" data-action="form--collection#down">Down</button>
        <button type="button" data-action="form--collection#remove">Remove</button>
    </div>
{% endmacro %}

<div data-controller="form--collection" data-form--collection-prototype-value="{{ _self.link_row(form.links.vars.prototype)|e('html_attr') }}">
    <div data-form--collection-target="container">
        {% for row in form.links %}{{ _self.link_row(row) }}{% endfor %}
    </div>
    <button type="button" data-action="form--collection#add">Add</button>
</div>
```

```js
// assets/controllers/form/collection_controller.js
export { default } from '@jul6art/admin-bundle/controllers/collection_controller';
```

> ⚠️ **Moving a row does not reorder what Symfony receives**: a field keeps its name, and its name
> carries its index. The order travels in each row's hidden `position` field (an `IntegerType` or
> `HiddenType` of the entry), which the controller renumbers after every move, addition and
> removal. Sort by it when you save; a row without one moves on screen only.

Two buttons rather than a drag handle: a keyboard and a screen reader reach them like any other
button, and the focus stays on the button just pressed, which moved with its row.

### Keyboard shortcuts

Fast data entry for whoever types a hundred records in a row: open a creation screen, land on the
right field, save, start the next one — without reaching for the mouse.

```twig
{# your base template #}
{% block body_attr %} data-controller="core--keyboard"{% endblock %}
{% block head %}{{ include('@Admin/partials/_keyboard_meta.html.twig') }}{% endblock %}
```

```twig
{# an index screen: the shortcut goes on the button that already passed the permission check #}
<a href="{{ path('app_customer_new') }}" class="btn-primary"
   data-shortcut="{{ keyboard_shortcut('global.new') }}"
   data-shortcut-label="{{ 'customer.list.create'|trans }}">

{# an entry form #}
{{ form_start(form, { attr: { 'data-controller': 'form--autofocus form--submit-shortcut' } }) }}
```

| Key | Effect |
|---|---|
| `n` | clicks the visible element whose `data-shortcut` is the combo in force for `global.new` (`n` by default) — typically the "New X" button. Render it with `keyboard_shortcut('global.new')`, never a literal `"n"`: an organisation's override would otherwise never reach the button |
| `?` | opens the cheat-sheet, listing what the current page offers |
| `Ctrl` + `B` | back to the list: clicks the Cancel link of `_form_actions` (`form.back`, overridable) |
| `Esc` | closes the cheat-sheet — and nothing else since 1.20 |
| `Ctrl`/`⌘` + `Enter` | submits the form |
| `Ctrl`/`⌘` + `Shift` + `Enter` | submits it through the `data-secondary` button ("save and add another") |

Four Stimulus controllers ship with it. Register them under the identifiers the templates address:
`core--keyboard`, `form--autofocus`, `form--submit-shortcut`, `form--shortcut-capture`.

> ⚠️ **`data-shortcut-label` is what puts an entry in the cheat-sheet.** A combo without one still
> fires, and `?` does not mention it — a shortcut nobody can discover.

> ⚠️ **A button your template withholds is a shortcut that does not exist.** The router clicks a
> node; it knows nothing about roles. Which is exactly why this is an attribute rather than a
> registry — the `is_granted()` that decides whether to render the button has already run.

> ⚠️ **`Esc` no longer goes back** (1.20). It only did after arriving through a shortcut, could not be
> overridden, and shares its key with every Select2, modal and picker: one press too many left a
> half-filled form. `Ctrl+B` replaces it. A form that writes its own Cancel link gives it
> `data-shortcut="{{ keyboard_shortcut('form.back') }}"`; `cancel_shortcut: false` takes it off the
> partial's.

Four rules keep a shortcut from firing where it would do harm (1.20.1):

- a keystroke a control already handled (`preventDefault()`) is not routed — `Ctrl+B` in a Trix
  editor makes text bold, it does not leave the page;
- from a rich editor (`contenteditable`), a shortcut whose target is a link does nothing;
- an open modal (`aria-modal`, `<dialog open>`, datatable-bundle's `[data-backdrop]`) or the
  cheat-sheet confines the shortcuts to itself;
- on macOS, `Ctrl` + a letter in a text field keeps its system meaning (`Ctrl+B` moves the caret
  back) — outside a field, and with Shift or Alt, shortcuts fire as anywhere.

The cheat-sheet lists a combo the page offers under the page's own label, and leaves out the
generic global row for it.

Add your own actions by configuration; the four above belong to the shell, everything else belongs
to a product:

```yaml
admin:
    keyboard:
        actions:
            erp.lines.add: { default: 'l', label: 'keyboard.action.erp_lines_add' }
```

The cancel link of `_form_actions` answers `form.back` by default. A product may hand it another of
its actions instead, or none:

```twig
{{ include('@Admin/partials/_form_actions.html.twig', { …,
    cancel_shortcut: 'app.leave',                        # declared under admin.keyboard.actions
    cancel_shortcut_label: 'keyboard.action.app_leave'|trans({}, 'keyboard'),
    # cancel_shortcut: false,                            # no shortcut on this link
}) }}
```

The link carries the combo in force, so an override reaches it.

Letting people change their own combos is optional. Implement `KeyboardShortcutStoreInterface` and
alias it; the resolver picks it up, and a settings screen can read `keyboard_actions()` and render
each field with `form--shortcut-capture`.

> ⚠️ **Not implementing it is a supported state**, and it degrades toward *the factory shortcuts
> work* — never toward *no shortcuts*. This is deliberately the opposite of the datatable bundle's
> preference port, whose absence removes the feature silently: a back-office that stopped answering
> `Ctrl+Enter` because nobody wired a database would be indistinguishable from a broken one.

The cheat-sheet and the capture widget read their labels from the browser catalogue, so hand
`Translation\DeclaredTranslationKeys` to your JavaScript translation guard — otherwise it reports
every one of them as dead and the next tidy-up deletes them.

> ⚠️ **The action labels are NOT among them.** `KeyboardAction::$labelKey` is read by a settings
> screen, server-side, in that screen's own domain — putting it in the browser catalogue would
> bless a dead entry as alive.

### The inbox (1.25)

A mail-client screen for messages the application receives — contact requests, support tickets,
enquiries: the folders on top, the list on the left, the open message on the right; on a phone, one
pane at a time, with a way back. The bundle knows no entity: a message is anything implementing
`Contract\InboxMessageInterface` (id, sender name and e-mail, a plain-text excerpt, the date
received, read or not, and an optional status label).

The page is **rendered by the server**: one URL per folder, one per open message, links everywhere.
It works without JavaScript; the controller only helps.

```twig
{# templates/message/index.html.twig — the list, and the open message when there is one #}
{% extends '@Admin/layout.html.twig' %}

{% block content %}
    {% embed '@Admin/inbox/_layout.html.twig' with { inbox: {
        folders: [
            { label: 'message.folder.inbox'|trans({}, 'contact'), url: path('admin_message_index'), count: unread, active: folder == 'inbox' },
            { label: 'message.folder.archived'|trans({}, 'contact'), url: path('admin_message_index', { folder: 'archived' }), count: 0, active: folder == 'archived' },
        ],
        messages: messages,                    # InboxMessageInterface[], in list order
        current: message ?? null,              # the open one, or null
        message_route: 'admin_message_show',   # takes `id`
        message_route_params: { folder: folder },
        translation_domain: 'contact',         # where getInboxStatusLabel() keys live
    } } %}
        {% block inbox_actions %}
            {% if is_granted('contact_message:reply', message) %}
                <a href="#reply-body" class="btn-primary" data-shortcut="{{ keyboard_shortcut('inbox.reply') }}"
                   data-shortcut-label="{{ 'message.reply'|trans({}, 'contact') }}">…</a>
            {% endif %}
        {% endblock %}
        {% block inbox_reader %}
            <div class="whitespace-pre-line">{{ message.body }}</div>
            {# the thread of replies, the reply form (textarea id="reply-body")… #}
        {% endblock %}
    {% endembed %}
{% endblock %}
```

| Block | What goes in it |
|---|---|
| `inbox_actions` | the open message's buttons (reply, archive, delete…), in its header |
| `inbox_reader` | its content — the text, the replies, the form. The bundle renders the sender, the e-mail and the date above it |
| `inbox_attr` | more attributes on the root — a second controller, typically |

Register the controller as `ui--inbox`:

```js
// assets/controllers/ui/inbox_controller.js
export { default } from '@jul6art/admin-bundle/controllers/inbox_controller';
```

It turns every date into "3 hours ago" in the page's language (`Intl.RelativeTimeFormat`, the
absolute date moving to `title`), keeps the open message in sight in a long list, and focuses a
field a link points to (`<a href="#reply-body">`). Its `refresh()` action reloads the list and the
folders in place, from the current URL.

> ⚠️ **The bundle owns no real-time feed.** When a message arrives, the application's own channel
> (Mercure, polling…) calls `ui--inbox#refresh` — through `inbox_attr`, or a small controller of its
> own. Without one, the list is as fresh as the last page load.

**A long folder, slice by slice (1.28)** — a folder is never rendered whole. The application
renders a slice and passes `more_url`, the address of the next, older one; the template turns it
into a link under the list, which works without JavaScript. With it, `ui--inbox` follows the link
as soon as it scrolls into view, APPENDS the slice and replaces the link by the slice's own — an
infinite scroll whose link stays the keyboard's way (pressed, it moves the focus to the first
message added). A screen reader hears `inbox.loaded` (`%count%`); the link reads `inbox.more`.

```twig
{% embed '@Admin/inbox/_layout.html.twig' with { inbox: {
    …,
    more_url: more ? path('admin_message_index', {before: last.id}) : null,
} } %}
```

> ⚠️ **The cursor is the application's, and it should not be an offset.** Paginate on the
> ordering key — `(receivedAt, id)` before the last message shown: a message that arrives between
> two slices then shifts nothing, where an `OFFSET` repeats one message and skips another.

**The panes fit the screen (1.28.1)**: the list and the open message scroll each in their own pane,
never the page — `ui--inbox` measures what stands above and below them (`--admin-inbox-top`,
16rem without JavaScript).

> ⚠️ **`refresh()` keeps the loaded slices.** The fresh first page replaces the HEAD of the list,
> down to its own oldest message; what was loaded below stays. A message opened far down should be
> rendered with the list down to it (and its neighbours), or it is no longer in the list it was
> opened from.

**Keyboard: `inbox.previous`, `inbox.next`, `inbox.reply`, `inbox.archive` are the application's
to declare**, not the bundle's — a consumer without an inbox has no business seeing them in its
cheat-sheet:

```yaml
admin:
    keyboard:
        actions:
            inbox.previous: { default: 'k', label: 'keyboard.action.inbox_previous' }
            inbox.next:     { default: 'j', label: 'keyboard.action.inbox_next' }
            inbox.reply:    { default: 'r', label: 'keyboard.action.inbox_reply' }
            inbox.archive:  { default: 'e', label: 'keyboard.action.inbox_archive' }
```

The previous/next links of the reader take their combo from the first two as soon as they are
declared; undeclared, they stay plain links. The last two are yours to put on your own buttons in
`inbox_actions`, as above — a button a permission withholds is a shortcut that does not exist.

The template's own words are in the application's **`inbox`** domain (the bundle ships no
translations): `inbox.folders`, `inbox.list`, `inbox.empty`, `inbox.select`, `inbox.unread`,
`inbox.back`, `inbox.previous`, `inbox.next`. All of them are rendered server-side — none belongs
in the browser catalogue.

> ⚠️ **The excerpt is plain text, and escaped.** `getInboxExcerpt()` is what the list shows under the
> sender; strip the markup before returning it, or the list shows the tags.

### The record page (1.35)

A "show" page reads in three beats: **who** (a header — avatar, name, badges, one line of meta,
the actions), **where it stands** (figures, steps, tabs), then **the detail** — the content, and a
column of properties on the right from 1280 px. The pattern comes from the Metronic concepts
(CRM · Company, Store · Customer details), redrawn in this theme.

```twig
{% extends 'admin/layout.html.twig' %}
{% from '@Admin/partials/_actions_dropdown.html.twig' import dropdown %}

{% block content %}
    {% embed '@Admin/record/_page.html.twig' with { aside_first: true } %}
        {% import '@Admin/record/_macros.html.twig' as record %}

        {% block record_breadcrumb %}<nav class="text-xs …">…</nav>{% endblock %}
        {% block record_avatar %}{{ record.avatar(company.name) }}{% endblock %}
        {% block record_title %}{{ company.name }}{% endblock %}
        {% block record_badges %}<span class="badge-active">{{ 'status.active'|trans }}</span>{% endblock %}
        {% block record_meta %}<span>{{ company.industry }}</span><a href="…">{{ company.owner }}</a>{% endblock %}
        {% block record_actions %}
            <a href="{{ path('deal_new', {company: company.id}) }}" class="btn-primary">…</a>
            {{ dropdown('action.actions'|trans({}, 'messages'), menu) }}
        {% endblock %}
        {% block record_stats %}
            <div class="admin-record__stats">
                {{ record.stat('Billed in 2026', record.figure(billed|format_currency('EUR')), {trend: {label: '+12.4 %', direction: 'up'}}) }}
            </div>
        {% endblock %}
        {% block record_tabs %}{{ record.tabs(tabs, tab) }}{% endblock %}
        {% block record_main %}<div class="panel">…</div>{% endblock %}
        {% block record_aside %}
            <div class="panel">{{ record.properties([
                { icon: 'fa-globe', label: 'Website', value: include('_partials/_external_link.html.twig', {url: company.website}) },
                { icon: 'fa-phone', label: 'Phone', value: company.phone },
            ]) }}</div>
        {% endblock %}
        {% block record_after %}{# the audit, a modal #}{% endblock %}
    {% endembed %}
{% endblock %}
```

| Block | What goes in |
| --- | --- |
| `record_breadcrumb` | the trail above the header |
| `record_avatar` | `record.avatar(name)` — initials, or `{icon: 'fa-wrench'}`, `{round: true}` for a person |
| `record_title` | the page's **only `<h1>`** — `ui--page-title` copies it into the top bar |
| `record_badges` | state badges, next to the title |
| `record_meta` | one line of `<span>`/`<a>`, separated by dots by the stylesheet |
| `record_actions` | one primary action, two secondary at most, the rest in the ⋯ menu |
| `record_alerts` | a read-only or deleted banner |
| `record_stats` | `<div class="admin-record__stats">` and `record.stat()` cards |
| `record_steps` | `record.steps(steps)` — a status that moves forward |
| `record_tabs` | `record.tabs(items, current)` — links to `?tab=…` |
| `record_main` / `record_aside` | the content, and the column of properties |
| `record_after` | below everything — the audit, a modal |

Options of the embed: `aside_first` (default `true` — under 1280 px the column comes **before** the
content: identity first; a record whose content is the work to do sets it to `false`) and
`title_mono` (a number as a name: `WO-2026-00412`).

**Every empty block disappears, tag included**: the meta, the actions and the column are captured
before they are written, so a record without a column takes the full width and an empty meta leaves
no empty paragraph under the title.

The macros (`@Admin/record/_macros.html.twig`): `avatar`, `property`, `properties` (folds the rows
beyond `visible`, 6 by default, behind a button — only while the column is stacked; from 1280 px
everything shows), `stat` (`hint`, `trend {label, direction}`, `tone: danger|success`,
`progress {value, max}`), `figure` (a formatted amount whose decimals step back), `steps`
(`{label, state: done|current|todo, hint, color, tone: warning|danger}` — a `color` that is not a
hexadecimal colour is dropped: it lands in a `style` attribute), `timeline`
(`{title, time, datetime, text, state}` — a `<time>` only with `datetime`, a `<span>` otherwise) and
`tabs` (`{key, label, href, count, icon, condition, attrs}`).

**Tabs are links, not buttons.** A tab is shared and works without JavaScript, and the controller
computes only the open one — the audit of a record is no longer loaded each time it is opened.

The template's own words are in the application's **`record`** domain (the bundle ships no
translations): `record.tabs` (the tabs' `aria-label`), `record.properties.show_all` (`%count%`),
`record.steps.caption` (`%position%`, `%total%`, `%label%` — « Étape 5 sur 7 · Sur site ») and
`record.cancel` (the ⋯ menu's confirmation). Every consumer defines them in each of its languages.

> ⚠️ **Written in this bundle's classes, never in utilities**: the applications of this ecosystem
> scan the bundle's templates (`bundleContentPaths()`), an application set up otherwise may not —
> and a utility only a template names is then purged from its production stylesheet.
> `RecordRenderingTest` reads every class the three templates write and fails on one the stylesheet
> does not declare.

**The preview of a list** (jul6art/datatable-bundle ≥ 2.13, `previewAction`): a plain click on a
row's link opens the record in a panel on the right, which shows the page's `[data-preview]` element
minus its `[data-preview-exclude]` regions. The record page marks both: the panel shows the header
and its actions, the alerts, the figures, the steps and the column; the breadcrumb, the tabs, the
content and `record_after` stay out. A panel of the content marked `data-preview-keep` stays in —
for a record whose details live in the content. Inside the panel the record takes one column,
whatever the width of the window.

#### The ⋯ menu (`@Admin/partials/_actions_dropdown.html.twig`)

N secondary actions in one menu instead of N buttons side by side — moved up from superp and
trading-bot, which each carried a copy. Each item: `label`, `icon`, `href` **or** `form_action`
with `csrf` and an optional `confirm {title, message, confirm, variant}` (the `ui--modal` of
datatable-bundle), `danger` — or `tone: success|accent|danger`, a transition coloured like its
button —, `attrs`, and **`condition` — where the action's `is_granted` goes**:
a link shown without the right answers 403. `{divider: true}` separates two groups; a divider left
first, last or doubled by hidden items is not rendered. `{compact: true}` renders the ⋮ alone, for
a table row. Without a visible item, nothing is rendered.

`ui--dropdown` and `ui--collapsible` keep `aria-expanded` on their buttons up to date (1.35).

The menu hangs from its button's right edge; when that would cross the left edge of what shows it — a
list's preview sheet, a phone, where the actions wrap under the title — it hangs from the left edge
instead (1.35.1).

### The sign-in pages

`@Admin/security/{login,register,reset_password_request,reset_password,check_email}.html.twig`.
They carry the branding and honour `admin.routes` — closing public sign-up is emptying one key, not
overriding a template. The register and reset templates expect a `form` from the application: the
bundle does not decide what an account is made of, it draws the screen.

They all extend `@Admin/security/_card.html.twig`. A project dresses the five at once by overriding
that ONE file and extending the original through `@!Admin` — never by copying the pages:

```twig
{# templates/bundles/AdminBundle/security/_card.html.twig #}
{% extends '@!Admin/security/_card.html.twig' %}

{% block auth_topbar %}{{ include('_partials/_locale_switcher.html.twig') }}{% endblock %}
{% block auth_aside %}{{ include('security/_aside.html.twig') }}{% endblock %}
```

| Block | What goes in |
| --- | --- |
| `auth_aside` (1.24) | beside the card: an illustrated half, a brand, a link back to the public site. Its elements are DIRECT children of `.auth-shell` — the project places them (absolute on a wide screen, in the flow on a phone, `order` for what comes after the card). Empty by default |
| `auth_topbar` | above the card, right-aligned — a language picker |
| `auth_title`, `auth_subtitle`, `auth_body`, `auth_footer` | the card itself, filled by each page |

> ⚠️ The password-reset flow must never reveal whether an address exists. Both cases lead to the
> same confirmation page with the same text — a different message turns the form into an
> account-enumeration oracle. The shipped templates already read that way; keep them that way.

### Two-factor authentication (1.32)

A six-digit code from an authenticator app (TOTP), asked after the password — scheb/2fa-bundle
underneath. Two decisions, and they are the application's:

```yaml
# config/packages/admin.yaml
admin:
    two_factor:
        enabled: true     # OFFER it: an account turns it on from its account menu
        required: false   # REQUIRE it: an account without it reaches its enrolment screen and nothing else
        exempt_routes: []  # sign-ins that skip the code — the dev login link, never a production route
        open_routes: []    # with `required`: what an account without it still reaches (a language switch…)
        enrolment_route: admin_account_two_factor   # with `required`: where such an account is sent
```

Off by default, and then nothing is registered. On, the bundle configures scheb for you — the TOTP
provider and its issuer (`branding.name`), the sign-in template, the tokens it applies to (form
login and custom authenticators, never remember-me nor a stateless API), the exempt routes, the
replay protection — and the limiter: five wrong codes per quarter of an hour and per account, then
even the right one is refused before it is read.

**What the application writes**, because only it knows where:

```shell
composer require scheb/2fa-bundle scheb/2fa-totp endroid/qr-code symfony/rate-limiter
```

```php
#[ORM\Entity]
class User implements UserInterface, TwoFactorAwareInterface
{
    use TwoFactorTrait;   // totp_secret, totp_enabled_at — then a migration
}
```

```yaml
# config/packages/security.yaml — the firewall that signs in with a form
security:
    firewalls:
        main:
            two_factor:
                auth_form_path: 2fa_login
                check_path: 2fa_login_check
                enable_csrf: true
    access_control:
        - { path: ^/2fa, roles: IS_AUTHENTICATED_2FA_IN_PROGRESS }   # first in the list

# config/routes/admin.yaml — scheb's two routes, and the account screen
2fa_login:
    path: /2fa
    defaults: { _controller: 'scheb_two_factor.form_controller::form' }
2fa_login_check:
    path: /2fa_check
admin_two_factor:
    resource: '@AdminBundle/Controller/TwoFactorController.php'
    type: attribute
    prefix: /admin
```

**Several connected areas** — `/app` for the customer, `/admin` for the platform — import the screen
once per area, each with its prefix, name prefix and layout, and link it from their own
`admin_account_menu_extra`; `admin.routes.two_factor` is then emptied. The screen renders in the
area's layout and comes back to the area's route, and every import stays open under `required`:

```yaml
app_two_factor:
    resource: '@AdminBundle/Controller/TwoFactorController.php'
    type: attribute
    prefix: /app
    name_prefix: app_                                 # app_admin_account_two_factor
    defaults: { _admin_layout: 'app/layout.html.twig' }
```

At sign-in, the form still redirects to its usual target; it is the NEXT request that scheb sends to
`/2fa` while the code is missing — a functional test follows that redirect before expecting `/2fa`.

The screen (`admin_account_two_factor`) enrols with a QR code — an SVG `data:` URI, nothing the
CSP refuses — and a first correct code; it turns the second factor off on a **current** code only,
so a session left open is not enough; it refuses to turn it off when `required`. Its account-menu
entry is `admin.routes.two_factor` — empty it in an application with several areas and link the
screen from `admin_account_menu_extra`.

The bundle writes no audit trail: it dispatches `TwoFactorEvent::ENABLED`, `DISABLED` and
`FAILED` (a wrong code at sign-in), and an application that keeps one listens. ⚠️ An application
whose `User` is `#[Auditable]` (jul6art/audit-bundle) adds `totpSecret` to its ignored fields: the
secret is read back to check a code, so in clear in an update diff it would hand the second factor
to whoever reads the trail. An account that lost
its phone is reset by `TotpEnrolment::reset()`.

| Catalogue | Keys |
| --- | --- |
| `security` | `security.two_factor.{title, subtitle, code, submit, cancel, page_title, help, required, enabled_since, disable_help, disable, cannot_disable, step_scan, step_confirm, qr_alt, manual, enable}` |
| `validators` | `two_factor.code.{not_blank, format, invalid}` |
| `SchebTwoFactorBundle` | `two_factor.throttled` — scheb translates its own errors |
| `messages` | `nav.two_factor`, and the screen's flashes `security.two_factor.flash.{enabled, disabled}` — the shell's toasts translate in `messages` |

#### Backup codes (1.34)

Ten single-use codes (`K7QM2-XW9RT`) for the day the phone is lost — drawn when the second factor is
turned on, shown **once** on the page that follows, then only counted; a new set takes a current code
and replaces the old one. A backup code is accepted wherever a current code is: at sign-in (a second,
folded form on the code page, so the first keeps its numeric keyboard), and on the account screen to
turn the second factor off or draw new codes — which is what lets an account without its phone get
back in and enrol a new one.

```yaml
admin:
    two_factor:
        enabled: true
        backup_codes: true   # needs scheb/2fa-backup-code
```

```php
class User implements UserInterface, TwoFactorAwareInterface, BackupCodeAwareInterface
{
    use TwoFactorTrait;
    use TwoFactorBackupCodesTrait;   // totp_backup_codes (JSON) — then a migration
}
```

The codes are stored as SHA-256 hashes and can never be read back. Keep `totpBackupCodes` out of an
audited diff and out of any report, as `totpSecret`. The bundle dispatches
`TwoFactorEvent::BACKUP_CODES_GENERATED` and `TwoFactorEvent::BACKUP_CODE_USED` (at sign-in or on the
screen — often the sign of a lost phone). `Contract\BackupCodeAwareInterface`,
`TwoFactorBackupCodesTrait` and `TwoFactor\BackupCodeGenerator` stand alone: an application with its
own two-factor implementation uses them without `admin.two_factor`.

| Catalogue | Keys |
| --- | --- |
| `security` | `security.two_factor.code_or_backup`, `security.two_factor.backup.{title, help, save_now, none, left, few_left, generate_help, generate, login_toggle, login_help, login_label, login_submit}` — `left` takes `%count%` |
| `validators` | `two_factor.code.format_or_backup` |
| `messages` | `security.two_factor.flash.backup_codes` |

### The e-mail layout (1.30)

`@Admin/email/base.html.twig` and its button `@Admin/email/_button.html.twig` draw a transactional
mail — logo, name, content panel, footer — entirely inline, since a mail client loads no stylesheet.
Their colours come from `branding.email`; every key defaults to the historical slate layout, so a
product that declares nothing sends exactly the mails it sent before.

```yaml
admin:
    branding:
        email:
            accent: '#1565d8'        # the button: your --accent-600, AA with white text
            accent_text: '#ffffff'
```

A product whose identity is dark sends a night mail, with its light wordmark:

```yaml
admin:
    branding:
        logo: 'img/logo-ink.png'
        logo_dark: 'img/logo-white.png'
        email:
            background: '#0e0a1a'
            panel: '#151026'
            border: '#2a1f45'
            text: '#dcd0e6'
            heading: '#f8f1fa'
            muted: '#ada0c0'
            accent: '#ff4f9a'
            accent_text: '#1a0b14'
            logo: dark               # serves logo_dark; falls back to logo when there is none
```

| Key | Default | Where |
| --- | --- | --- |
| `background` | `#f1f5f9` | the page around the panel |
| `panel` / `border` | `#ffffff` / `#e2e8f0` | the content panel |
| `text` | `#334155` | the content |
| `heading` | `#1e293b` | the product name under the logo |
| `muted` | `#94a3b8` | the footer |
| `accent` / `accent_text` | `#1e293b` / `#ffffff` | the button |
| `logo` | `light` | `dark` serves `branding.logo_dark` |

> ⚠️ Values are `#rrggbb` literals — they are written into `style` attributes, never CSS custom
> properties. A palette whose content on the panel, name on the background or button text on the
> accent falls under 4.5:1 is refused when the container compiles, not discovered in an inbox.

### Deadlines to renew (1.36)

An API secret that expires in 24 months, a service account's token every 60 days, an annual review: a
screen lists them, the nearest first, and an e-mail reminds of each one **at D-7, D-1 and on the day**
(or later, while nothing was renewed) — each once per date. A reminder missed (the worker was stopped) goes
at the next run, the most urgent one alone; a new date re-arms the three.

The bundle knows no entity. The application declares its own:

```php
#[ORM\Entity]
#[ORM\Table(name: 'deadline')]
class Deadline implements DeadlineInterface
{
    use DeadlineTrait;   // label, due_on, period_days, renew_url, note, source, notified_stage

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function getId(): ?int { return $this->id; }
}
```

```yaml
# config/packages/admin.yaml
admin:
    deadlines:
        entity: App\Entity\Deadline              # empty (the default) registers nothing
        recipients: '%env(DEADLINE_ALERTS_TO)%'   # comma-separated, or a list
        from: '%env(MAILER_FROM)%'
        access: ROLE_SUPER_ADMIN                  # asked by every action of the screen
        locale: fr                                # the reminders are written outside any request
        timezone: Europe/Brussels                 # where « today » is read

# config/routes/admin.yaml — keep the route names: `admin_deadline_index` is the link of every reminder
admin_deadlines:
    resource: '@AdminBundle/Controller/DeadlineController.php'
    type: attribute
    prefix: /admin
```

Then the menu entry, with **the same attribute** as `access` — a menu test reads it back as the
container parameter `admin.deadlines.access`, since the screen carries no `#[IsGranted]` of its own —, and
the morning run in the schedule:

```php
new NavItem('admin_deadline_index', 'nav.deadlines', 'fa-solid fa-calendar-check', permission: 'ROLE_SUPER_ADMIN');

RecurringMessage::every('1 day', new RunCommandMessage('admin:deadlines:remind'), from: '08:00');
```

**A deadline the code already knows** — a token's date of entry plus its lifetime — comes from a
`DeadlineProviderInterface` (declaring the class as a service is enough). Its rows are written before each
run and on each visit of the screen, keyed by `source`; the screen shows them without letting them be
edited, and a source that stops yielding is removed:

```php
final readonly class NpssoDeadline implements DeadlineProviderInterface
{
    public function deadlines(): iterable
    {
        $account = $this->service->current();
        if (null !== $account) {
            yield new ProvidedDeadline('psn.npsso', 'NPSSO PSN', $account->getExpectedExpiry(), 'https://ca.account.sony.com/api/v1/ssocookie');
        }
    }
}
```

« Renewed » sets today plus the period (`period_days`); without one, the date is corrected in the form.
Deleting lives on the edit screen only — a row is never erased by a stray click.

> ⚠️ **The mail carries no secret** — the label, the date, the days left, the renewal link and the
> screen's. A forwarded reminder leaks nothing. And with no recipient, nothing is sent **and nothing is
> marked sent**: the day the address is set, the reminder goes.

The catalogue is the application's, domain `deadline`: `title`, `subtitle`, `breadcrumb`, `date_format`
(a PHP date format), `action.{new,open,renew,edit,delete,save,cancel}` (`renew` receives `%count%`, the
period), `stats.{past,soon,month,total}` and their `_hint`, `list.{title,count,empty_title,empty_text}`,
`status.{past,soon,month,later,undated}`, `left` and `overdue` (`%count%` days, plural forms), `provided`,
`period` (`%count%`), `progress` (`%percent%`), `progress_label`, `no_period`,
`reminders.{title,lead,empty,today}`, `stage.{7,1,0}`, `edit.{title_new,lead,danger_title,danger_text}`,
`field.{label,due_on,period_days,renew_url,note}`, `help.{period_days,renew_url,note}`,
`error.{label,due_on,period_days,renew_url}` and `email.{subject,subject_past,lead,lead_past,what,when,renew,screen}`
(`%app%`, `%label%`, `%date%`, `%count%`). The three flashes are keys of **`messages`**, like every flash
the shell's toasts render: `deadline.flash.{saved,renewed,deleted}`.

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
