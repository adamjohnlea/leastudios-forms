=== leaStudios Forms ===
Contributors: leastudios
Tags: forms, contact form, form builder, email notifications, submissions
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight form builder for WordPress. Create contact forms, feedback forms, and more with an intuitive editor.

== Description ==

leaStudios Forms is a lightweight form builder that focuses on simplicity and security. Build forms with 11 field types, manage submissions from the admin, and send email notifications with merge tags.

**Key features:**

* **11 field types** — text, email, textarea, select, checkbox, radio, hidden, number, phone, URL, and address.
* **Form editor** — configure fields, notifications, and settings from a clean admin interface.
* **Email notifications** — send notification emails on submission with customisable recipients, subjects, and body content using merge tags.
* **Submission management** — view all entries with search and filtering. Export submissions to CSV.
* **Shortcode and Gutenberg block** — embed forms anywhere with `[leastudios_form id="X"]` or the block editor.
* **Spam protection** — built-in honeypot field and IP-based rate limiting.
* **Mailer integration** — when leaStudios Mailer is active, email delivery status is tracked per submission.

**Field types:**

Text, Email, Textarea, Select dropdown, Checkbox, Radio buttons, Hidden, Number, Phone, URL, and Address (multi-line with street, city, state, postcode, country).

== Installation ==

1. Upload the `leastudios-forms` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Go to Forms in the admin menu to create your first form.
4. Add the form to a page using the shortcode or Gutenberg block.

== Frequently Asked Questions ==

= Can I receive email notifications when a form is submitted? =

Yes. Each form can have multiple notification emails configured with customisable recipients, subject lines, and body content. Use merge tags like `{field:name}` and `{all_fields}` to include submission data.

= Does it support file uploads? =

Not currently. The plugin focuses on text-based form fields.

== Changelog ==

= 1.0.0 =
* Initial release.
