import { saveFCMToken } from './notificationService.js'; // reuse your API

export function isMobilePushRuntime() {
    if (typeof window === 'undefined') {
        return false;
    }

    const protocol = window.location?.protocol;
    const host = window.location?.hostname;
    const docUrl = typeof document !== 'undefined' ? document.URL : '';
    const ua = typeof navigator !== 'undefined' ? navigator.userAgent || '' : '';

    const isFileWebView = protocol === 'file:' || docUrl.startsWith('file:');
    const isLocalhostWebView = (host === 'localhost' || host === '127.0.0.1')
        && /(Android|iPhone|iPad|iPod|wv)/i.test(ua);

    return !!window.cordova || !!window.FirebasePlugin || (isFileWebView && /cordova/i.test(ua)) || (isLocalhostWebView && !!window.cordova);
}

export function canUseMobilePush() {
    if (typeof window === 'undefined') {
        return false;
    }

    return !!window.FirebasePlugin || !!window.cordova;
}

export function waitForCordovaBridge(timeoutMs = 15000) {
    return new Promise((resolve) => {
        if (typeof window === 'undefined') {
            resolve(false);
            return;
        }

        if (window.FirebasePlugin || window.cordova) {
            resolve(true);
            return;
        }

        let pollCount = 0;
        const pollInterval = 250;
        const maxPolls = Math.ceil(timeoutMs / pollInterval);

        const checkBridge = () => {
            pollCount++;

            if (window.FirebasePlugin || window.cordova) {
                console.log('[Push] Cordova bridge found after', pollCount * pollInterval, 'ms');
                resolve(true);
                return;
            }

            if (pollCount >= maxPolls) {
                console.warn('[Push] Cordova bridge not found after', timeoutMs, 'ms');
                resolve(false);
                return;
            }

            setTimeout(checkBridge, pollInterval);
        };

        setTimeout(checkBridge, 0);
    });
}

export function waitForMobilePushReady(timeoutMs = 15000) {
    return new Promise((resolve) => {
        if (typeof window === 'undefined') {
            resolve(false);
            return;
        }

        if (!window.cordova && !window.FirebasePlugin) {
            console.warn('[Push] Cordova bridge/plugin not available');
            resolve(false);
            return;
        }

        if (window.FirebasePlugin) {
            console.log('[Push] FirebasePlugin already available');
            resolve(true);
            return;
        }

        let pollCount = 0;
        const pollInterval = 250;
        const maxPolls = Math.ceil(timeoutMs / pollInterval);

        const checkPlugin = () => {
            pollCount++;

            if (window.FirebasePlugin) {
                console.log('[Push] FirebasePlugin found after', pollCount * pollInterval, 'ms');
                resolve(true);
                return;
            }

            if (pollCount >= maxPolls) {
                console.warn('[Push] FirebasePlugin not found after', timeoutMs, 'ms');
                resolve(false);
                return;
            }

            setTimeout(checkPlugin, pollInterval);
        };

        // Start polling immediately
        setTimeout(checkPlugin, 0);
    });
}

export async function registerPushMobile() {
    console.log('[Push] Starting mobile push registration...');

    const bridgeReady = await waitForCordovaBridge(15000);
    if (!bridgeReady) {
        console.warn('[Push] Skipping mobile push registration: Cordova bridge/plugin unavailable');
        return null;
    }

    const ready = await waitForMobilePushReady(15000);

    console.log('[Push] Plugin ready status:', ready);
    console.log('[Push] window.FirebasePlugin exists?', !!window.FirebasePlugin);
    console.log('[Push] window.cordova exists?', !!window.cordova);

    if (!ready || !window.FirebasePlugin) {
        console.error('[Push] Firebase plugin not available after full wait');
        return null;
    }

    return new Promise((resolve) => {
        console.log('[Push] Calling FirebasePlugin.getToken()...');

        window.FirebasePlugin.getToken(
            async function (token) {
                try {
                    console.log('🔥 MOBILE TOKEN:', token);

                    if (!token) {
                        console.warn('[Push] No token returned from plugin');
                        resolve(null);
                        return;
                    }

                    console.log('[Push] Saving token to backend...');
                    await saveFCMToken(token, 'android', 'cordova');
                    console.log('[Push] Token saved successfully');
                    resolve(token);
                } catch (error) {
                    console.error('[Push] Token save error:', error);
                    resolve(null);
                }
            },
            function (error) {
                console.error('[Push] FirebasePlugin.getToken error:', error);
                resolve(null);
            }
        );
    });
}

export function listenPushMobile() {
    if (!window.FirebasePlugin) return;

    window.FirebasePlugin.onMessageReceived(
        function (message) {
            console.log("📩 Push received:", message);

            if (message.tap) {
                window.location.href = message.data?.url || '/';
            } else {
                alert(message.title + "\n" + message.body);
            }
        },
        function (error) {
            console.error("Push error:", error);
        }
    );
}