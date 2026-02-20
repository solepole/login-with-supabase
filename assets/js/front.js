(function () {
    'use strict';
    
    // Debug logging helper - checks both URL parameter and config setting
    // Use a function to check dynamically since lwsAuth is injected after script loads
    function isDebugEnabled() {
        return window.location.search.indexOf('lws_debug=1') > -1 || 
               (window.lwsAuth && window.lwsAuth.debugMode === 1);
    }
    
    function log(message, data) {
        if (isDebugEnabled() && typeof console !== 'undefined' && console.log) {
            var prefix = '[LWS] ';
            if (data !== undefined) {
                console.log(prefix + message, data);
            } else {
                console.log(prefix + message);
            }
        }
    }
    
    // Define lwsInstallButtons stub early so it's always available
    // This will be replaced with the real implementation once everything loads
    window.lwsInstallButtons = function () {
        log('lwsInstallButtons called (stub - waiting for full initialization)');
        // Retry logic if not ready yet
        setTimeout(function() {
            if (typeof window.lwsInstallButtons === 'function') {
                window.lwsInstallButtons();
            }
        }, 100);
    };
    
    if (typeof window.lwsAuth === 'undefined') {
        log('ERROR: lwsAuth configuration not found');
        return;
    }
    
    if (typeof window.supabase === 'undefined') {
        log('ERROR: Supabase library not loaded');
        return;
    }

    log('Initializing Login with Supabase', {
        supabaseUrl: window.lwsAuth.supabaseUrl,
        providersCount: window.lwsAuth.providers ? window.lwsAuth.providers.length : 0
    });

    const config = window.lwsAuth;
    const client = window.supabase.createClient(config.supabaseUrl, config.supabaseAnonKey, {
        auth: {
            persistSession: true,
            detectSessionInUrl: true,
        },
    });

    let syncingSession = false;
    let initialized = false;

    function boot() {
        if (initialized) {
            log('Already initialized, skipping');
            return;
        }
        initialized = true;
        log('Booting application');

        // Clear any stale sync tracking keys from previous sessions
        clearStaleSyncKeys();

        setupButtons();
        handleOAuthError();
        announceReady();

        if (config.isLoggedIn) {
            log('User already logged in to WordPress');
            return;
        }

        const params = new URLSearchParams(window.location.search);
        if (params.get('loggedout') === 'true') {
            log('Logout detected, clearing session');
            clearSupabaseSession(true);
            return;
        }

        checkExistingSession();
    }

    function announceReady() {
        try {
            document.dispatchEvent(new CustomEvent('lws-scripts-ready'));
        } catch (error) {
            try {
                const fallbackEvent = document.createEvent('Event');
                fallbackEvent.initEvent('lws-scripts-ready', true, true);
                document.dispatchEvent(fallbackEvent);
            } catch (error) {}
        }
    }

    window.lwsInstallButtons = function () {
        setupButtons();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    client.auth.onAuthStateChange((event, session) => {
        if (event === 'SIGNED_IN') {
            syncSession(session);
        }
    });

    function setupButtons() {
        log('Setting up buttons');
        const wrappers = document.querySelectorAll('.lws-login-wrapper');
        log('Found ' + wrappers.length + ' wrapper(s)');
        
        wrappers.forEach((wrapper) => {
            const buttons = wrapper.querySelectorAll('.js-lws-login');
            log('Found ' + buttons.length + ' button(s) in wrapper');
            
            buttons.forEach((button) => {
                if (button.dataset.lwsBound === '1') {
                    log('Button already bound, skipping');
                    return;
                }
                button.dataset.lwsBound = '1';
                button.dataset.label = button.dataset.label || button.textContent.trim() || config.labels.genericButton;
                
                log('Injecting icon for provider: ' + button.dataset.provider);
                injectProviderIcon(button);
                
                button.addEventListener('click', () => {
                    log('Button clicked for provider: ' + button.dataset.provider);
                    if (syncingSession) {
                        log('Already syncing session, ignoring click');
                        return;
                    }
                    startOAuth(button);
                });
                
                log('Button setup complete for: ' + button.dataset.provider);
            });
        });
    }

    function injectProviderIcon(button) {
        if (button.dataset.iconInjected === '1') {
            return;
        }

        const provider = (button.dataset.provider || '').toLowerCase();
        log('Injecting icon for provider: ' + provider);
        let svg = '';

        switch (provider) {
            case 'azure': 
            case 'microsoft':
                svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23 23"><path fill="#f3f3f3" d="M0 0h23v23H0z"/><path fill="#f35325" d="M1 1h10v10H1z"/><path fill="#81bc06" d="M12 1h10v10H12z"/><path fill="#05a6f0" d="M1 12h10v10H1z"/><path fill="#ffba08" d="M12 12h10v10H12z"/></svg>';
                break;
            case 'google':
                svg = '<svg class="lws-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285f4" d="M12 10.8v3.6h5.08c-.22 1.18-1.36 3.46-5.08 3.46-3.06 0-5.56-2.54-5.56-5.66 0-3.12 2.5-5.66 5.56-5.66 1.74 0 2.92.74 3.6 1.38l2.46-2.38C16.88 3.42 14.74 2.4 12 2.4 6.7 2.4 2.4 6.7 2.4 12c0 5.3 4.3 9.6 9.6 9.6 5.54 0 9.2-3.88 9.2-9.34 0-.62-.06-1.08-.14-1.56H12z"></path></svg>';
                break;
            case 'github':
                svg = '<svg class="lws-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="#161614" d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.09-.745.083-.73.083-.73 1.205.085 1.84 1.236 1.84 1.236 1.07 1.835 2.807 1.304 3.492.997.108-.776.418-1.304.762-1.604-2.665-.305-5.466-1.332-5.466-5.93 0-1.31.47-2.38 1.235-3.22-.135-.304-.54-1.528.105-3.183 0 0 1.005-.322 3.3 1.23a11.5 11.5 0 0 1 3-.404 11.5 11.5 0 0 1 3 .404c2.28-1.552 3.285-1.23 3.285-1.23.645 1.655.24 2.88.12 3.183.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.62-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"></path></svg>';
                break;
            case 'gitlab':
                svg = '<svg class="lws-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="#fc6d26" d="M23.954 14.73l-1.11-3.43-2.37-7.3c-.18-.54-.92-.54-1.1 0l-2.37 7.3H7.993l-2.37-7.3c-.18-.54-.92-.54-1.1 0l-2.37 7.3-1.11 3.43c-.17.52.02 1.1.47 1.43l9.79 7.13c.35.26.84.26 1.19 0l9.79-7.13c.45-.33.64-.91.47-1.43"></path></svg>';
                break;
            case 'facebook':
                svg = '<svg class="lws-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="#1877f2" d="M22.675 0H1.325C.593 0 0 .593 0 1.325v21.351C0 23.406.593 24 1.325 24H12.82v-9.294H9.692V11.41h3.128V8.691c0-3.1 1.893-4.788 4.657-4.788 1.325 0 2.464.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.296h-3.12V24h6.116C23.406 24 24 23.406 24 22.676V1.325C24 .593 23.406 0 22.675 0"></path></svg>';
                break;
            case 'apple':
                svg = '<svg class="lws-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="#000000" d="M16.365 1.43c-.924.06-1.997.66-2.64 1.43-.577.684-1.065 1.68-.877 2.66.98.077 1.992-.557 2.596-1.334.604-.789 1.04-1.874.92-2.756zm2.48 8.575c.022 2.415 1.946 3.22 1.967 3.23-.017.044-.308 1.06-1.017 2.1-.61.93-1.243 1.86-2.236 1.88-.977.02-1.29-.61-2.406-.61-1.117 0-1.463.59-2.384.63-.957.015-1.687-.996-2.303-1.92-1.254-1.92-2.215-5.42-.927-7.78.64-1.16 1.79-1.88 3.04-1.9.952-.02 1.856.65 2.384.65.528 0 1.64-.8 2.767-.68.471.02 1.79.18 2.64 1.36-.07.054-1.57.91-1.55 2.79z"></path></svg>';
                break;
            default:
                break;
        }

        if (!svg) {
            log('No icon found for provider: ' + provider);
            button.dataset.iconInjected = '1';
            return;
        }

        const iconWrapper = document.createElement('span');
        iconWrapper.className = 'lws-icon-wrapper';
        iconWrapper.innerHTML = svg;

        button.insertBefore(iconWrapper, button.firstChild);
        button.dataset.iconInjected = '1';
        log('Icon injected successfully for: ' + provider);
    }

    function startOAuth(button) {
        const provider = button.dataset.provider;
        if (!provider) {
            log('ERROR: No provider specified on button');
            return;
        }

        log('Starting OAuth flow for: ' + provider);
        toggleBusy(button, true, config.labels.working);
        hideMessage(button);

        client.auth
            .signInWithOAuth({
                provider,
                options: {
                    redirectTo: currentRedirectTarget(),
                    scopes: 'openid email profile offline_access',
                },
            })
            .then(function(result) {
                log('OAuth initiated successfully', result);
            })
            .catch((error) => {
                log('ERROR: OAuth failed', error);
                toggleBusy(button, false);
                showMessage(button, error.message || config.labels.error, true);
            });
    }

    function currentRedirectTarget() {
        return window.location.href.split('#')[0];
    }

    function toggleBusy(button, busy, labelOverride) {
        const textEl = button.querySelector('.lws-button-text');
        const spinner = button.querySelector('.lws-spinner');
        button.disabled = busy;
        button.classList.toggle('lws-button--busy', busy);
        if (spinner) {
            spinner.hidden = !busy;
        }
        if (textEl) {
            if (busy && labelOverride) {
                textEl.textContent = labelOverride;
            } else if (!busy) {
                textEl.textContent = button.dataset.label || config.labels.genericButton;
            }
        }
    }

    function showMessage(button, message, isError) {
        const wrapper = button.closest('.lws-login-wrapper');
        if (!wrapper) {
            return;
        }
        const feedback = wrapper.querySelector('.lws-feedback');
        if (!feedback) {
            return;
        }
        if (!isError) {
            feedback.hidden = true;
            feedback.textContent = '';
            feedback.classList.remove('lws-feedback--error');
            return;
        }
        feedback.textContent = message;
        feedback.hidden = false;
        feedback.classList.toggle('lws-feedback--error', !!isError);
    }

    function hideMessage(button) {
        const wrapper = button.closest('.lws-login-wrapper');
        if (!wrapper) {
            return;
        }
        const feedback = wrapper.querySelector('.lws-feedback');
        if (!feedback) {
            return;
        }
        feedback.hidden = true;
        feedback.textContent = '';
        feedback.classList.remove('lws-feedback--error');
    }

    async function syncSession(session) {
        if (!session || syncingSession) {
            return;
        }

        // Check if we're already in the process of handling this session
        var sessionKey = 'lws_syncing_' + (session.access_token ? session.access_token.substring(0, 20) : '');
        if (window.sessionStorage.getItem(sessionKey)) {
            log('Already synced this session, skipping');
            return;
        }

        syncingSession = true;
        window.sessionStorage.setItem(sessionKey, '1');
        
        const button = document.querySelector('.js-lws-login');
        if (button) {
            toggleBusy(button, true, config.labels.working);
            hideMessage(button);
        }

        try {
            const response = await fetch(config.restUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce,
                },
                body: JSON.stringify({
                    access_token: session.access_token,
                }),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                window.sessionStorage.removeItem(sessionKey);
                throw new Error(config.labels.error);
            }

            const payload = await response.json();
            if (!payload || !payload.success) {
                window.sessionStorage.removeItem(sessionKey);
                const message = payload && payload.message ? payload.message : config.labels.error;
                throw new Error(message);
            }

            // Clear the sync tracking key now that we've successfully synced
            window.sessionStorage.removeItem(sessionKey);
            
            // Clear Supabase session after successful WordPress login
            log('WordPress login successful, clearing Supabase session');
            await clearSupabaseSession(false);
            
            // Redirect to the configured page
            var redirectUrl = payload.redirect || config.redirectUrl || '/';
            log('Redirecting to: ' + redirectUrl);
            window.location.href = redirectUrl;
        } catch (error) {
            syncingSession = false;
            window.sessionStorage.removeItem(sessionKey);
            const button = document.querySelector('.js-lws-login');
            if (button) {
                toggleBusy(button, false);
                showMessage(button, error.message || config.labels.error, true);
            }
        }
    }

    function handleOAuthError() {
        const params = new URLSearchParams(window.location.search);
        if (!params.has('error_description')) {
            return;
        }

        const button = document.querySelector('.js-lws-login');
        if (!button) {
            return;
        }

        toggleBusy(button, false);
        showMessage(button, params.get('error_description'), true);
    }

    async function checkExistingSession() {
        // Don't check if already logged in to WordPress
        if (config.isLoggedIn) {
            log('User already logged in to WordPress, skipping session check');
            return;
        }

        log('Checking for existing Supabase session');
        const { data, error } = await client.auth.getSession();
        if (error) {
            log('Error getting session:', error);
            return;
        }

        if (data && data.session) {
            log('Found existing Supabase session, syncing to WordPress');
            syncSession(data.session);
        } else {
            log('No existing Supabase session found');
        }
    }

    async function clearSupabaseSession(forceWipe = false) {
        try {
            await client.auth.signOut({ scope: 'global' });
        } catch (error) {}

        try {
            await client.auth.signOut({ scope: 'local' });
        } catch (error) {}

        if (forceWipe) {
            purgeStoredSessions();
        }
    }

    function purgeStoredSessions() {
        const projectRef = deriveProjectRef(config.supabaseUrl);
        const explicitKey = client && client.auth ? client.auth.storageKey : '';
        const matchers = [];

        if (explicitKey) {
            matchers.push({ type: 'exact', value: explicitKey });
            matchers.push({ type: 'prefix', value: explicitKey.replace(/auth-token$/i, '') });
        }

        if (projectRef) {
            matchers.push({ type: 'contains', value: projectRef });
        }

        // Also clear sync tracking keys
        matchers.push({ type: 'prefix', value: 'lws_syncing_' });

        sweepStorage(window.localStorage, matchers);
        sweepStorage(window.sessionStorage, matchers);
    }

    function deriveProjectRef(url) {
        if (!url) {
            return '';
        }

        try {
            var host = new URL(url).host || '';
            return host.split('.')[0] || '';
        } catch (error) {
            return '';
        }
    }

    function sweepStorage(storage, matchers) {
        if (!storage || !matchers.length) {
            return;
        }

        try {
            for (var index = storage.length - 1; index >= 0; index--) {
                var key = storage.key(index);
                if (!key) {
                    continue;
                }

                if (shouldRemoveKey(key, matchers)) {
                    storage.removeItem(key);
                }
            }
        } catch (error) {}
    }

    function clearStaleSyncKeys() {
        // Clear any lws_syncing_* keys from sessionStorage that might be left from previous attempts
        try {
            for (var i = window.sessionStorage.length - 1; i >= 0; i--) {
                var key = window.sessionStorage.key(i);
                if (key && key.indexOf('lws_syncing_') === 0) {
                    window.sessionStorage.removeItem(key);
                    log('Removed stale sync key: ' + key);
                }
            }
        } catch (error) {
            log('Error clearing stale sync keys', error);
        }
    }

    function shouldRemoveKey(key, matchers) {
        for (var i = 0; i < matchers.length; i++) {
            var matcher = matchers[i];
            if (matcher.type === 'exact' && matcher.value === key) {
                return true;
            }
            if (matcher.type === 'prefix' && key.indexOf(matcher.value) === 0) {
                return true;
            }
            if (matcher.type === 'contains' && key.indexOf(matcher.value) !== -1) {
                return true;
            }
        }

        return false;
    }
})();
