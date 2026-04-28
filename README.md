# leaStudios Forms

Lightweight form builder for WordPress. Create contact forms, feedback forms, and more — 11 field types, email notifications with merge tags, submission management, CSV export, and built-in spam protection.

- **Requires WordPress:** 6.4+
- **Requires PHP:** 8.1+
- **License:** GPL-2.0-or-later

## Features

- **11 field types** — text, email, textarea, select, checkbox, radio, hidden, number, phone, URL, address.
- **Form editor** for fields, notifications, and settings.
- **Email notifications** with merge tags (`{field:name}`, `{all_fields}`, …).
- **Submission management** — search, filter, and export entries to CSV.
- **Shortcode + Gutenberg block** — `[leastudios_form id="..."]` or the block editor.
- **Spam protection** — honeypot field and IP-based rate limiting.

## Installation

1. Upload `leastudios-forms` to `/wp-content/plugins/`.
2. Activate via Plugins → Installed Plugins.
3. Go to **Forms** in the admin menu and create your first form.
4. Add the form to a page using the shortcode or block.

## Related plugins

This plugin is part of the leaStudios plugin family. It works on its own, and integrates with:

- **[leastudios-mailer](../leastudios-mailer)** — when active, form notification email delivery is tracked per submission.
- **[leastudios-email-templates](../leastudios-email-templates)** — wraps form notification emails in your branded template.

## Development

This plugin is self-contained — it can be cloned, linted, tested, and packaged on its own.

```bash
composer install            # install dependencies (incl. dev tools)
composer lint               # phpcs + phpstan
composer test               # phpunit (requires the WP test library)
composer phpcbf             # auto-fix WPCS issues
```

To run the test suite, install the WordPress test library once:

```bash
bash ../leastudios-dev-tools/bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

The shared scaffold, packaging script, and project-wide development conventions live in **[leastudios-dev-tools](../leastudios-dev-tools)** — start there when bootstrapping a new plugin or making cross-plugin tooling changes.

## License

GPL-2.0-or-later. See `readme.txt` for the WordPress.org-style header.
