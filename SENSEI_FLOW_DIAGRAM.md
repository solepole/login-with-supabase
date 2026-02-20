# Login with Supabase - Sensei LMS Integration Flow

> **Note:** This flow diagram specifically covers Sensei LMS integration. The plugin also supports WooCommerce integration with similar flow on `/my-account` pages. Enable it via Settings → Login with Supabase → "Add buttons to WooCommerce forms".

## Visual Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    User Not Logged In                           │
│                  Visits /my-courses                             │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│              Sensei LMS Renders Login/Register Page             │
│                                                                 │
│  ┌──────────────────────────────────┬─────────────────────────┐ │
│  │     Login Form (col-1)           │  Register Form (col-2)  │ │
│  │  ┌─────────────────────────┐     │  ┌────────────────────┐ │ │
│  │  │ Username/Email Field    │     │  │ First Name         │ │ │
│  │  │ Password Field          │     │  │ Last Name          │ │ │
│  │  │ Remember Me Checkbox    │     │  │ Username           │ │ │
│  │  │ Login Button            │     │  │ Email              │ │ │
│  │  │ Lost Password Link      │     │  │ Password           │ │ │
│  │  └─────────────────────────┘     │  │ Register Button    │ │ │
│  │                                  │  └────────────────────┘ │ │
│  │  ← sensei_login_form_inside_after│  ← sensei_register_form │ │
│  │         (Hook Point)             │        _fields          │ │
│  │                                  │     (Hook Point)        │ │
│  │  ┌─────────────────────────┐     │  ┌────────────────────┐ │ │
│  │  │ ─────────────────────── │     │  │ ────────────────── │ │ │
│  │  │                         │     │  │ Or sign up with:   │ │ │
│  │  │ [Login with Google]     │     │  │                    │ │ │
│  │  │ [Login with Azure]      │     │  │ [Login with Google]│ │ │
│  │  │                         │     │  │ [Login with Azure] │ │ │
│  │  └─────────────────────────┘     │  └────────────────────┘ │ │
│  └──────────────────────────────────┴─────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                          │
                          │ User Clicks OAuth Button
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                  JavaScript Event Handler                       │
│  • front.js catches click event                                 │
│  • Reads data-provider attribute                                │
│  • Calls supabase.auth.signInWithOAuth()                        │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│               Redirect to OAuth Provider                        │
│  (Google, Azure, GitHub, etc.)                                  │
│  • User authenticates with provider                             │
│  • Provider authorizes access                                   │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│              Return to WordPress with Token                     │
│  • Supabase callback URL                                        │
│  • Session contains access_token                                │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│         REST API Call: /wp-json/login-with-supabase/v1/         │
│                           session                               │
│  • Send access_token to WordPress                               │
│  • WordPress validates with Supabase                            │
│  • Fetches user data from Supabase                              │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                   User Synchronization                          │
│  • Check if user exists by email                                │
│  • If exists: Update metadata                                   │
│  • If new: Create WordPress user                                │
│  • Set WordPress auth cookie                                    │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Redirect to Target                           │
│  • Default: redirect_url from settings                          │
│  • Or: /my-courses (Sensei redirects to here)                   │
│  • User is now logged in                                        │
└─────────────────────────────────────────────────────────────────┘
```

## Code Execution Flow

### 1. Plugin Initialization
```
login-with-supabase.php
  └─> lws_run()
      └─> Login_With_Supabase_Plugin::init()
          ├─> LWS_Admin::init()
          │   └─> Registers settings
          └─> LWS_Frontend::init()
              └─> Conditionally adds hooks
```

### 2. Hook Registration (when enabled)
```
LWS_Frontend::init()
  └─> if (show_on_sensei_forms == 1)
      ├─> add_action('sensei_login_form_inside_after', ...)
      └─> add_action('sensei_register_form_fields', ...)
```

### 3. Sensei Page Render
```
Sensei LMS renders /my-courses
  └─> Sensei_Frontend::sensei_login_form()
      ├─> Renders login form
      │   └─> do_action('sensei_login_form_inside_after')
      │       └─> LWS_Frontend::render_login_buttons_on_sensei_login()
      │           ├─> Enqueue assets (CSS/JS)
      │           ├─> Render shortcode
      │           └─> Bootstrap JavaScript
      │
      └─> Renders register form
          └─> do_action('sensei_register_form_fields')
              └─> LWS_Frontend::render_login_buttons_on_sensei_register()
                  ├─> Enqueue assets
                  ├─> Render shortcode with "Or sign up" text
                  └─> Bootstrap JavaScript
```

### 4. Button Click Handler
```
User clicks button
  └─> front.js event listener
      └─> lwsInstallButtons()
          └─> button.addEventListener('click')
              └─> handleProviderClick(provider)
                  └─> supabase.auth.signInWithOAuth({provider})
```

### 5. OAuth Callback
```
OAuth provider returns to WordPress
  └─> Supabase handles callback
      └─> front.js detects session
          └─> POST /wp-json/login-with-supabase/v1/session
              └─> LWS_Frontend::handle_session_login()
                  ├─> Validate nonce
                  ├─> Fetch user from Supabase
                  ├─> LWS_Frontend::sync_user()
                  │   ├─> Check existing user
                  │   └─> Create or update
                  ├─> wp_set_auth_cookie()
                  └─> Return redirect URL
```

## File Structure with Sensei Integration

```
wp-content/plugins/login-with-supabase/
│
├── login-with-supabase.php          [Modified] Version bump
│
├── includes/
│   ├── class-lws-admin.php          [Modified] New option + field
│   └── class-lws-frontend.php       [Modified] New hooks + methods
│
├── assets/
│   ├── css/
│   │   └── front.css                [Modified] Sensei-specific styles
│   └── js/
│       └── front.js                 [Unchanged] Reused existing
│
├── README.md                         [Modified] New docs
├── SENSEI_INTEGRATION.md             [New] Integration guide
└── CHANGELOG-0.1.4.md                [New] Change log
```

## Hook Priority & Execution Order

```
WordPress Init (priority 10)
  └─> Sensei loads
  └─> Login with Supabase loads
      └─> LWS_Frontend::init() registers hooks

User visits /my-courses
  └─> sensei_login_form_before (Sensei)
  └─> Login form renders
  └─> sensei_login_form_inside_before (Sensei)
  └─> Form fields render
  └─> sensei_login_form_inside_after_password_field (Sensei)
  └─> sensei_login_form_inside_after (Priority 10)
      └─> [OUR HOOK] LWS_Frontend::render_login_buttons_on_sensei_login()
  └─> sensei_login_form_after (Sensei)

  └─> sensei_register_form_start (Sensei)
  └─> Register form fields render
  └─> sensei_register_form_fields (Priority 10)
      └─> [OUR HOOK] LWS_Frontend::render_login_buttons_on_sensei_register()
  └─> sensei_register_form_end (Sensei)
```

## CSS Cascade

```
Base Styles (front.css)
  └─> .lws-button (base button styles)
  └─> .lws-buttons--vertical (layout)

Sensei-Specific Styles
  └─> .lws-sensei-login-buttons (separator + spacing)
  └─> .sensei .lws-login-wrapper--sensei-login .lws-button (full-width)
```

## JavaScript Loading Strategy

```
1. WordPress enqueues supabase-js from CDN
2. WordPress enqueues front.js (depends on supabase-js)
3. front.js defines window.lwsInstallButtons()
4. Inline script in render_login_buttons_on_sensei_login() calls installButtons
5. Fallback bootstrap script retries if initial load fails
6. Buttons become active and clickable
```

## Data Flow

```
Button Click
  └─> Provider: "google"
      └─> Supabase OAuth URL
          └─> Google Auth
              └─> Consent Screen
                  └─> Grant Access
                      └─> Redirect to WordPress
                          └─> access_token
                              └─> REST API
                                  └─> Supabase User Data
                                      ├─> Email
                                      ├─> Name
                                      ├─> Provider
                                      └─> UUID
                                          └─> WordPress User
                                              ├─> username
                                              ├─> email
                                              ├─> display_name
                                              ├─> first_name
                                              ├─> last_name
                                              └─> User Meta
                                                  ├─> lws_supabase_uuid
                                                  └─> lws_supabase_provider
```

## Security Layer

```
Frontend (front.js)
  ├─> Uses lwsAuth.nonce for REST API
  └─> Validates Supabase session

REST API (/wp-json/login-with-supabase/v1/session)
  ├─> Validates nonce
  ├─> Validates access_token with Supabase
  └─> Checks email exists in Supabase response

WordPress
  ├─> Sanitizes all user input
  ├─> Uses wp_create_user() / wp_update_user()
  └─> Sets secure auth cookie
```

## State Management

```
Before Click:
  • User: Not authenticated
  • Button: .lws-button
  • Spinner: hidden

During OAuth:
  • User: Redirected to provider
  • Button: .lws-button--busy
  • Spinner: visible (animation)

After Success:
  • User: Authenticated (WordPress cookie set)
  • Page: Redirected to target URL
  • Button: No longer visible (user logged in)

After Error:
  • User: Still not authenticated
  • Button: .lws-button (normal state)
  • Feedback: .lws-feedback--error (error message)
```

This visual guide should help you understand exactly how the Sensei integration works!
