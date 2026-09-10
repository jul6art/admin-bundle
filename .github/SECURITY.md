# Security Policy

## Supported versions

`jul6art/admin-bundle` is installed by other applications through Composer, so a fix here
reaches them the moment they update. Only the current major line gets one.

| Version | Supported |
| --- | --- |
| `1.x` | ✅ |
| any older tag or fork | ❌ |

Support means security fixes on the latest release of that line — upgrade to it before
reporting, in case the problem is already gone.

## What is in scope

This bundle ships the sign-in pages and the navigation of a back office, so its defects are
the ones an unauthenticated visitor meets first:

* **Anything wrong with the authentication screens** — a missing or reusable CSRF token,
  login throttling that does not throttle, a remember-me cookie that outlives a logout or is
  issued without the check, a logout reachable by GET.
* **A navigation item shown without the permission it requires** — the builder needs
  `security.authorization_checker`, and a path that skips it leaks the shape of the
  application.
* **A route this bundle ships without an explicit access decision**, or one whose decision
  can be bypassed.
* **The appearance screen writing another account's preferences**, or reading them from a
  request parameter instead of the session identity.
* **Cross-site scripting through the shell** — a navigation label, a user preference, a
  flash message or a breadcrumb rendered unescaped.
* **A password reaching a template, a log or the profiler** through the sign-in forms or
  their error handling.

Out of scope: vulnerabilities in Symfony, Doctrine, API Platform or any other third-party
package — report those to the project that owns the code, and they will reach you through
your own `composer update`. Also out of scope: an application that misconfigures this bundle
in a way the README warns against, though a warning that turns out to be easy to miss is
worth an issue of its own.

## Reporting a vulnerability

**Do not open a public issue for a security problem.**

Use [GitHub's private vulnerability reporting](https://github.com/jul6art/admin-bundle/security/advisories/new)
(the **Security** tab → *Report a vulnerability*). It opens a draft advisory only
you and the maintainers can read, and it is the channel this project prefers —
no email address needs to be published for it to work.

Please include:

* the version of `jul6art/admin-bundle` and of Symfony you are running,
* the relevant part of your bundle configuration,
* the shortest reproduction you have — ideally a failing test against this
  repository, since that is what a fix will be built on,
* what an attacker gains: which check is bypassed, which data is read or
  written, and whether authentication is required.

## What to expect

* An acknowledgement within **7 days**.
* An assessment — accepted, out of scope, or needing more detail — within
  **14 days**.
* For an accepted report: a fix released on the supported line, a
  [security advisory](https://github.com/jul6art/admin-bundle/security/advisories)
  describing the impact and the version to upgrade to, and credit in it unless
  you ask otherwise.

Please give the maintainers a reasonable window to ship a release before disclosing
publicly. This project runs no bug-bounty programme and offers no payment.