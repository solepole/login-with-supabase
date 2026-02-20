# Sensei LMS Integration Guide

## Overview

The "Login with Supabase" plugin now includes built-in support for Sensei LMS login and register forms. This allows users to authenticate via Supabase OAuth providers (Google, Azure, GitHub, etc.) directly from the Sensei `/my-courses` page.

> **Note:** The plugin also offers similar integration for WooCommerce forms. If you use WooCommerce, you can enable OAuth buttons on the `/my-account` login and register forms using the "Add buttons to WooCommerce forms" option in plugin settings.

## How It Works

### Integration Points

The plugin hooks into Sensei's action hooks to inject the Supabase login buttons:

1. **Login Form**: Uses `sensei_login_form_inside_after` action
   - Appears at the bottom of the Sensei login form
   - Shows all enabled OAuth provider buttons

2. **Register Form**: Uses `sensei_register_form_fields` action
   - Appears at the bottom of the Sensei registration form
   - Shows "Or sign up with:" text followed by OAuth buttons

### Technical Implementation

#### Files Modified

1. **`includes/class-lws-admin.php`**
   - Added `show_on_sensei_forms` option to default settings
   - Added new settings field with checkbox
   - Updated sanitization to handle the new option

2. **`includes/class-lws-frontend.php`**
   - Added hooks for Sensei forms in `init()` method
   - New method: `render_login_buttons_on_sensei_login()` 
   - New method: `render_login_buttons_on_sensei_register()`
   - Both methods render the button shortcode with appropriate styling

3. **`assets/css/front.css`**
   - Added `.lws-sensei-login-buttons` and `.lws-sensei-register-buttons` styles
   - Full-width buttons for better mobile experience
   - Consistent separator styling with border-top

4. **`login-with-supabase.php`**
   - Updated version to 0.1.4

## Setup Instructions

### Step 1: Enable the Feature

1. Go to **WordPress Admin → Settings → Login with Supabase**
2. Scroll to the "Behavior" section
3. Check the box for **"Add buttons to Sensei LMS forms"**
4. Click "Save Changes"

### Step 2: Verify Prerequisites

Make sure you have:
- Sensei LMS plugin installed and activated
- Supabase credentials configured
- At least one OAuth provider enabled

### Step 3: Test the Integration

1. Log out of WordPress
2. Navigate to `/my-courses` (or your Sensei courses page)
3. You should see the Supabase login buttons at the bottom of both:
   - Login form (left column)
   - Register form (right column)

## User Experience

### Login Flow

1. User visits `/my-courses` while not logged in
2. Sensei displays the login/register forms
3. User sees OAuth buttons (e.g., "Login with Google", "Login with Azure")
4. User clicks a button
5. Redirected to OAuth provider for authentication
6. Returns to WordPress and is automatically logged in
7. Redirected to the configured post-login URL (or `/my-courses`)

### Registration Flow

1. User visits the registration form on `/my-courses`
2. Sees "Or sign up with:" followed by OAuth buttons
3. Clicks an OAuth provider
4. Authenticates with provider
5. New WordPress account created automatically
6. Logged in and redirected to configured URL

## Styling

The integration includes responsive styling:

```css
/* Separator above buttons */
.lws-sensei-login-buttons,
.lws-sensei-register-buttons {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

/* Full-width buttons on Sensei forms */
.sensei .lws-login-wrapper--sensei-login .lws-button,
.sensei .lws-login-wrapper--sensei-register .lws-button {
    width: 100%;
}
```

## Customization

### Change Button Text

Edit the translation in your theme's `functions.php`:

```php
add_filter('gettext', function($translated, $text, $domain) {
    if ($domain === 'login-with-supabase' && $text === 'Or sign up with:') {
        return 'Continue with:';
    }
    return $translated;
}, 10, 3);
```

### Custom Styling

Add your own CSS to override the default styles:

```css
/* Change separator color */
.lws-sensei-login-buttons {
    border-top-color: #your-color !important;
}

/* Adjust button spacing */
.lws-sensei-register-buttons {
    margin-top: 30px !important;
    padding-top: 30px !important;
}
```

### Disable for Specific Pages

Use the action priority to remove the integration:

```php
// In your theme's functions.php
add_action('init', function() {
    if (is_page('my-courses')) {
        remove_action('sensei_login_form_inside_after', [LWS_Frontend::class, 'render_login_buttons_on_sensei_login']);
    }
}, 11);
```

## Hooks Used

The plugin integrates with these Sensei/WooCommerce hooks:

| Hook | Priority | Purpose |
|------|----------|---------|
| `sensei_login_form_inside_after` | 10 | Inject buttons after login form fields |
| `sensei_register_form_fields` | 10 | Inject buttons in registration form |
| `woocommerce_login_form` | 10 | Inject buttons in login form form |
| `woocommerce_register_form` | 10 | Inject buttons in registration form |

## Troubleshooting

### Buttons Don't Appear

1. **Check if Sensei is active**: The integration only works with Sensei LMS installed
2. **Verify option is enabled**: Go to Settings → Login with Supabase
3. **Clear cache**: If using caching plugins, clear all caches
4. **Check provider configuration**: Ensure at least one OAuth provider is enabled

> **WooCommerce Users:** If buttons don't appear on WooCommerce forms, ensure the "Add buttons to WooCommerce forms" option is enabled separately.

### Styling Issues

1. **Theme conflicts**: Your theme may have conflicting CSS
2. **Add specificity**: Use `!important` or more specific selectors
3. **Check browser console**: Look for CSS loading errors

### Authentication Fails

1. **Supabase configuration**: Verify your Supabase URL and keys
2. **Redirect URLs**: Add your site URL to Supabase allowed redirects
3. **Provider setup**: Ensure OAuth providers are configured in Supabase dashboard

## Support

For issues specific to Sensei integration:

1. Enable WordPress debug mode
2. Check browser console for JavaScript errors
3. Verify Sensei templates aren't overridden in your theme
4. Test with default WordPress theme to rule out theme conflicts

## Future Enhancements

Potential improvements for future versions:

- [ ] Custom button positioning options
- [ ] Hide/show buttons on specific forms only
- [ ] Integration with Sensei Pro features
- [ ] Custom styling options in admin panel
- [ ] Support for other LMS plugins

## Code Reference

### Action Hook Registration

```php
// In includes/class-lws-frontend.php
if ((int) LWS_Admin::get_option('show_on_sensei_forms', 0) === 1) {
    add_action('sensei_login_form_inside_after', array($this, 'render_login_buttons_on_sensei_login'), 10);
    add_action('sensei_register_form_fields', array($this, 'render_login_buttons_on_sensei_register'), 10);
}
```

### Button Rendering

```php
public function render_login_buttons_on_sensei_login() {
    if (is_user_logged_in()) {
        return;
    }

    $this->enqueue_assets();
    $markup = $this->render_login_shortcode(array(
        'wrapper_class' => 'lws-login-wrapper--sensei-login',
        'layout' => 'vertical'
    ));

    echo '<div class="lws-sensei-login-buttons" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">';
    echo $markup;
    echo '</div>';

    $this->render_login_asset_bootstrap();
}
```

## Conclusion

The Sensei LMS integration provides a seamless way to offer OAuth authentication on your LMS platform. Users can now sign in or register using their preferred OAuth providers directly from the `/my-courses` page, improving the user experience and reducing friction in the registration process.
