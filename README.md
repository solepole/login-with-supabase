# Login with Supabase

Authenticate WordPress users through any Supabase OAuth provider and sync them into WordPress.

## Features

- Support for multiple OAuth providers (Google, GitHub, Azure, GitLab, Facebook, Apple, etc.)
- Automatic user creation and synchronization
- Customizable login button placement
- Full button shortcode and compact icon shortcode support
- Icon shortcode works in navigation menus (FSE Site Editor and Classic Menus)
- Secure REST API authentication
- Integration with Sensei LMS login/register forms
- Integration with WooCommerce login/register forms

## Installation

### Standard WordPress Installation

1. Download or clone this plugin
2. Upload to `/wp-content/plugins/login-with-supabase/`
3. Activate through the WordPress admin panel
4. Configure your Supabase credentials in Settings > Login with Supabase

### WordPress.com Installation (Business Plan or Higher)

WordPress.com has stricter security policies. Follow these steps:

1. **Zip the plugin folder** before uploading
   ```bash
   cd wp-content/plugins
   zip -r login-with-supabase.zip login-with-supabase/
   ```

2. **Upload via WordPress.com admin**:
   - Go to My Sites > Plugins > Upload Plugin
   - Choose the `login-with-supabase.zip` file
   - Click "Install Now"
   - Activate the plugin

3. **Configure the plugin**:
   - Go to Settings > Login with Supabase
   - Enter your Supabase Project URL
   - Enter your Supabase Anon/Public Key
   - Select which OAuth providers to enable
   - Save settings

4. **Clear WordPress.com cache** (important!):
   - Go to My Sites > Manage > Settings
   - Scroll to Performance section
   - Click "Clear Cache"
   
   Or use the WP Super Cache plugin to clear cache if installed.

## Configuration

1. **Supabase Setup**:
   - Create a project at [supabase.com](https://supabase.com)
   - Go to Authentication > Providers
   - Enable and configure your desired OAuth providers
   - Note your Project URL and anon/public key from Settings > API

2. **WordPress Setup**:
   - Navigate to Settings > Login with Supabase
   - Enter your Supabase Project URL (e.g., `https://xxxxx.supabase.co`)
   - Enter your Supabase Anon Key
   - Select which providers to enable
   - Optionally set a custom redirect URL after login
   - Choose whether to show login buttons on the default WordPress login page
   - Choose whether to show login buttons on Sensei LMS forms (requires Sensei LMS plugin)
   - Choose whether to show login buttons on WooCommerce forms (requires WooCommerce plugin)

## Usage

### Automatic Display

By default, the login buttons will appear on the `/wp-login.php` page automatically.

### Sensei LMS Integration

If you have Sensei LMS plugin installed, you can enable the "Add buttons to Sensei LMS forms" option in plugin settings. This will automatically display the Supabase login buttons on:

- Sensei login form (typically at `/my-courses` when not logged in)
- Sensei register form

The buttons will appear at the bottom of both forms with a separator line, maintaining a clean integration with Sensei's native forms.

### WooCommerce Integration

If you have WooCommerce plugin installed, you can enable the "Add buttons to WooCommerce forms" option in plugin settings. This will automatically display the Supabase login buttons on:

- WooCommerce login form (typically at `/my-account` when not logged in)
- WooCommerce register form

The buttons will appear at the bottom of both forms with a separator line, maintaining a clean integration with WooCommerce's native forms.

### Shortcode

Use the shortcode to display login buttons anywhere:

```
[login_with_supabase]
```

**Shortcode Attributes**:
- `layout`: `vertical` (default) or `horizontal`
- `wrapper_class`: Add custom CSS classes

Example:
```
[login_with_supabase layout="horizontal" wrapper_class="my-custom-class"]
```

### Icon Shortcode (New!)

Display individual provider icons for a more compact login option:

```
[lws provider="azure"]
```

**Icon Shortcode Attributes**:
- `provider`: (required) The OAuth provider name - `azure`, `google`, `github`, `gitlab`, `facebook`, `apple`, etc.
- `size`: (optional) Icon size - `small`, `medium` (default), or `large`
- `title`: (optional) Custom tooltip text on hover

**Examples**:
```
[lws provider="azure"]
[lws provider="google" size="large"]
[lws provider="github" size="small" title="Sign in with GitHub"]
```

**Multiple Icons in a Row**:
```
[lws provider="google"] [lws provider="azure"] [lws provider="github"]
```

**In Navigation Menus**:
The icon shortcode works perfectly in WordPress navigation menus:
1. Go to **Appearance → Editor → Navigation** (or **Appearance → Menus** for classic themes)
2. Add a **Navigation Link**
3. Set the **Label** to: `[lws provider="azure"]`
4. Set the **URL** to: `#` (or any value)
5. Save your navigation

The shortcode will render as a clickable icon that triggers authentication!

**Supported Providers**: azure, google, github, gitlab, facebook, apple, etc

## Troubleshooting

### Issue: Buttons show but icons are missing

**Solution**: This is usually a CSS caching issue on WordPress.com.

1. Clear your WordPress.com cache (see installation steps above)
2. Hard refresh your browser (Ctrl+Shift+R or Cmd+Shift+R)
3. Check browser console for errors by adding `?lws_debug=1` to the URL

### Issue: Buttons are not clickable

**Causes**:
- JavaScript not loading properly
- Script conflicts with other plugins
- WordPress.com security restrictions blocking scripts

**Solutions**:

1. **Enable Debug Mode**:
   Add `?lws_debug=1` to your login URL and check browser console:
   ```
   https://yoursite.com/wp-login.php?lws_debug=1
   ```
   
   Look for messages starting with `[LWS]` to see what's happening.

2. **Check for JavaScript errors**:
   - Open browser Developer Tools (F12)
   - Go to Console tab
   - Look for any red error messages
   - Share these errors if you need help

3. **Verify script loading**:
   - In Developer Tools, go to Network tab
   - Refresh the page
   - Look for:
     - `supabase.min.js` (should load from CDN)
     - `front.js` (your plugin script)
     - `front.css` (your plugin styles)
   - If any show red/failed status, there's a loading issue

4. **Disable other plugins temporarily**:
   - Deactivate other plugins one by one
   - Test if the login button works after each deactivation
   - This helps identify plugin conflicts

5. **Check Supabase Configuration**:
   - Verify your Supabase URL is correct (should be `https://xxxxx.supabase.co`)
   - Verify your anon key is correct
   - Check that OAuth providers are properly configured in Supabase dashboard
   - Make sure your site URL is added to Supabase's allowed redirect URLs

### Issue: Authentication succeeds but doesn't redirect

**Solution**: 
1. Check WordPress REST API is accessible at `/wp-json/login-with-supabase/v1/session`
2. Verify the redirect URL in plugin settings
3. Check for PHP errors in WordPress error logs

### Issue: Users created but missing profile information

**Solution**: Different OAuth providers return different user metadata. The plugin tries to extract:
- Full name from various fields
- First/last name
- Email (always required)

If profile info is missing, the provider may not be sharing it. Check provider settings in Supabase.

## WordPress.com Specific Notes

### Why doesn't it work on WordPress.com?

WordPress.com (especially managed hosting) has several restrictions that can affect plugin functionality:

1. **Script Loading**: WordPress.com may modify how scripts are loaded for performance
2. **Caching**: Aggressive caching can serve old CSS/JS files
3. **Security**: Some inline scripts may be blocked or modified
4. **CDN**: External resources might be proxied or blocked

### Fixes Applied (v0.1.1)

This plugin has been updated specifically for WordPress.com compatibility:

1. **Improved script registration**: Uses proper WordPress enqueue system with fallbacks
2. **Better dependency management**: Ensures Supabase library loads before plugin scripts
3. **Multiple loading strategies**: Primary enqueue + inline fallback + delayed retry
4. **Enhanced CSS specificity**: Uses `!important` to override theme conflicts
5. **Debug logging**: Add `?lws_debug=1` to see what's happening

### Recommended WordPress.com Settings

1. **Disable Jetpack's "Speed up image load times"** temporarily during testing
2. **Clear cache** after any plugin updates
3. **Use a simple theme** during initial testing to rule out theme conflicts

## Development

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- Active Supabase project with OAuth providers configured

### File Structure
```
login-with-supabase/
├── login-with-supabase.php    # Main plugin file
├── README.md                   # This file
├── includes/
│   ├── class-lws-admin.php    # Admin settings interface
│   └── class-lws-frontend.php # Frontend functionality
└── assets/
    ├── css/
    │   └── front.css          # Button and UI styles
    └── js/
        └── front.js           # OAuth and UI logic
```

### Hooks

**Actions**:
- `lws_user_authenticated` - Fired after user authenticates (passes user ID and Supabase user data)
- `lws_new_user_created` - Fired when a new user is created (passes user ID and Supabase user data)

**Filters**: None currently, but let us know if you need any!

## Security

- Uses WordPress nonces for REST API requests
- Validates OAuth tokens server-side
- Sanitizes all user input
- Uses WordPress's secure password generation
- No passwords stored for OAuth users (random, secure passwords assigned)

## Support

If you encounter issues:

1. Enable debug mode: `?lws_debug=1`
2. Check browser console for `[LWS]` messages
3. Check WordPress debug log for PHP errors
4. Verify Supabase configuration
5. Test with default WordPress theme and no other plugins

## Changelog

### 1.0.0 (2026-02-19)
- **Fixed**: Re-login issue after logout in the same browser session
- Session tracking keys are now properly cleared on logout
- Added cleanup of stale sync tracking keys on script initialization
- Improved session management to prevent duplicate login attempts
- Enhanced reliability for users who log out and immediately log back in

### 0.1.6 (2026-02-08)
- Added icon shortcode `[lws provider="azure"]` for compact login buttons
- Support for 12+ provider icons with proper branding
- Icon shortcode works in WordPress navigation menus (FSE and Classic)
- Transparent background with subtle hover effects for icons
- Three size options: small, medium, large
- Custom tooltip support for accessibility
- Automatic shortcode processing in navigation blocks
- Fixed duplicate icon rendering issue
- Added custom nav menu meta box for easy icon insertion

### 0.1.5 (2026-02-08)
- Added WooCommerce integration support
- New option to display login buttons on WooCommerce login and register forms
- Buttons appear on `/my-account` and other WooCommerce account pages
- Full-width buttons on WooCommerce forms for better mobile experience
- Updated debug shortcode to show WooCommerce status

### 0.1.4 (2026-02-08)
- Added Sensei LMS integration support
- New option to display login buttons on Sensei login and register forms
- Automatic detection and integration with Sensei LMS
- Improved styling for Sensei forms integration
- Full-width buttons on Sensei forms for better mobile experience

### 0.1.1 (2026-01-30)
- Fixed script loading issues on WordPress.com hosting
- Improved icon injection reliability  
- Added fallback loading mechanisms
- Enhanced CSS specificity to prevent theme conflicts
- Added comprehensive debug logging
- Better error handling and retry logic
- Multiple initialization strategies for reliability

### 0.1.0
- Initial release
- Support for multiple OAuth providers
- Automatic user creation and sync
- Shortcode support
- Admin configuration interface

## License

MIT License - Feel free to use and modify as needed.

## Credits

Built by Zhe Xu
Powered by [Supabase](https://supabase.com)

A provider-agnostic WordPress plugin that authenticates users against Supabase Auth and provisions local WordPress accounts on first login.

## Setup

1. Activate **Login with Supabase** from Plugins.
2. Navigate to **Settings → Login with Supabase** and configure:
   - **Supabase Project URL** (e.g. `https://project.supabase.co`).
   - **Supabase Anon Key** (required).
   - **Supabase Service Role Key** (optional, only needed for advanced provider checks).
   - **Post-login Redirect URL** to control where users land after sign-in.
   - **Enabled OAuth Providers**: list each Supabase provider slug on its own line (examples: `azure`, `google`, `github`, `gitlab`, `facebook`, `apple`). Buttons render in the order entered.
   - Toggle whether to display buttons on the core `wp-login.php` form.
3. In Supabase, enable each provider you listed and register the callback URL that points to the WordPress page containing the login shortcode (for example, `https://domain.com/login/`).

## Usage

- Embed Supabase login buttons anywhere with either shortcode:

  ```
  [login_with_supabase]
  ```

  ```
  [supabase_login layout="horizontal"]
  ```

  Use `layout="horizontal"` to render a single row; default is stacked vertically.

- The plugin also injects the buttons on `wp-login.php` when enabled in settings.

## What happens on login?

1. The JavaScript bundle launches `signInWithOAuth` for the selected provider.
2. Supabase completes the external OAuth flow and returns to WordPress.
3. The plugin exchanges the Supabase session via a REST endpoint, creates the WordPress account if needed, and issues standard WordPress auth cookies.
4. Metadata is stored on the user: `lws_supabase_uuid` (Supabase user ID) and `lws_supabase_provider` (provider slug).

## Hooks

- `lws_user_authenticated( int $user_id, array $supabase_user )` fires after the user is logged in.
- `lws_new_user_created( int $user_id, array $supabase_user )` fires after a new WordPress account is provisioned.
