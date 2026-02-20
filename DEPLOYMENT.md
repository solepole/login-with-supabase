# WordPress.com Deployment Checklist

## Pre-Deployment

- [ ] Verify plugin works locally
- [ ] Test with default WordPress theme
- [ ] Disable other plugins temporarily to test for conflicts
- [ ] Note your current plugin settings (backup)

## Deployment Steps

### 1. Upload to WordPress.com

1. Go to your WordPress.com site
2. Navigate to: **My Sites > Plugins > Upload Plugin**
3. Click "Choose File" and select `login-with-supabase.zip`
4. Click "Install Now"
5. Click "Activate Plugin"

### 2. Configure Plugin Settings

1. Go to **Settings > Login with Supabase**
2. Enter your Supabase configuration:
   - **Supabase Project URL**: `https://xxxxx.supabase.co`
   - **Supabase Anon Key**: Your anon/public key
3. Select providers to enable (e.g., Google, GitHub)
4. Set redirect URL (optional, defaults to homepage)
5. Check "Show on default WordPress login page" if desired
6. Click **Save Changes**

### 3. Configure Supabase

1. Go to your Supabase Dashboard
2. Navigate to **Authentication > URL Configuration**
3. Add your WordPress.com site URL to **Redirect URLs**:
   ```
   https://yoursite.wordpress.com/
   https://yoursite.wordpress.com/wp-login.php
   https://yoursite.wordpress.com/*
   ```
4. Go to **Authentication > Providers**
5. Verify each enabled provider is properly configured

### 4. Clear Cache (CRITICAL!)

**WordPress.com built-in cache:**
1. Go to **My Sites > Manage > Settings**
2. Scroll to **Performance** section
3. Click **"Clear Cache"**

**If using WP Super Cache plugin:**
1. Go to **Settings > WP Super Cache**
2. Click **"Delete Cache"**

**If using other cache plugins:**
- Clear cache through their respective settings

### 6. Test the Plugin

1. **Open login page in incognito/private window**:
   ```
   https://yoursite.wordpress.com/wp-login.php?lws_debug=1
   ```

2. **Check for "Login with XXX" buttons below the login form**

3. **Verify icons are visible** (Google icon, GitHub icon, etc.)

4. **Open browser Developer Tools** (F12):
   - Go to **Console** tab
   - Look for messages starting with `[LWS]`
   - Should see:
     ```
     [LWS] Initializing Login with Supabase
     [LWS] Booting application
     [LWS] Setting up buttons
     [LWS] Found X wrapper(s)
     [LWS] Found X button(s) in wrapper
     [LWS] Injecting icon for provider: google
     [LWS] Icon injected successfully for: google
     [LWS] Button setup complete for: google
     ```

5. **Click a login button** (e.g., "Login with Google"):
   - Should redirect to OAuth provider
   - Should see message in console:
     ```
     [LWS] Button clicked for provider: google
     [LWS] Starting OAuth flow for: google
     ```

6. **Complete authentication with provider**

7. **Should redirect back** to your WordPress site and log you in

## Troubleshooting

### Icons Not Showing

**Symptom**: Buttons appear but without provider icons

**Fix**:
1. Clear WordPress.com cache again
2. Hard refresh browser: `Ctrl+Shift+R` (Windows) or `Cmd+Shift+R` (Mac)
3. Check browser console for JavaScript errors
4. Verify `front.css` is loading in Network tab

### Buttons Not Clickable

**Symptom**: Buttons appear but nothing happens when clicked

**Fix**:
1. Add `?lws_debug=1` to URL and check console
2. Look for errors starting with `[LWS] ERROR:`
3. Common issues:
   - Supabase library not loading: Check Network tab for `supabase.min.js`
   - Plugin script not loading: Check Network tab for `front.js`
   - Configuration missing: Verify Supabase URL and key in settings

### Authentication Fails

**Symptom**: Redirects to provider but fails to log into WordPress

**Fix**:
1. Check WordPress.com REST API is accessible:
   ```
   https://yoursite.wordpress.com/wp-json/login-with-supabase/v1/session
   ```
   Should return: `{"code":"rest_no_route","message":"No route was found..."}` (this is OK)
   
2. Verify redirect URLs in Supabase match your site exactly
3. Check WordPress debug log for PHP errors:
   - Enable debug logging in `wp-config.php`:
     ```php
     define('WP_DEBUG', true);
     define('WP_DEBUG_LOG', true);
     ```
   - Check `/wp-content/debug.log` for errors

### Script Loading Issues

**Symptom**: Console shows scripts not loading

**Fix**:
1. Check if WordPress.com is blocking external CDN:
   - Look for `supabase.min.js` in Network tab
   - Should load from `cdn.jsdelivr.net`
   
2. Check if plugin files are accessible:
   - Direct access: `https://yoursite.wordpress.com/wp-content/plugins/login-with-supabase/assets/js/front.js`
   - Should download the file (not show 404)

3. Disable conflicting plugins:
   - Temporarily deactivate other JavaScript-heavy plugins
   - Test login functionality
   - Re-enable one by one to identify conflicts

## Post-Deployment

- [ ] Test login with each enabled provider
- [ ] Verify user profile information syncs correctly
- [ ] Test logout functionality
- [ ] Enable previously disabled plugins
- [ ] Re-test to ensure no conflicts
- [ ] Set up monitoring/logging if needed
- [ ] Document any custom configuration

## WordPress.com Specific Issues

### Issue: Cache not clearing

Some WordPress.com plans have aggressive caching. If cache won't clear:

1. Wait 5-10 minutes for cache to expire naturally
2. Make a small change to plugin settings (save again)
3. Try accessing with a new query parameter: `?nocache=123`
4. Contact WordPress.com support if persistent

### Issue: CDN blocking Supabase library

If WordPress.com blocks external CDN:

1. Consider upgrading to Business plan (better external resource support)
2. Or download Supabase library and host locally (not recommended)

### Issue: REST API not working

WordPress.com sometimes has REST API restrictions:

1. Verify REST API is enabled for your site
2. Check plugin permissions in WordPress.com dashboard
3. Ensure Business plan or higher (required for plugin uploads)

## Success Indicators

- Buttons visible on login page  
- Icons showing for each provider  
- Buttons respond to clicks  
- OAuth redirects work  
- Users can log in successfully  
- Profile information syncs  
- No JavaScript errors in console  
- No PHP errors in debug log  

## Need Help?

1. **Check debug logs**: Add `?lws_debug=1` to URL
2. **Browser console**: Look for `[LWS]` messages
3. **Network tab**: Verify all resources loading
4. **WordPress debug log**: Check for PHP errors
5. **Supabase logs**: Check dashboard for OAuth errors

## Rollback Plan

If deployment fails:

1. Deactivate plugin from WordPress.com dashboard
2. Delete plugin if necessary
3. Clear cache again
4. Restore from local backup if you made database changes
5. Review errors before attempting re-deployment
