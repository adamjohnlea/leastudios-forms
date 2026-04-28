# LEA Studios Forms - Developer Handbook

This document provides a complete reference for all WordPress hooks (actions and filters) available in the LEA Studios Forms plugin. Use these hooks to extend, customise, or integrate with the forms plugin from your own themes or plugins.

---

## Table of Contents

1. [Render Hooks](#render-hooks)
   - [leastudios_forms_before_render](#leastudios_forms_before_render)
   - [leastudios_forms_after_render](#leastudios_forms_after_render)
   - [leastudios_forms_render_field](#leastudios_forms_render_field)
   - [leastudios_forms_form_attributes](#leastudios_forms_form_attributes)
   - [leastudios_forms_shortcode_output](#leastudios_forms_shortcode_output)
2. [Submission Hooks](#submission-hooks)
   - [leastudios_forms_before_submission](#leastudios_forms_before_submission)
   - [leastudios_forms_spam_detected](#leastudios_forms_spam_detected)
   - [leastudios_forms_sanitized_data](#leastudios_forms_sanitized_data)
   - [leastudios_forms_validation_errors](#leastudios_forms_validation_errors)
   - [leastudios_forms_entry_data](#leastudios_forms_entry_data)
   - [leastudios_forms_submission_created](#leastudios_forms_submission_created)
   - [leastudios_forms_submission_response](#leastudios_forms_submission_response)
3. [Notification Hooks](#notification-hooks)
   - [leastudios_forms_notification_message](#leastudios_forms_notification_message)
   - [leastudios_forms_notification_args](#leastudios_forms_notification_args)
   - [leastudios_forms_notification_sent](#leastudios_forms_notification_sent)
4. [Admin Hooks](#admin-hooks)
   - [leastudios_forms_entry_actions](#leastudios_forms_entry_actions)
   - [leastudios_forms_delivery_status](#leastudios_forms_delivery_status)
5. [Registration Hooks](#registration-hooks)
   - [leastudios_forms_post_type_args](#leastudios_forms_post_type_args)
   - [leastudios_forms_field_types](#leastudios_forms_field_types)
   - [leastudios_forms_mailer_integration_init](#leastudios_forms_mailer_integration_init)

---

## Render Hooks

### `leastudios_forms_before_render`

- **Type:** Action
- **Location:** `src/Render/Form_Renderer.php`
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

### `leastudios_forms_after_render`

- **Type:** Action
- **Location:** `src/Render/Form_Renderer.php`
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

### `leastudios_forms_render_field`

- **Type:** Filter
- **Location:** `src/Render/Form_Renderer.php`
- **Description:** Filters the HTML output for each individual field. Use this to modify field markup, add wrapper elements, or inject custom attributes.

**Parameters:**

| Parameter       | Type    | Description                    |
|-----------------|---------|--------------------------------|
| `$html`         | `string`| The rendered field HTML.       |
| `$field_config` | `array` | The field configuration array. |
| `$value`        | `mixed` | The current field value.       |
| `$form_id`      | `int`   | The form post ID.              |

**Returns:** `string` -- The filtered field HTML.

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

### `leastudios_forms_form_attributes`

- **Type:** Filter
- **Location:** `src/Render/Form_Renderer.php`
- **Description:** Filters the HTML attributes array for the `<form>` tag. Use this to add custom classes, data attributes, or change the form method.

**Parameters:**

| Parameter     | Type    | Description                                      |
|---------------|---------|--------------------------------------------------|
| `$attributes` | `array` | Key-value pairs of form tag attributes.           |
| `$form_id`    | `int`   | The form post ID.                                |

**Returns:** `array` -- The filtered attributes array.

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

### `leastudios_forms_shortcode_output`

- **Type:** Filter
- **Location:** `src/Render/Shortcode.php`
- **Description:** Filters the final HTML output of the `[leastudios_form]` shortcode. Use this to wrap the form in additional markup or modify the output after rendering.

**Parameters:**

| Parameter  | Type     | Description              |
|------------|----------|--------------------------|
| `$html`    | `string` | The rendered form HTML.  |
| `$form_id` | `int`    | The form post ID.        |

**Returns:** `string` -- The filtered shortcode HTML.

**Example:**

```php
add_filter( 'leastudios_forms_shortcode_output', function ( string $html, int $form_id ): string {
    // Wrap every form in a card container.
    return '<div class="form-card">' . $html . '</div>';
}, 10, 2 );
```

---

## Submission Hooks

### `leastudios_forms_before_submission`

- **Type:** Action
- **Location:** `src/Submission/Submission_Handler.php`
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

### `leastudios_forms_spam_detected`

- **Type:** Filter
- **Location:** `src/Submission/Submission_Handler.php`
- **Description:** Filters the spam detection result. The default value comes from the honeypot check. Use this to integrate additional spam detection services (reCAPTCHA, Akismet, etc.) or override the built-in check.

**Parameters:**

| Parameter   | Type    | Description                               |
|-------------|---------|-------------------------------------------|
| `$is_spam`  | `bool`  | Whether the submission is detected as spam.|
| `$form_id`  | `int`   | The form post ID.                         |
| `$raw_data` | `array` | The raw submitted data.                   |

**Returns:** `bool` -- Whether the submission should be treated as spam.

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

### `leastudios_forms_sanitized_data`

- **Type:** Filter
- **Location:** `src/Submission/Submission_Handler.php`
- **Description:** Filters the sanitized submission data before validation runs. Use this to modify, augment, or normalise field values after the built-in sanitisation but before validation.

**Parameters:**

| Parameter        | Type    | Description                           |
|------------------|---------|---------------------------------------|
| `$sanitized_data`| `array` | The sanitized submitted data.         |
| `$form_id`       | `int`   | The form post ID.                     |
| `$fields_config` | `array` | The form fields configuration array.  |

**Returns:** `array` -- The filtered sanitized data.

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

### `leastudios_forms_validation_errors`

- **Type:** Filter
- **Location:** `src/Submission/Submission_Handler.php`
- **Description:** Filters the validation errors array after the built-in validator has run. Use this to add custom validation rules, remove errors, or modify error messages.

**Parameters:**

| Parameter        | Type    | Description                               |
|------------------|---------|-------------------------------------------|
| `$errors`        | `array` | Validation errors keyed by field name.    |
| `$form_id`       | `int`   | The form post ID.                         |
| `$sanitized_data`| `array` | The sanitized submitted data.             |

**Returns:** `array` -- The filtered errors array.

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

### `leastudios_forms_entry_data`

- **Type:** Filter
- **Location:** `src/Submission/Submission_Handler.php`
- **Description:** Filters the data array immediately before it is stored in the database. Use this to add computed fields, strip sensitive data, or transform values for storage.

**Parameters:**

| Parameter  | Type    | Description               |
|------------|---------|---------------------------|
| `$data`    | `array` | The data to be stored.    |
| `$form_id` | `int`   | The form post ID.         |

**Returns:** `array` -- The filtered data array.

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

### `leastudios_forms_submission_created`

- **Type:** Action
- **Location:** `src/Submission/Submission_Handler.php`
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

### `leastudios_forms_submission_response`

- **Type:** Filter
- **Location:** `src/Submission/Submission_Handler.php`
- **Description:** Filters the response array before it is returned to the client on a successful submission. Use this to add extra data to the response (e.g., redirect URLs, tracking IDs) or customise the success message.

**Parameters:**

| Parameter   | Type    | Description                                         |
|-------------|---------|-----------------------------------------------------|
| `$response` | `array` | Array with `success`, `message`, and `errors` keys. |
| `$form_id`  | `int`   | The form post ID.                                   |
| `$entry_id` | `int`   | The entry ID.                                       |

**Returns:** `array` -- The filtered response array.

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

## Notification Hooks

### `leastudios_forms_notification_message`

- **Type:** Filter
- **Location:** `src/Notification/Email_Notifier.php`
- **Description:** Filters the notification email message body after all merge tags have been replaced. Use this to append footers, transform formatting, or inject dynamic content.

**Parameters:**

| Parameter    | Type     | Description                    |
|--------------|----------|--------------------------------|
| `$message`   | `string` | The notification message body. |
| `$form_id`   | `int`    | The form post ID.              |
| `$field_data` | `array` | The submitted field data.      |

**Returns:** `string` -- The filtered message.

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

### `leastudios_forms_notification_args`

- **Type:** Filter
- **Location:** `src/Notification/Email_Notifier.php`
- **Description:** Filters each notification's email arguments immediately before `wp_mail()` is called. Use this to modify recipients, add CC/BCC headers, change the subject line, or alter the message.

**Parameters:**

| Parameter    | Type    | Description                                          |
|--------------|---------|------------------------------------------------------|
| `$args`      | `array` | Array with `to`, `subject`, `message`, and `headers` keys. |
| `$form_id`   | `int`   | The form post ID.                                    |
| `$entry_id`  | `int`   | The entry ID.                                        |
| `$field_data`| `array` | The submitted field data.                            |

**Returns:** `array` -- The filtered email args array.

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

### `leastudios_forms_notification_sent`

- **Type:** Action
- **Location:** `src/Submission/Submission_Handler.php`
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

## Admin Hooks

### `leastudios_forms_entry_actions`

- **Type:** Filter
- **Location:** `src/Admin/Entries_Page.php`
- **Description:** Filters the available actions on the entry detail page. Each action is an associative array with `url`, `label`, `class`, and optional `onclick` keys. Use this to add custom actions (e.g., resend notification, export PDF) or remove default ones.

**Parameters:**

| Parameter  | Type     | Description                                                        |
|------------|----------|--------------------------------------------------------------------|
| `$actions` | `array`  | Actions keyed by slug. Each value has `url`, `label`, `class`, and optional `onclick`. |
| `$entry`   | `object` | The entry database row object.                                     |

**Returns:** `array` -- The filtered actions array.

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

### `leastudios_forms_delivery_status`

- **Type:** Filter
- **Location:** `src/Admin/Entries_Page.php`
- **Description:** Filters the delivery status display string for a notification message ID. This is used in the entry detail view's "Notification Delivery Status" section. The Mailer Integration plugin hooks into this to provide real-time delivery statuses.

**Parameters:**

| Parameter     | Type     | Description                       |
|---------------|----------|-----------------------------------|
| `$status`     | `string` | The status display string.        |
| `$message_id` | `string` | The notification message ID.      |

**Returns:** `string` -- The filtered status string.

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

## Registration Hooks

### `leastudios_forms_post_type_args`

- **Type:** Filter
- **Location:** `src/CPT/Form_Post_Type.php`
- **Description:** Filters the arguments passed to `register_post_type()` for the `leastudios_form` custom post type. Use this to modify labels, capabilities, visibility, REST API support, or any other post type setting.

**Parameters:**

| Parameter | Type    | Description                              |
|-----------|---------|------------------------------------------|
| `$args`   | `array` | The post type registration arguments.    |

**Returns:** `array` -- The filtered arguments array.

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

### `leastudios_forms_field_types`

- **Type:** Action
- **Location:** `src/Field/Field_Registry.php`
- **Description:** Fires after the default field types are registered, allowing plugins or themes to register custom field types. The callback receives the `Field_Registry` instance and should call `$registry->register()` with an object implementing the `Field_Type` interface.

**Parameters:**

| Parameter   | Type                                      | Description                     |
|-------------|-------------------------------------------|---------------------------------|
| `$registry` | `LEAStudios\Forms\Field\Field_Registry`   | The field registry instance.    |

**Example -- registering a custom field type:**

```php
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

### `leastudios_forms_mailer_integration_init`

- **Type:** Action
- **Location:** `src/Integration/Mailer_Integration.php`
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

## Hook Execution Order (Submission Flow)

For reference, here is the order in which hooks fire during a typical successful form submission:

1. `leastudios_forms_before_submission` -- raw data available
2. `leastudios_forms_spam_detected` -- spam check result
3. `leastudios_forms_sanitized_data` -- after sanitisation, before validation
4. `leastudios_forms_validation_errors` -- after validation
5. `leastudios_forms_entry_data` -- before database storage
6. `leastudios_forms_submission_created` -- after entry stored
7. `leastudios_forms_notification_message` -- per notification, after merge tags
8. `leastudios_forms_notification_args` -- per notification, before wp_mail()
9. `leastudios_forms_notification_sent` -- after all notifications sent
10. `leastudios_forms_submission_response` -- before response returned to client
