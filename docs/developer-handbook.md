# leaStudios Forms — Developer Handbook

leaStudios Forms is a lightweight WordPress form builder with 11 field types, email
notifications, submission management, and built-in spam protection. Every stage of the
form lifecycle — render, submission, notification, admin display, and registration — is
covered by named hooks, giving extension authors a clean seam to inject logic, add field
types, and integrate with external services without patching core files.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Architecture](#2-architecture)
3. [Development Setup](#3-development-setup)
4. [Concepts](#4-concepts)
5. [Data Model](#5-data-model)
6. [Hooks Reference](#6-hooks-reference)
7. [Hook Execution Order](#7-hook-execution-order)
8. [REST API Reference](#8-rest-api-reference)
9. [Public PHP API](#9-public-php-api)
10. [Extension Recipes](#10-extension-recipes)
11. [Testing](#11-testing)
12. [Release Process](#12-release-process)
13. [Where to Read More](#13-where-to-read-more)

---

## 1. Overview

leaStudios Forms lets WordPress site owners create and embed contact forms, feedback
forms, and other data-collection forms without writing code. Site visitors fill in the
form; the plugin validates submissions server-side, stores entries in a custom database
table, and sends configurable email notifications — all with no reliance on third-party
form services.

The plugin ships eleven built-in field types (text, email, textarea, select, checkbox,
radio, hidden, number, phone, URL, and address) and exposes a `Field_Type` interface so
extension authors can register custom types. Forms are created as a custom post type in
the WordPress admin and embedded with a shortcode (`[leastudios_form id="…"]`) or a
Gutenberg block.

For extension authors the most important entry points are:

- **Hooks** — 18 named `do_action` and `apply_filters` hooks covering render, submission,
  notification, admin display, and registration phases (see Section 6).
- **`leastudios_forms_field_types`** — register a custom `Field_Type` implementation to
  add a new field type to the form editor.
- **REST API** — `POST /wp-json/leastudios-forms/v1/submissions` accepts public
  submissions with nonce verification and IP rate limiting (see Section 8).

The plugin integrates with `leastudios-mailer` (delivery-status tracking in the entry
detail view) and `leastudios-email-templates` (branded notification emails). Both
integrations are optional; the plugin degrades gracefully when those siblings are absent.

---

## 2. Architecture

### Component map

```
leastudios-forms.php
    └── Plugin::init()
            |
            ├── Database\Migration::maybe_migrate()   schema up-to-date check
            |
            ├── CPT\Form_Post_Type                    registers leastudios_form CPT
            ├── Field\Field_Registry                  loads 11 built-in field types
            ├── Form\Form_Repository                  reads form CPT + meta
            ├── Entry\Entry_Repository                reads/writes entries table
            |
            ├── Submission\Submission_Handler::handle()  shared submission core
            ├── REST\Submission_Controller            POST /submissions (REST entry point)
            ├── Plugin::handle_fallback_submission()  admin-post.php (no-JS entry point)
            |
            ├── Notification\Email_Notifier           sends notification emails
            ├── Integration\Mailer_Integration        delivery-status provider
            |         (boots when leastudios-mailer is active)
            |
            ├── Render\Form_Renderer                  HTML rendering
            ├── Render\Shortcode                      [leastudios_form] shortcode
            ├── Render\Block                          Gutenberg block wrapper
            |
            └── Admin\{Forms,Entries,Settings}_Page   admin pages (is_admin only)
```

### Submission pipeline

Both entry points call the same `Submission_Handler::handle()` method:

```
Browser submits form
    |
    +-- REST: POST /wp-json/leastudios-forms/v1/submissions   (Submission_Controller)
    |       OR
    +-- No-JS: admin-post.php leastudios_forms_submit / _nopriv   (Plugin)
    |
    Submission_Handler::handle()
        |
        [action]  leastudios_forms_before_submission
        |
        [filter]  leastudios_forms_spam_detected      (honeypot check)
        |         if spam → abort, return error response
        |
        Form_Repository::get_form()                   load CPT + meta
        |
        Rate-limit check (per-IP)
        |
        Per-field-type sanitize via Field_Registry
        |
        [filter]  leastudios_forms_sanitized_data
        |
        Per-field-type validate
        |
        [filter]  leastudios_forms_validation_errors
        |         if errors → return validation error response
        |
        [filter]  leastudios_forms_entry_data
        |
        Entry_Repository::create()                    write row to entries table
        |
        [action]  leastudios_forms_submission_created
        |
        Email_Notifier::notify()
            |
            [filter]  leastudios_forms_notification_message   (per notification)
            [filter]  leastudios_forms_notification_args      (per notification)
            wp_mail()
            |
        [action]  leastudios_forms_notification_sent
        |
        [filter]  leastudios_forms_submission_response
        |
        Return response to client
```

### Key design decisions

- **Single code path.** The REST controller and the `admin-post.php` fallback both call
  `Submission_Handler::handle()`. This means every filter and action fires regardless of
  which transport was used.
- **No DI container.** `Plugin::init()` constructs every component by hand and passes
  dependencies through constructors.
- **Field types via registry.** Built-in types and third-party types are treated equally
  by `Field_Registry` — there is no special-casing for the 11 built-ins.

---

## 3. Development Setup

```bash
cd wp-content/plugins/leastudios-forms
composer install
composer lint              # phpcs + phpstan
composer test              # PHPUnit (requires WP test library — see below)
composer phpcbf            # auto-fix WPCS issues
```

### WordPress test library (one-time, shared across all plugins)

```bash
bash ../leastudios-dev-tools/bin/install-wp-tests.sh \
    wordpress_test root '' 127.0.0.1 latest
```

The library installs to `/tmp/wordpress-tests-lib/`. All plugin `tests/bootstrap.php`
files look there automatically.

### Sibling plugin integrations

- **leastudios-mailer** — activate to enable per-notification delivery-status tracking in
  the entry detail view. Optional; the plugin degrades gracefully without it.
- **leastudios-email-templates** — when active, wraps all form notification emails in the
  site's branded template. Optional.

---

## 4. Concepts

### Form

A form is a custom post (`leastudios_form`) managed through the **Forms** admin menu.
Its field definitions are stored as a JSON blob in the `_leastudios_forms_fields` post
meta key; its behaviour settings (notification address, success message, spam protection
toggles) are stored in `_leastudios_forms_settings`. All form data access goes through
`Form\Form_Repository`.

### Field

A field is a single input within a form, represented as an associative array in the
`_leastudios_forms_fields` JSON. The array carries at minimum a `type` key (matching a
registered `Field_Type` slug) and a `name` key. Additional keys — `label`, `required`,
`placeholder`, `options` — depend on the field type.

### Field type

A field type is a PHP class implementing the `Field_Type` interface. It defines how a
field is sanitised, validated, and rendered. The 11 built-in types live in
`src/Field/Types/`. Third-party types are registered via the
`leastudios_forms_field_types` action (see Section 9 and the [custom field type
recipe](#how-do-i-register-a-custom-field-type)).

### Entry

An entry is a database row in `{prefix}leastudios_forms_entries` created when a form
submission passes spam checks and validation. The submitted field values are serialised
as a JSON blob in the `field_data` column. Entries are managed through the **Forms →
Entries** admin page.

### Notification

A notification is an email sent after a successful entry is stored. Each form can have
one or more notification configurations (stored in `_leastudios_forms_settings`),
specifying a recipient address, a subject template, and a body template. Body templates
support merge tags like `{field:email}` and `{all_fields}`. `Email_Notifier` processes
and dispatches all notifications for a given submission.

---

## 5. Data Model

### `leastudios_form` CPT

Forms are stored as WordPress posts with `post_type = 'leastudios_form'`.

| Post meta key                   | Type     | Description                                      |
|---------------------------------|----------|--------------------------------------------------|
| `_leastudios_forms_fields`      | `string` | JSON array of field definition objects.          |
| `_leastudios_forms_settings`    | `string` | JSON object hydrated into a `Form_Settings` value object. Includes `notification_email`, `success_message`, `notifications`, and spam/rate-limit overrides. |

Access forms through `Form\Form_Repository` — do not read these meta keys directly, as
`Form_Repository` handles JSON decoding and populates defaults.

### `{prefix}leastudios_forms_entries`

One row per accepted form submission.

| Column                    | Type                    | Description                                      |
|---------------------------|-------------------------|--------------------------------------------------|
| `id`                      | `bigint unsigned PK`    | Auto-increment local entry ID.                   |
| `form_id`                 | `bigint unsigned`       | FK → the `leastudios_form` post ID.              |
| `field_data`              | `longtext`              | JSON object mapping field names to values.       |
| `status`                  | `varchar(20)`           | `unread` (default) or `read`.                    |
| `ip_address`              | `varchar(45)`           | Submitter's IP; nullable.                        |
| `user_agent`              | `varchar(255)`          | Browser user-agent; nullable.                    |
| `user_id`                 | `bigint unsigned`       | WordPress user ID if logged in; nullable.        |
| `notification_message_ids`| `text`                  | JSON array of SES message IDs from the mailer integration; nullable. |
| `created_at`              | `datetime`              | UTC submission timestamp; indexed.               |
| `updated_at`              | `datetime`              | Auto-updates on row change.                      |

Access entries through `Entry\Entry_Repository` — `create()`, `get()`, `update_status()`,
`delete()`, `paginate()`. Do not write to this table directly.

**Schema version option:** `leastudios_forms_schema_version` (current target: `3`).
Schema version 3 removed payment columns that were introduced in v2. If you have a site
running schema v2, `maybe_migrate()` drops those columns on next load.

### Options

| Option key                    | Type    | Description                                                    |
|-------------------------------|---------|----------------------------------------------------------------|
| `leastudios_forms_options`    | `array` | Plugin settings (see below).                                   |
| `leastudios_forms_schema_version` | `int` | DB schema version; managed by `Database\Migration`.        |

The `leastudios_forms_options` array has the following keys:

| Key                    | Type     | Default              | Description                                      |
|------------------------|----------|----------------------|--------------------------------------------------|
| `notification_email`   | `string` | `admin_email` option | Fallback notification recipient address.         |
| `entry_retention_days` | `int`    | `90`                 | Days before entries are pruned (when configured).|
| `honeypot_enabled`     | `bool`   | `true`               | Whether the honeypot spam check is active.       |
| `rate_limit`           | `int`    | `5`                  | Max submissions per IP within the window.        |
| `rate_limit_window`    | `int`    | `60`                 | Rate-limit window in seconds.                    |

---

## 6. Hooks Reference

The hooks below let you customise every stage of the form lifecycle. They are grouped by
phase: render, submission, notification, admin, and registration. Within each group,
filters appear before actions.

### Render Hooks

#### `leastudios_forms_form_attributes`

- **Type:** Filter
- **Location:** `src/Render/Form_Renderer.php`
- **Since:** 1.0.0
- **Description:** Filters the HTML attributes array for the `<form>` tag. Use this to add custom classes, data attributes, or change the form method.

**Parameters:**

| Parameter     | Type    | Description                                      |
|---------------|---------|--------------------------------------------------|
| `$attributes` | `array` | Key-value pairs of form tag attributes.           |
| `$form_id`    | `int`   | The form post ID.                                |

**Returns:** `array` — The filtered attributes array.

**Example:**

```php
add_filter( 'leastudios_forms_form_attributes', function ( array $attributes, int $form_id ): array {
    // Add a custom CSS class and data attribute for analytics.
    $attributes['class']              .= ' js-tracked-form';
    $attributes['data-analytics-id']   = 'form-' . $form_id;
    return $attributes;
}, 10, 2 );
```

---

#### `leastudios_forms_render_field`

- **Type:** Filter
- **Location:** `src/Render/Form_Renderer.php`
- **Since:** 1.0.0
- **Description:** Filters the HTML output for each individual field. Use this to modify field markup, add wrapper elements, or inject custom attributes.

**Parameters:**

| Parameter       | Type     | Description                    |
|-----------------|----------|--------------------------------|
| `$html`         | `string` | The rendered field HTML.       |
| `$field_config` | `array`  | The field configuration array. |
| `$value`        | `mixed`  | The current field value.       |
| `$form_id`      | `int`    | The form post ID.              |

**Returns:** `string` — The filtered field HTML.

**Example:**

```php
add_filter( 'leastudios_forms_render_field', function ( string $html, array $field_config, mixed $value, int $form_id ): string {
    // Add a help tooltip icon after every email field.
    if ( 'email' === ( $field_config['type'] ?? '' ) ) {
        $tooltip = '<span class="tooltip-icon" title="We will never share your email.">&#9432;</span>';
        $html    = str_replace( '</div>', $tooltip . '</div>', $html );
    }
    return $html;
}, 10, 4 );
```

---

#### `leastudios_forms_shortcode_output`

- **Type:** Filter
- **Location:** `src/Render/Shortcode.php`
- **Since:** 1.0.0
- **Description:** Filters the final HTML output of the `[leastudios_form]` shortcode. Use this to wrap the form in additional markup or modify the output after rendering.

**Parameters:**

| Parameter  | Type     | Description              |
|------------|----------|--------------------------|
| `$html`    | `string` | The rendered form HTML.  |
| `$form_id` | `int`    | The form post ID.        |

**Returns:** `string` — The filtered shortcode HTML.

**Example:**

```php
add_filter( 'leastudios_forms_shortcode_output', function ( string $html, int $form_id ): string {
    // Wrap every form in a card container.
    return '<div class="form-card">' . $html . '</div>';
}, 10, 2 );
```

---

#### `leastudios_forms_before_render`

- **Type:** Action
- **Location:** `src/Render/Form_Renderer.php`
- **Since:** 1.0.0
- **Description:** Fires before the form HTML output begins. Use this to inject content or perform side effects before a form is rendered.

**Parameters:**

| Parameter   | Type     | Description                  |
|-------------|----------|------------------------------|
| `$form_id`  | `int`    | The form post ID.            |
| `$fields`   | `array`  | The form field configurations.|
| `$settings` | `object` | The form settings object.    |

**Example:**

```php
add_action( 'leastudios_forms_before_render', function ( int $form_id, array $fields, object $settings ): void {
    // Track which forms are being rendered on the page.
    error_log( sprintf( 'Rendering form #%d with %d fields.', $form_id, count( $fields ) ) );
}, 10, 3 );
```

---

#### `leastudios_forms_after_render`

- **Type:** Action
- **Location:** `src/Render/Form_Renderer.php`
- **Since:** 1.0.0
- **Description:** Fires after the form HTML output has completed. Use this to inject scripts, tracking pixels, or additional markup after a form.

**Parameters:**

| Parameter  | Type  | Description       |
|------------|-------|-------------------|
| `$form_id` | `int` | The form post ID. |

**Example:**

```php
add_action( 'leastudios_forms_after_render', function ( int $form_id ): void {
    // Add a conversion tracking pixel after the contact form.
    if ( 42 === $form_id ) {
        echo '<img src="https://analytics.example.com/pixel?form=contact" alt="" width="1" height="1" />';
    }
}, 10, 1 );
```

---

### Submission Hooks

#### `leastudios_forms_spam_detected`

- **Type:** Filter
- **Location:** `src/Submission/Submission_Handler.php`
- **Since:** 1.0.0
- **Description:** Filters the spam detection result. The default value comes from the honeypot check. Use this to integrate additional spam detection services (reCAPTCHA, Akismet, etc.) or override the built-in check.

**Parameters:**

| Parameter   | Type    | Description                               |
|-------------|---------|-------------------------------------------|
| `$is_spam`  | `bool`  | Whether the submission is detected as spam.|
| `$form_id`  | `int`   | The form post ID.                         |
| `$raw_data` | `array` | The raw submitted data.                   |

**Returns:** `bool` — Whether the submission should be treated as spam.

**Example:**

```php
add_filter( 'leastudios_forms_spam_detected', function ( bool $is_spam, int $form_id, array $raw_data ): bool {
    // Already flagged as spam by honeypot, no need to check further.
    if ( $is_spam ) {
        return true;
    }

    // Integrate with a custom blocklist.
    $blocked_emails = [ 'spam@example.com', 'bot@example.net' ];
    $email          = $raw_data['email'] ?? '';

    if ( in_array( strtolower( $email ), $blocked_emails, true ) ) {
        return true;
    }

    return false;
}, 10, 3 );
```

---

#### `leastudios_forms_sanitized_data`

- **Type:** Filter
- **Location:** `src/Submission/Submission_Handler.php`
- **Since:** 1.0.0
- **Description:** Filters the sanitized submission data before validation runs. Use this to modify, augment, or normalise field values after the built-in sanitisation but before validation.

**Parameters:**

| Parameter        | Type    | Description                           |
|------------------|---------|---------------------------------------|
| `$sanitized_data`| `array` | The sanitized submitted data.         |
| `$form_id`       | `int`   | The form post ID.                     |
| `$fields_config` | `array` | The form fields configuration array.  |

**Returns:** `array` — The filtered sanitized data.

**Example:**

```php
add_filter( 'leastudios_forms_sanitized_data', function ( array $data, int $form_id, array $fields_config ): array {
    // Normalise phone numbers to E.164 format before validation.
    if ( isset( $data['phone'] ) ) {
        $data['phone'] = preg_replace( '/[^+\d]/', '', $data['phone'] );
    }
    return $data;
}, 10, 3 );
```

---

#### `leastudios_forms_validation_errors`

- **Type:** Filter
- **Location:** `src/Submission/Submission_Handler.php`
- **Since:** 1.0.0
- **Description:** Filters the validation errors array after the built-in validator has run. Use this to add custom validation rules, remove errors, or modify error messages.

**Parameters:**

| Parameter        | Type    | Description                               |
|------------------|---------|-------------------------------------------|
| `$errors`        | `array` | Validation errors keyed by field name.    |
| `$form_id`       | `int`   | The form post ID.                         |
| `$sanitized_data`| `array` | The sanitized submitted data.             |

**Returns:** `array` — The filtered errors array.

**Example:**

```php
add_filter( 'leastudios_forms_validation_errors', function ( array $errors, int $form_id, array $data ): array {
    // Require a corporate email address on the business enquiry form.
    if ( 42 === $form_id && ! empty( $data['email'] ) ) {
        $free_providers = [ 'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com' ];
        $domain         = strtolower( substr( strrchr( $data['email'], '@' ), 1 ) );

        if ( in_array( $domain, $free_providers, true ) ) {
            $errors['email'] = __( 'Please use your company email address.', 'my-plugin' );
        }
    }
    return $errors;
}, 10, 3 );
```

---

#### `leastudios_forms_entry_data`

- **Type:** Filter
- **Location:** `src/Submission/Submission_Handler.php`
- **Since:** 1.0.0
- **Description:** Filters the data array immediately before it is stored in the database. Use this to add computed fields, strip sensitive data, or transform values for storage.

**Parameters:**

| Parameter  | Type    | Description               |
|------------|---------|---------------------------|
| `$data`    | `array` | The data to be stored.    |
| `$form_id` | `int`   | The form post ID.         |

**Returns:** `array` — The filtered data array.

**Example:**

```php
add_filter( 'leastudios_forms_entry_data', function ( array $data, int $form_id ): array {
    // Add a UTC timestamp to every stored entry.
    $data['_submitted_at_utc'] = gmdate( 'Y-m-d H:i:s' );

    // Strip credit card numbers if accidentally submitted.
    foreach ( $data as $key => $value ) {
        if ( is_string( $value ) && preg_match( '/\b\d{13,19}\b/', $value ) ) {
            $data[ $key ] = '[REDACTED]';
        }
    }

    return $data;
}, 10, 2 );
```

---

#### `leastudios_forms_submission_response`

- **Type:** Filter
- **Location:** `src/Submission/Submission_Handler.php`
- **Since:** 1.0.0
- **Description:** Filters the response array before it is returned to the client on a successful submission. Use this to add extra data to the response (e.g., redirect URLs, tracking IDs) or customise the success message.

**Parameters:**

| Parameter   | Type    | Description                                         |
|-------------|---------|-----------------------------------------------------|
| `$response` | `array` | Array with `success`, `message`, and `errors` keys. |
| `$form_id`  | `int`   | The form post ID.                                   |
| `$entry_id` | `int`   | The entry ID.                                       |

**Returns:** `array` — The filtered response array.

**Example:**

```php
add_filter( 'leastudios_forms_submission_response', function ( array $response, int $form_id, int $entry_id ): array {
    // Redirect to a thank-you page after the contact form submission.
    if ( 42 === $form_id && $response['success'] ) {
        $response['redirect'] = home_url( '/thank-you/?entry=' . $entry_id );
    }
    return $response;
}, 10, 3 );
```

---

#### `leastudios_forms_before_submission`

- **Type:** Action
- **Location:** `src/Submission/Submission_Handler.php`
- **Since:** 1.0.0
- **Description:** Fires at the very start of submission handling, before spam checks, validation, or any processing. Use this for logging, third-party integrations, or early-stage side effects.

**Parameters:**

| Parameter   | Type    | Description               |
|-------------|---------|---------------------------|
| `$form_id`  | `int`   | The form post ID.         |
| `$raw_data` | `array` | The raw submitted data.   |

**Example:**

```php
add_action( 'leastudios_forms_before_submission', function ( int $form_id, array $raw_data ): void {
    // Log all incoming submissions for debugging.
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf( 'Form #%d submission received: %s', $form_id, wp_json_encode( $raw_data ) ) );
    }
}, 10, 2 );
```

---

#### `leastudios_forms_submission_created`

- **Type:** Action
- **Location:** `src/Submission/Submission_Handler.php`
- **Since:** 1.0.0
- **Description:** Fires after a form submission entry has been successfully created in the database. Use this for CRM syncing, third-party API calls, or post-submission processing.

**Parameters:**

| Parameter        | Type    | Description                    |
|------------------|---------|--------------------------------|
| `$entry_id`      | `int`   | The newly created entry ID.    |
| `$form_id`       | `int`   | The form post ID.              |
| `$sanitized_data`| `array` | The sanitized field data.      |

**Example:**

```php
add_action( 'leastudios_forms_submission_created', function ( int $entry_id, int $form_id, array $data ): void {
    // Sync new contact form submissions to a CRM.
    if ( 42 === $form_id && ! empty( $data['email'] ) ) {
        wp_remote_post( 'https://api.crm.example.com/contacts', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . get_option( 'crm_api_key' ),
            ],
            'body' => wp_json_encode( [
                'email'      => $data['email'],
                'first_name' => $data['first_name'] ?? '',
                'source'     => 'website-form',
                'entry_id'   => $entry_id,
            ] ),
        ] );
    }
}, 10, 3 );
```

---

### Notification Hooks

#### `leastudios_forms_notification_message`

- **Type:** Filter
- **Location:** `src/Notification/Email_Notifier.php`
- **Since:** 1.0.0
- **Description:** Filters the notification email message body after all merge tags have been replaced. Use this to append footers, transform formatting, or inject dynamic content.

**Parameters:**

| Parameter    | Type     | Description                    |
|--------------|----------|--------------------------------|
| `$message`   | `string` | The notification message body. |
| `$form_id`   | `int`    | The form post ID.              |
| `$field_data` | `array` | The submitted field data.      |

**Returns:** `string` — The filtered message.

**Example:**

```php
add_filter( 'leastudios_forms_notification_message', function ( string $message, int $form_id, array $field_data ): string {
    // Append a standard footer to all notification emails.
    $footer  = '<hr style="margin-top:30px;" />';
    $footer .= '<p style="font-size:12px;color:#888;">This message was sent from ' . esc_html( get_bloginfo( 'name' ) ) . '.</p>';
    return $message . $footer;
}, 10, 3 );
```

---

#### `leastudios_forms_notification_args`

- **Type:** Filter
- **Location:** `src/Notification/Email_Notifier.php`
- **Since:** 1.0.0
- **Description:** Filters each notification's email arguments immediately before `wp_mail()` is called. Use this to modify recipients, add CC/BCC headers, change the subject line, or alter the message.

**Parameters:**

| Parameter    | Type    | Description                                          |
|--------------|---------|------------------------------------------------------|
| `$args`      | `array` | Array with `to`, `subject`, `message`, and `headers` keys. |
| `$form_id`   | `int`   | The form post ID.                                    |
| `$entry_id`  | `int`   | The entry ID.                                        |
| `$field_data`| `array` | The submitted field data.                            |

**Returns:** `array` — The filtered email args array.

**Example:**

```php
add_filter( 'leastudios_forms_notification_args', function ( array $args, int $form_id, int $entry_id, array $field_data ): array {
    // BCC the sales team on the enquiry form.
    if ( 42 === $form_id ) {
        $args['headers'][] = 'Bcc: sales-team@example.com';
    }

    // Prepend the entry ID to the subject for easier tracking.
    $args['subject'] = sprintf( '[#%d] %s', $entry_id, $args['subject'] );

    return $args;
}, 10, 4 );
```

---

#### `leastudios_forms_notification_sent`

- **Type:** Action
- **Location:** `src/Submission/Submission_Handler.php`
- **Since:** 1.0.0
- **Description:** Fires after notification emails have been sent and the entry has been updated with SES message IDs. Use this for delivery tracking, logging, or triggering follow-up workflows.

**Parameters:**

| Parameter     | Type    | Description                      |
|---------------|---------|----------------------------------|
| `$entry_id`   | `int`   | The entry ID.                    |
| `$form_id`    | `int`   | The form post ID.                |
| `$message_ids`| `array` | Array of SES message IDs.        |

**Example:**

```php
add_action( 'leastudios_forms_notification_sent', function ( int $entry_id, int $form_id, array $message_ids ): void {
    // Log the number of notifications sent for monitoring.
    error_log( sprintf(
        'Form #%d entry #%d: %d notification(s) sent. IDs: %s',
        $form_id,
        $entry_id,
        count( $message_ids ),
        implode( ', ', $message_ids )
    ) );
}, 10, 3 );
```

---

### Admin Hooks

#### `leastudios_forms_delivery_status`

- **Type:** Filter
- **Location:** `src/Admin/Entries_Page.php`
- **Since:** 1.0.0
- **Description:** Filters the delivery status display string for a notification message ID. This is used in the entry detail view's "Notification Delivery Status" section. The Mailer Integration plugin hooks into this to provide real-time delivery statuses.

**Parameters:**

| Parameter     | Type     | Description                       |
|---------------|----------|-----------------------------------|
| `$status`     | `string` | The status display string.        |
| `$message_id` | `string` | The notification message ID.      |

**Returns:** `string` — The filtered status string.

**Example:**

```php
add_filter( 'leastudios_forms_delivery_status', function ( string $status, string $message_id ): string {
    // Look up delivery status from a custom tracking table.
    global $wpdb;
    $result = $wpdb->get_var( $wpdb->prepare(
        "SELECT status FROM {$wpdb->prefix}email_tracking WHERE message_id = %s",
        $message_id
    ) );
    return $result ? ucfirst( $result ) : $status;
}, 10, 2 );
```

---

#### `leastudios_forms_entry_actions`

- **Type:** Filter
- **Location:** `src/Admin/Entries_Page.php`
- **Since:** 1.0.0
- **Description:** Filters the available actions on the entry detail page. Each action is an associative array with `url`, `label`, `class`, and optional `onclick` keys. Use this to add custom actions (e.g., resend notification, export PDF) or remove default ones.

**Parameters:**

| Parameter  | Type     | Description                                                        |
|------------|----------|--------------------------------------------------------------------|
| `$actions` | `array`  | Actions keyed by slug. Each value has `url`, `label`, `class`, and optional `onclick`. |
| `$entry`   | `object` | The entry database row object.                                     |

**Returns:** `array` — The filtered actions array.

**Example:**

```php
add_filter( 'leastudios_forms_entry_actions', function ( array $actions, object $entry ): array {
    // Add a "Resend Notification" action.
    $actions['resend'] = [
        'url'   => wp_nonce_url(
            add_query_arg( [
                'page'     => 'leastudios-forms-entries',
                'action'   => 'resend',
                'entry_id' => $entry->id,
            ], admin_url( 'admin.php' ) ),
            'leastudios_forms_entry_action'
        ),
        'label' => __( 'Resend Notification', 'my-plugin' ),
        'class' => 'button',
    ];

    return $actions;
}, 10, 2 );
```

---

### Registration Hooks

#### `leastudios_forms_address_countries`

- **Type:** Filter
- **Location:** `src/Field/Types/Address_Field.php`
- **Since:** 1.0.0
- **Description:** Filters the list of country code / country name pairs shown in the address field's country dropdown. The default list covers approximately 240 countries in alphabetical order. Return a filtered array to restrict choices or change display names.

**Parameters:**

| Parameter    | Type                   | Description                               |
|--------------|------------------------|-------------------------------------------|
| `$countries` | `array<string, string>`| Country code => country name pairs.       |

**Returns:** `array<string, string>` — The filtered countries array.

**Example:**

```php
add_filter( 'leastudios_forms_address_countries', function ( array $countries ): array {
    // Restrict the address field to North America only.
    return array_intersect_key( $countries, array_flip( [ 'US', 'CA', 'MX' ] ) );
} );
```

---

#### `leastudios_forms_post_type_args`

- **Type:** Filter
- **Location:** `src/CPT/Form_Post_Type.php`
- **Since:** 1.0.0
- **Description:** Filters the arguments passed to `register_post_type()` for the `leastudios_form` custom post type. Use this to modify labels, capabilities, visibility, REST API support, or any other post type setting.

**Parameters:**

| Parameter | Type    | Description                              |
|-----------|---------|------------------------------------------|
| `$args`   | `array` | The post type registration arguments.    |

**Returns:** `array` — The filtered arguments array.

**Example:**

```php
add_filter( 'leastudios_forms_post_type_args', function ( array $args ): array {
    // Make forms publicly queryable for a headless front end.
    $args['public']       = true;
    $args['has_archive']  = true;
    $args['rewrite']      = [ 'slug' => 'forms' ];

    // Restrict form management to administrators.
    $args['capability_type'] = 'leastudios_form';
    $args['map_meta_cap']    = true;

    return $args;
} );
```

---

#### `leastudios_forms_field_types`

- **Type:** Action
- **Location:** `src/Field/Field_Registry.php`
- **Since:** 1.0.0
- **Description:** Fires after the default field types are registered, allowing plugins or themes to register custom field types. The callback receives the `Field_Registry` instance and should call `$registry->register()` with an object implementing the `Field_Type` interface. See the [custom field type recipe](#how-do-i-register-a-custom-field-type) for a full implementation example.

**Parameters:**

| Parameter   | Type                                      | Description                     |
|-------------|-------------------------------------------|---------------------------------|
| `$registry` | `LEAStudios\Forms\Field\Field_Registry`   | The field registry instance.    |

**Example:**

```php
add_action( 'leastudios_forms_field_types', function ( \LEAStudios\Forms\Field\Field_Registry $registry ): void {
    $registry->register( new My_Plugin\Field\Date_Picker_Field() );
} );
```

---

#### `leastudios_forms_mailer_integration_init`

- **Type:** Action
- **Location:** `src/Integration/Mailer_Integration.php`
- **Since:** 1.0.0
- **Description:** Fires when the Mailer Integration is initialised (admin only). Use this to hook into or extend the mailer integration's behaviour, such as adding custom delivery status providers or modifying integration settings.

**Parameters:**

| Parameter      | Type                                                | Description                      |
|----------------|-----------------------------------------------------|----------------------------------|
| `$integration` | `LEAStudios\Forms\Integration\Mailer_Integration`   | The integration instance.        |

**Example:**

```php
add_action( 'leastudios_forms_mailer_integration_init', function ( $integration ): void {
    // Hook into the delivery status filter using data from the mailer integration.
    add_filter( 'leastudios_forms_delivery_status', function ( string $status, string $message_id ) use ( $integration ): string {
        $statuses = $integration->get_delivery_statuses( [ $message_id ] );

        if ( ! empty( $statuses[0]['status'] ) ) {
            return $integration::render_status_badge( $statuses[0]['status'] );
        }

        return $status;
    }, 10, 2 );
} );
```

---

## 7. Hook Execution Order

### Submission Flow

For a typical successful form submission, hooks fire in this order:

```
Browser submits form (REST or admin-post.php)
    |
    [action] leastudios_forms_before_submission    raw data available
    |
    [filter] leastudios_forms_spam_detected        honeypot result
    |         if spam → abort
    |
    Form_Repository::get_form()
    |
    Rate-limit check
    |
    Per-field-type sanitize
    |
    [filter] leastudios_forms_sanitized_data       after sanitise, before validate
    |
    Per-field-type validate
    |
    [filter] leastudios_forms_validation_errors    after validate
    |         if errors → abort with validation response
    |
    [filter] leastudios_forms_entry_data           before database write
    |
    Entry_Repository::create()
    |
    [action] leastudios_forms_submission_created   entry stored
    |
    Email_Notifier::notify() — per notification:
        [filter] leastudios_forms_notification_message
        [filter] leastudios_forms_notification_args
        wp_mail()
    |
    [action] leastudios_forms_notification_sent    after all notifications
    |
    [filter] leastudios_forms_submission_response  before client response
    |
    Return response to browser
```

| Order | Hook | Type | Trigger |
|-------|------|------|---------|
| 1 | `leastudios_forms_before_submission` | Action | Start of `handle()`, before any processing |
| 2 | `leastudios_forms_spam_detected` | Filter | After honeypot check |
| 3 | `leastudios_forms_sanitized_data` | Filter | After per-field-type sanitise, before validate |
| 4 | `leastudios_forms_validation_errors` | Filter | After per-field-type validate |
| 5 | `leastudios_forms_entry_data` | Filter | Before database write |
| 6 | `leastudios_forms_submission_created` | Action | After entry row inserted |
| 7 | `leastudios_forms_notification_message` | Filter | Per notification, after merge-tag replacement |
| 8 | `leastudios_forms_notification_args` | Filter | Per notification, before `wp_mail()` |
| 9 | `leastudios_forms_notification_sent` | Action | After all notifications dispatched |
| 10 | `leastudios_forms_submission_response` | Filter | Before response returned to client |

### Render Flow (hooks outside the submission pipeline)

The render and registration hooks fire independently of submissions:

```
WordPress renders [leastudios_form] shortcode or block
    |
    [action] leastudios_forms_before_render
    |
    Per-field: [filter] leastudios_forms_render_field
    |
    [action] leastudios_forms_after_render
    |
    [filter] leastudios_forms_shortcode_output
```

The `leastudios_forms_form_attributes` filter fires during `Form_Renderer::render()`
just before the `<form>` tag is output, between `leastudios_forms_before_render` and the
first `leastudios_forms_render_field` call.

The registration hooks (`leastudios_forms_post_type_args`, `leastudios_forms_field_types`,
`leastudios_forms_mailer_integration_init`, `leastudios_forms_address_countries`) fire
during plugin bootstrap or at the point the relevant component initialises — not during
the submission or render flow.

---

## 8. REST API Reference

Namespace: `leastudios-forms/v1`

| Method | Route | Description | Capability |
|--------|-------|-------------|------------|
| `POST` | `/submissions` | Submit a form entry | Public (nonce + IP rate-limit) |

### `POST /submissions`

- **Endpoint:** `/wp-json/leastudios-forms/v1/submissions`
- **Controller:** `src/REST/Submission_Controller.php`
- **Capability:** Public — `permission_callback` returns `true`. Authentication is via a
  per-form nonce (`leastudios_forms_submit_{form_id}`) passed as the `_wpnonce` body
  parameter. The controller returns 403 if the nonce is absent or invalid.
- **Query parameters:** none
- **Request body:**

  | Field | Type | Required | Description |
  |-------|------|----------|-------------|
  | `form_id` | `integer` | Yes | The `leastudios_form` post ID. |
  | `fields` | `object` | Yes | Field name → value map for all submitted field values. |
  | `_wpnonce` | `string` | Yes | WordPress nonce for action `leastudios_forms_submit_{form_id}`. |
  | `_leastudios_forms_hp` | `string` | No | Honeypot field value. Must be present (even if empty) to pass the spam check — bots that skip form rendering often omit it entirely. |

- **Response (200 — success):**

  ```json
  {
    "success": true,
    "message": "Thank you for your message.",
    "errors": []
  }
  ```

- **Response (422 — validation error):**

  ```json
  {
    "success": false,
    "message": "",
    "errors": {
      "email": "Please enter a valid email address."
    }
  }
  ```

- **Response (403 — nonce failure):**

  ```json
  {
    "success": false,
    "message": "Security check failed. Please refresh the page and try again.",
    "errors": []
  }
  ```

- **Response (429 — rate limited):** Standard WordPress REST error with `code` and
  `message` fields.

- **Example:**

  ```bash
  # Retrieve a nonce first (the form renderer outputs one automatically).
  NONCE=$(wp eval 'echo wp_create_nonce( "leastudios_forms_submit_42" );')

  curl -s -X POST https://leastudios-plugins.test/wp-json/leastudios-forms/v1/submissions \
    -H "Content-Type: application/json" \
    -d "{
      \"form_id\": 42,
      \"_wpnonce\": \"${NONCE}\",
      \"_leastudios_forms_hp\": \"\",
      \"fields\": {
        \"name\": \"Ada Lovelace\",
        \"email\": \"ada@example.com\",
        \"message\": \"Hello!\"
      }
    }"
  ```

---

## 9. Public PHP API

### `LEAStudios\Forms\Field\Field_Type` *(interface)*

- **File:** `src/Field/Field_Type.php`
- **Since:** 1.0.0
- **Purpose:** The contract every form field type must implement. Register custom
  implementations via the `leastudios_forms_field_types` action. The interface is the
  stable extension surface — the 11 built-in type classes in `src/Field/Types/` are
  implementation details.

**Methods:**

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_type` | `(): string` | Stable slug for this field type (e.g. `date_picker`). Used as the `type` key in field definitions and the form editor. |
| `get_label` | `(): string` | Human-readable translated label shown in the form editor's field type list. |
| `sanitize` | `( mixed $value ): mixed` | Sanitise a raw submitted value. Called for every field of this type before validation. |
| `validate` | `( mixed $value, array<string, mixed> $field_config ): true\|string` | Validate a sanitised value. Return `true` if valid, or an error message string. |
| `render` | `( array<string, mixed> $field_config, mixed $value = null ): string` | Render the field's HTML. Used in both the form frontend and, optionally, the form editor preview. |

See the [custom field type recipe](#how-do-i-register-a-custom-field-type) for a full implementation example.

---

## 10. Extension Recipes

### How do I register a custom field type?

**Goal:** Add a `date_picker` field type to the form editor, with proper sanitise,
validate, and render logic.

**Hooks used:** `leastudios_forms_field_types`.

**Walkthrough:** Implement the `LEAStudios\Forms\Field\Field_Type` interface in your own
plugin. The four required methods are `get_type()` (slug), `get_label()` (display name),
`sanitize()` (clean the raw value), `validate()` (return `true` or an error string), and
`render()` (produce the HTML input). Hook `leastudios_forms_field_types` at file scope
so your class is registered before the form editor renders its field list.

The `sanitize()` and `validate()` methods are called by `Submission_Handler::handle()`
on every submission. Keep them fast and side-effect free. The `render()` method is called
by `Form_Renderer` during page render — avoid database queries inside it.

Because `Submission_Handler` reads field type data from `Field_Registry`, your type
handles submissions automatically once registered. There is no separate registration step
for the submission pipeline.

**Complete example:**

```php
<?php
// In my-plugin/my-plugin.php or a file loaded at plugin init.

use LEAStudios\Forms\Field\Field_Type;
use LEAStudios\Forms\Field\Field_Registry;

/**
 * A custom date-picker field type.
 */
class Date_Picker_Field implements Field_Type {

    public function get_type(): string {
        return 'date_picker';
    }

    public function get_label(): string {
        return __( 'Date Picker', 'my-plugin' );
    }

    public function sanitize( mixed $value ): mixed {
        // Expect YYYY-MM-DD format.
        $value = sanitize_text_field( (string) $value );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            return $value;
        }
        return '';
    }

    public function validate( mixed $value, array $field_config ): true|string {
        $required = ! empty( $field_config['required'] );

        if ( $required && '' === $value ) {
            return __( 'This field is required.', 'my-plugin' );
        }

        if ( '' !== $value ) {
            $timestamp = strtotime( $value );
            if ( false === $timestamp ) {
                return __( 'Please enter a valid date.', 'my-plugin' );
            }

            // Optional: enforce min/max date.
            if ( ! empty( $field_config['min_date'] ) && $value < $field_config['min_date'] ) {
                return sprintf( __( 'Date must be on or after %s.', 'my-plugin' ), $field_config['min_date'] );
            }
        }

        return true;
    }

    public function render( array $field_config, mixed $value = null ): string {
        $name        = esc_attr( $field_config['name'] ?? '' );
        $label       = esc_html( $field_config['label'] ?? '' );
        $required    = ! empty( $field_config['required'] );
        $placeholder = esc_attr( $field_config['placeholder'] ?? 'YYYY-MM-DD' );
        $current     = esc_attr( (string) $value );

        return sprintf(
            '<label for="field-%1$s">%2$s</label>
            <input type="date" id="field-%1$s" name="%1$s" value="%3$s" placeholder="%4$s"%5$s class="leastudios-form-input" />',
            $name,
            $label,
            $current,
            $placeholder,
            $required ? ' required aria-required="true"' : ''
        );
    }
}

// Register the custom field type.
add_action( 'leastudios_forms_field_types', function ( Field_Registry $registry ): void {
    $registry->register( new Date_Picker_Field() );
} );
```

---

### How do I send form submissions to a CRM?

**Goal:** Push every new entry from a specific contact form to a CRM API in real time.

**Hooks used:** `leastudios_forms_submission_created`.

**Walkthrough:** The `leastudios_forms_submission_created` action fires after the entry
is safely stored in the database, so the push is guaranteed to happen at most once per
entry. Use a non-blocking `wp_remote_post()` call (set `'blocking' => false`) to avoid
delaying the response to the browser. If you need to handle failures, store a flag in
entry meta and retry on a cron event instead.

The `$sanitized_data` argument is the full field-name → value map. Keys are the `name`
values from your field definitions — check them in **Forms → Edit Form** if you are
unsure of the exact slug.

Calling `wp_remote_post()` with `'blocking' => false` means WordPress does not wait for
the response. CRM API errors will not be surfaced to the user and will not appear in the
WordPress error log. Add dedicated monitoring (e.g. log CRM failures to a custom table
or a service like Logtail) if reliability matters for your use case.

**Complete example:**

```php
add_action(
    'leastudios_forms_submission_created',
    function ( int $entry_id, int $form_id, array $sanitized_data ): void {
        // Only push entries from the contact form (ID 42).
        if ( 42 !== $form_id ) {
            return;
        }

        $email = sanitize_email( $sanitized_data['email'] ?? '' );
        if ( '' === $email ) {
            return;
        }

        wp_remote_post(
            'https://api.crm.example.com/v2/contacts',
            [
                'headers'  => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . get_option( 'my_plugin_crm_api_key' ),
                ],
                'body'     => wp_json_encode( [
                    'email'      => $email,
                    'first_name' => sanitize_text_field( $sanitized_data['first_name'] ?? '' ),
                    'last_name'  => sanitize_text_field( $sanitized_data['last_name'] ?? '' ),
                    'source'     => 'website-contact-form',
                    'entry_id'   => $entry_id,
                ] ),
                'blocking' => false,
                'timeout'  => 5,
            ]
        );
    },
    10,
    3
);
```

---

### How do I block free-email providers on a form?

**Goal:** Reject submissions where the `email` field uses a free provider (Gmail, Yahoo,
etc.), returning a field-level validation error to the browser.

**Hooks used:** `leastudios_forms_validation_errors`.

**Walkthrough:** The `leastudios_forms_validation_errors` filter fires after all built-in
per-field validation has run. The `$errors` array is keyed by field name; adding a key
causes the submission to fail and the error to appear next to the field in the form.

The filter receives the already-sanitised `$sanitized_data` array — you do not need to
sanitise values again. Only add the free-provider error when the built-in email validator
has not already flagged the field (check `! isset( $errors['email'] )`) so the user sees
one clear message at a time.

Confine this check to the specific form IDs where it applies. Applying it globally would
block legitimate personal-email submissions on forms that do not require corporate
addresses.

**Complete example:**

```php
add_filter(
    'leastudios_forms_validation_errors',
    function ( array $errors, int $form_id, array $sanitized_data ): array {
        // Only apply to the business-enquiry form (ID 42).
        if ( 42 !== $form_id ) {
            return $errors;
        }

        // Skip if the built-in validator already flagged the email field.
        if ( isset( $errors['email'] ) ) {
            return $errors;
        }

        $email = $sanitized_data['email'] ?? '';
        if ( '' === $email ) {
            return $errors;
        }

        $at_pos = strrpos( $email, '@' );
        if ( false === $at_pos ) {
            return $errors;
        }

        $domain = strtolower( substr( $email, $at_pos + 1 ) );

        $free_providers = [
            'gmail.com', 'googlemail.com',
            'yahoo.com', 'yahoo.co.uk',
            'hotmail.com', 'outlook.com', 'live.com',
            'icloud.com', 'me.com',
            'aol.com',
        ];

        if ( in_array( $domain, $free_providers, true ) ) {
            $errors['email'] = esc_html__(
                'Please use your company email address.',
                'my-plugin'
            );
        }

        return $errors;
    },
    10,
    3
);
```

---

### How do I add a CC/BCC header to notification emails?

**Goal:** BCC a second address on all notification emails sent by a specific form.

**Hooks used:** `leastudios_forms_notification_args`.

**Walkthrough:** The `leastudios_forms_notification_args` filter fires once per
notification, immediately before `wp_mail()` is called. The `$args` array has four keys:
`to`, `subject`, `message`, and `headers` (an array of RFC 2822 header strings). Append
a `Cc:` or `Bcc:` header to the `headers` array to add recipients.

The filter receives `$entry_id` as well as `$form_id`, so you can vary the CC address
per form or even look up a dynamic address from a form field value in `$field_data`.

Standard WordPress hosting allows `Reply-To:`, `Cc:`, and `Bcc:` headers, but some
managed hosts strip `Bcc` silently. Test with your hosting provider. When using
`leastudios-mailer` (Amazon SES transport), all standard RFC 2822 headers are supported.

**Complete example:**

```php
add_filter(
    'leastudios_forms_notification_args',
    function ( array $args, int $form_id, int $entry_id, array $field_data ): array {
        // BCC the sales team on every notification from form #42.
        if ( 42 === $form_id ) {
            $args['headers'][] = 'Bcc: sales-team@example.com';
        }

        // CC the submitter's manager if provided in a hidden field.
        $manager_email = sanitize_email( $field_data['manager_email'] ?? '' );
        if ( '' !== $manager_email && is_email( $manager_email ) ) {
            $args['headers'][] = 'Cc: ' . $manager_email;
        }

        // Add a Reply-To pointing back at the submitter.
        $submitter_email = sanitize_email( $field_data['email'] ?? '' );
        if ( '' !== $submitter_email && is_email( $submitter_email ) ) {
            $args['headers'][] = 'Reply-To: ' . $submitter_email;
        }

        return $args;
    },
    10,
    4
);
```

---

### How do I add a custom entry-detail action button?

**Goal:** Add a "Resend Notification" button to the entry detail page in the admin.

**Hooks used:** `leastudios_forms_entry_actions`.

**Walkthrough:** The `leastudios_forms_entry_actions` filter fires when the entry detail
page builds its action list. Each action is an associative array with at minimum `url`,
`label`, and `class` keys. Add your action to the `$actions` array under a unique slug.

The `url` should be a nonce-signed admin URL so that the subsequent handler can verify
the request. Build the URL with `wp_nonce_url()` and verify it in your handler with
`wp_verify_nonce()` before taking any privileged action.

The `$entry` object carries all columns from the entries table as properties: `$entry->id`,
`$entry->form_id`, `$entry->field_data` (raw JSON string), `$entry->status`,
`$entry->created_at`, and `$entry->notification_message_ids` (raw JSON string).

**Complete example:**

```php
// 1. Add the action button.
add_filter(
    'leastudios_forms_entry_actions',
    function ( array $actions, object $entry ): array {
        $actions['resend'] = [
            'url'   => wp_nonce_url(
                add_query_arg(
                    [
                        'page'     => 'leastudios-forms-entries',
                        'action'   => 'resend',
                        'entry_id' => absint( $entry->id ),
                    ],
                    admin_url( 'admin.php' )
                ),
                'my_plugin_resend_' . absint( $entry->id )
            ),
            'label' => __( 'Resend Notification', 'my-plugin' ),
            'class' => 'button',
        ];

        return $actions;
    },
    10,
    2
);

// 2. Handle the action when the button is clicked.
add_action( 'admin_init', function (): void {
    if ( ! isset( $_GET['action'], $_GET['entry_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    $action   = sanitize_key( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $entry_id = absint( wp_unslash( $_GET['entry_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( 'resend' !== $action || $entry_id <= 0 ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to perform this action.', 'my-plugin' ) );
    }

    check_admin_referer( 'my_plugin_resend_' . $entry_id );

    // Perform your resend logic here.
    // e.g., load the entry, rebuild the notification, call wp_mail().
    do_action( 'my_plugin_resend_entry_notification', $entry_id );

    wp_safe_redirect( add_query_arg( [
        'page'    => 'leastudios-forms-entries',
        'resent'  => '1',
    ], admin_url( 'admin.php' ) ) );
    exit;
} );
```

---

## 11. Testing

```bash
cd wp-content/plugins/leastudios-forms
composer test                                         # run the full suite
vendor/bin/phpunit --filter FieldRegistryTest         # one class
vendor/bin/phpunit tests/SubmissionHandlerTest.php    # one file
```

The suite uses PHPUnit 9.6 against the WordPress test library (`/tmp/wordpress-tests-lib`).
Install it once with:

```bash
bash ../leastudios-dev-tools/bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest
```

**Writing tests for an extension that loads this plugin:**

1. Ensure `leastudios-forms` is active in the test environment (add it to the test
   bootstrap or activate it via WP-CLI in your test setup script).
2. `Field_Registry` is populated during `Plugin::init()` (which runs on `plugins_loaded`
   at priority 10). To test a custom field type, hook
   `leastudios_forms_field_types` in your `setUp()` before triggering `plugins_loaded`.
3. To exercise the submission pipeline, call `Submission_Handler::handle()` directly in
   tests — inject a mock `Form_Repository` and `Entry_Repository` rather than hitting the
   database if you only want to assert hook firing order.
4. To test validation errors, hook `leastudios_forms_validation_errors` in your test,
   call `handle()`, and assert the response array contains the expected `errors` key.

---

## 12. Release Process

This plugin uses a tag-triggered release workflow (`.github/workflows/release.yml`)
that auto-generates release notes from the commit log between the previous and
current tag.

**To cut a release:** bump the `Version:` header in `leastudios-forms.php`, commit, then:

```bash
git tag v<X.Y.Z> && git push origin v<X.Y.Z>
```

**Commit-prefix → release-notes section:**

- `feat:` → `## Added`
- `fix:` → `## Fixed`
- `refactor:` → `## Changed`
- `perf:` → `## Performance`

**Hidden from release notes:** `ci:`, `chore:`, `docs:`, `test:`, `style:`, `build:`, `release:`.

---

## 13. Where to Read More

- [`CLAUDE.md`](../CLAUDE.md) — this plugin's repo conventions, architecture notes, and submission-pipeline gotchas.
- [`README.md`](../README.md) — user-facing overview, feature list, and shortcode reference.
- [`leastudios-dev-tools/CLAUDE.md`](../../leastudios-dev-tools/CLAUDE.md) — suite-wide coding standards, security checklist (escape / sanitize / nonce / capability), REST and i18n conventions inherited by every plugin.
- [`leastudios-mailer — Developer Handbook`](../../leastudios-mailer/docs/developer-handbook.md) — the Amazon SES transport layer that provides delivery-status tracking for form notification emails.
- [`leastudios-email-templates — Developer Handbook`](../../leastudios-email-templates/docs/developer-handbook.md) — the branded email wrapper that wraps form notification emails in the site's template.
