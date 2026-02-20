# Quick Start Guide: Sensei LMS Integration

## Quick Setup (3 Steps)

### Step 1: Enable the Feature
1. Log into WordPress admin
2. Go to **Settings → Login with Supabase**
3. Check **"Add buttons to Sensei LMS forms"**
4. Click **Save Changes**

### Step 2: Test the Integration
1. **Log out** of WordPress
2. Visit `/my-courses` in your browser
3. You should see OAuth buttons at the bottom of:
   - **Login form** (left side)
   - **Register form** (right side)

### Step 3: Test Login
1. Click any OAuth button (e.g., "Login with Google")
2. Complete authentication with provider
3. You'll be redirected back and automatically logged in!

---

## Prerequisites Checklist

Before enabling, make sure you have:

- **Sensei LMS plugin** installed and activated
- **Supabase credentials** configured in plugin settings
- At least **one OAuth provider** enabled (Google, Azure, etc.)
- Your **WordPress site URL** added to Supabase redirect URLs

---

## What You'll See

### Login Form
```
┌─────────────────────────┐
│ Username or Email       │
│ [input field]           │
│                         │
│ Password                │
│ [input field]           │
│                         │
│ [Login Button]          │
│ Lost your password?     │
│                         │
│ ☐ Remember me           │
│ ─────────────────────   │
│                         │
│ [Login with Google]     │ ← NEW!
│ [Login with Azure]      │ ← NEW!
└─────────────────────────┘
```

### Register Form
```
┌─────────────────────────┐
│ First Name              │
│ [input field]           │
│                         │
│ Last Name               │
│ [input field]           │
│                         │
│ Username                │
│ [input field]           │
│                         │
│ Email                   │
│ [input field]           │
│                         │
│ Password                │
│ [input field]           │
│                         │
│ [Register Button]       │
│ ─────────────────────   │
│ Or sign up with:        │ ← NEW!
│                         │
│ [Login with Google]     │ ← NEW!
│ [Login with Azure]      │ ← NEW!
└─────────────────────────┘
```

---

## Configuration Options

### In WordPress Admin → Settings → Login with Supabase

| Setting | Options | Default | Description |
|---------|---------|---------|-------------|
| **Add buttons to wp-login.php** | ☐ On/Off | [X] On | Show on default WordPress login |
| **Add buttons to Sensei LMS forms** | ☐ On/Off | [ ] Off | Show on Sensei login/register |
| **Add buttons to WooCommerce forms** | ☐ On/Off | [ ] Off | Show on WooCommerce login/register |
---

## Customization

### Change Separator Color
Add to your theme's CSS:
```css
.lws-sensei-login-buttons {
    border-top-color: #your-color !important;
}
```

### Adjust Spacing
```css
.lws-sensei-register-buttons {
    margin-top: 30px !important;
    padding-top: 30px !important;
}
```

### Hide "Or sign up with:" Text
```php
// Add to functions.php
add_filter('gettext', function($translated, $text, $domain) {
    if ($domain === 'login-with-supabase' && $text === 'Or sign up with:') {
        return ''; // Empty string hides it
    }
    return $translated;
}, 10, 3);
```

---

## Troubleshooting

### Buttons Don't Appear

**Check 1:** Is Sensei LMS/WooCommerce plugin active?
```bash
WordPress Admin → Plugins → Look for "Sensei LMS"/"WooCommerce"
```

**Check 2:** Is the option enabled?
```bash
Settings → Login with Supabase → "Add buttons to Sensei LMS forms"
Settings → Login with Supabase → "Add buttons to WooCommerce forms"
```

**Check 3:** Clear all caches
- WordPress cache (if using caching plugin)
- Browser cache (Ctrl+Shift+R or Cmd+Shift+R)
- CDN cache (if applicable)

**Check 4:** Are providers configured?
```bash
Settings → Login with Supabase → "Enabled OAuth Providers"
Should have at least one: azure, google, github, etc.
```

---

### Buttons Appear But Don't Work

**Check 1:** Browser Console
1. Press F12 (open Developer Tools)
2. Go to **Console** tab
3. Look for red errors
4. Check if these files loaded:
   - `supabase.min.js`
   - `front.js`
   - `front.css`

**Check 2:** Supabase Configuration
```bash
Settings → Login with Supabase
• Supabase Project URL: https://xxxxx.supabase.co
• Supabase Anon Key: eyJ... (long string)
```

**Check 3:** Redirect URLs in Supabase
1. Go to Supabase Dashboard
2. Authentication → URL Configuration
3. Add: `https://your-site.com/*` to redirect URLs

---

### Authentication Works But No Redirect

**Check:** Redirect URL Setting
```bash
Settings → Login with Supabase → "Post-login Redirect URL"
Default: Your home page
Recommended for Sensei: /my-courses
```

---

## Security

The integration is secure:
- Uses WordPress nonces
- Validates OAuth tokens server-side
- Sanitizes all user input
- No passwords stored (OAuth-based)
- Respects WordPress user roles

---

## User Experience Flow (use Google as provider for example)

```
1. User (not logged in) visits /login (WordPress entrance), or
   User (not logged in) visits /my-courses (Sensei LMS entrance), or
   User (not logged in) visits /my-account (WooCommerce entrance)
   └─> Sees WordPress/Sensei/WooCommerce login/register forms

2. User clicks "Login with Google"
   └─> Redirected to Google for authentication

3. User signs in to Google
   └─> Google asks for permission to share profile

4. User clicks "Allow"
   └─> Redirected back to WordPress

5. WordPress processes OAuth
   └─> Creates/updates user account
   └─> Sets login cookie
   └─> Redirects to /my-courses or configured URL

6. User is now logged in!
   └─> Can access resources as logged in user (e.g. WordPress Pages that requires login/Sensei courses/WooCommerce product)
```

---

## Pro Tips

1. **Test with incognito/private browsing** to simulate new users
2. **Enable both forms** (login + register) for maximum flexibility
3. **Use Google OAuth** for the smoothest experience
4. **Set redirect to /my-courses** so users land on their dashboard
5. **Add multiple providers** to give users choice

---

## Monitoring

### Check if it's working

**Admin Dashboard:**
- Go to Users → All Users
- Look for new users with OAuth provider in their meta
- Check `lws_supabase_uuid` and `lws_supabase_provider` user meta

**Database Query (if needed):**
```sql
SELECT user_id, meta_key, meta_value 
FROM wp_usermeta 
WHERE meta_key IN ('lws_supabase_uuid', 'lws_supabase_provider');
```

---

## Still Need Help?

1. **Enable WordPress Debug Mode:**
   ```php
   // Add to wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. **Check Debug Log:**
   ```
   /wp-content/debug.log
   ```

3. **Browser Console:**
   - F12 → Console tab
   - Look for `[LWS]` prefixed messages

4. **Test with Default Theme:**
   - Temporarily switch to Twenty Twenty-Four theme
   - Disable other plugins
   - Isolate conflicts

---

## Success Indicators

You'll know it's working when:
- Buttons appear on `/my-courses` forms
- Clicking a button redirects to OAuth provider
- After auth, you're logged into WordPress
- New users appear in WordPress admin
- User meta shows Supabase UUID and provider

---

## That's It!

Your Sensei LMS site now supports modern OAuth authentication. Users can sign in with their favorite providers in just 2 clicks!

**Enjoy!**

---

**Need more details?** Check out:
- `SENSEI_INTEGRATION.md` - Full technical guide
- `SENSEI_FLOW_DIAGRAM.md` - Visual flow diagrams
- `README.md` - Complete plugin documentation
