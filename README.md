# OrtusIT New WP-Admin URL

**Version:** 1.4  
**Author:** OrtusIT — [ortusit.com](https://ortusit.com/)

**Repository:** [github.com/ortusit/oit-wp-login](https://github.com/ortusit/oit-wp-login) — `git clone https://github.com/ortusit/oit-wp-login.git`

---

## Overview

The plugin lets you set a custom URL slug (in **Settings → Permalinks**) so visitors no longer use the default `wp-login.php` address. Requests still go through WordPress's real login script (via rewrite rules or, when permalinks are "plain", optional `.htaccess` rules), and the plugin adds session checks so the login flow is only allowed when you arrive through your chosen slug—otherwise users are redirected to the site home.

---

## Requirements

| | Minimum |
|---|--------|
| WordPress | 5.5 |
| PHP | 7.4 |
| Tested up to | WordPress 6.9.x |

---

## Installation

1. Copy the plugin folder to `wp-content/plugins/oit-wp-login-url/` (or upload a ZIP via **Plugins → Add New → Upload Plugin**).
2. Activate the plugin from the Plugins screen.
3. Go to **Settings → Permalinks** and set **New WP-Admin slug** (allowed characters: `a-z`, `0-9`, `-`, `_`).
4. Save your permalink settings (save twice if you also changed the permalink structure).

---

## Git

Clone the repository:

```bash
git clone https://github.com/ortusit/oit-wp-login.git
cd oit-wp-login
```

If you are publishing this folder as a new remote (no `.git` yet), from the project root:

```bash
git init
git remote add origin https://github.com/ortusit/oit-wp-login.git
git add README.md oit-wp-login-url.php
git commit -m "Initial commit: OrtusIT New WP-Admin URL v1.4"
git branch -M main
git push -u origin main
```

If `origin` already exists: `git remote set-url origin https://github.com/ortusit/oit-wp-login.git`

---

## License

See [LICENSE](LICENSE) in this repository (MIT).

---

## What's new in 1.4

### Bug fixes

- **PHP 8.x — `Undefined array key` on login:** parsing `REQUEST_URI` when there is no query string (`?`) no longer uses `list()` on a single-element `explode()`; the query string is safely treated as empty.
- **`.htaccess`:** reads via `file_get_contents` with path checks and `is_readable`, avoiding `file()` on a missing file (prevents PHP 8 type issues and warnings).
- **`fopen` / `fwrite`:** writes after removing markers only if the file opens successfully; the handle is closed properly.
- **`get_home_path()`:** if `strripos` finds nothing, returns `ABSPATH` instead of an invalid `substr` from `false`.

### PHP 8.x compatibility

- Safe access to `$_SERVER` keys (`REQUEST_URI`, `HTTPS`, `HTTP_HOST`, `SCRIPT_NAME`, `QUERY_STRING`, etc.).
- `parse_url( home_url() )` — validates the result before reading `path`.
- `rewrite_base` logic uses `explode( …, 2 )` without assuming a missing array index.
- `register_setting` uses the array form with `sanitize_callback`, `type`, and `default`.
- Strict string comparisons (`===` / `!==`) where appropriate; `.htaccess` marker lines normalized with `rtrim` for `\r\n` (Windows).

### WordPress compatibility (including 6.9.x)

- Plugin headers: `Requires at least`, `Tested up to`, `Requires PHP`.
- `ABSPATH` guard at the top of the main file.
- Login redirects use **`wp_safe_redirect( home_url( '/' ) )`** instead of a raw `header( 'Location: …' )`.
- Settings field and **`register_setting`** run only on the Permalinks screen or on `options.php` POST with `option_page=permalink`, so form saves keep working.
- `admin_init` hook order: register the setting first, then handle POST.
- Slug field output uses **`esc_attr()`**; `$_POST` values go through **`wp_unslash()`**.

### Other

- `in_array( …, true )` for `$pagenow` checks.
- `isset( $GLOBALS['pagenow'] )` before use.

---

## Support

Questions and custom work: [OrtusIT](https://ortusit.com/).

---

## Copyright

© 2026 OrtusIT. All rights reserved.
