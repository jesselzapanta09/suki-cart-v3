import api from './api';
import { isCordova } from './pushHelper';

let foregroundMessageListenerReady = false;
let messagingLib = null;

/**
 * Dynamically load Firebase messaging SDK ONLY if not in Cordova environment
 * This prevents "unsupported-browser" error in Cordova
 */
async function loadMessaging() {
    if (isCordova()) {
        console.log('[Push] Cordova detected - Firebase Web SDK not loaded');
        return null;
    }

    if (!messagingLib) {
        try {
            messagingLib = await import('firebase/messaging');
        } catch (err) {
            console.error('[Push] Failed to load Firebase messaging:', err);
            return null;
        }
    }

    return messagingLib;
}

async function isMessagingSupportedInThisEnvironment() {
    if (isCordova()) {
        return false;
    }

    if (
        typeof window === 'undefined' ||
        typeof navigator === 'undefined' ||
        window.location?.protocol === 'file:'
    ) {
        return false;
    }

    if (!('serviceWorker' in navigator) || !('Notification' in window) || !('PushManager' in window)) {
        return false;
    }

    const lib = await loadMessaging();
    if (!lib || typeof lib.isSupported !== 'function') {
        return false;
    }

    return lib.isSupported().catch(() => false);
}

// ── Notifications ──────────────────────────────────────────I────────────────────

export function getNotifications(page = 1, perPage = 20) {
    return api.get('/notifications', { params: { page, per_page: perPage } });
}

export function getUnreadCount() {
    return api.get('/notifications/unread-count');
}

export function markRead(id) {
    return api.post(`/notifications/${id}/mark-read`);
}

export function markAllRead() {
    return api.post('/notifications/mark-all-read');
}

export function deleteNotification(id) {
    return api.delete(`/notifications/${id}`);
}

// ── FCM Push Subscription ──────────────────────────────────────────────────────

export function saveFCMToken(deviceToken, deviceType = 'web', deviceName = null) {
    return api.post('/notifications/push-subscription', {
        device_token: deviceToken,
        device_type: deviceType,
        device_name: deviceName,
    });
}

export function deleteFCMToken(deviceToken) {
    return api.delete('/notifications/push-subscription', {
        data: { device_token: deviceToken },
    });
}

// ── Firebase messaging setup ───────────────────────────────────────────────────

/**
 * Request notification permission and get FCM token
 */
export async function registerPushSubscription() {
    // Guard against Cordova/mobile environments where Notification API doesn't exist
    if (isCordova()) {
        console.warn('[Push] Not supported in Cordova environment.');
        return null;
    }

    if (!('serviceWorker' in navigator) || !('Notification' in window) || !('PushManager' in window)) {
        console.warn('[Push] Not supported in this environment.');
        return null;
    }

    try {
        const supported = await isMessagingSupportedInThisEnvironment();
        if (!supported) {
            console.warn('[Push] Firebase messaging is not supported in this environment.');
            return null;
        }

        const lib = await loadMessaging();
        if (!lib) {
            console.warn('[Push] Failed to load Firebase messaging SDK.');
            return null;
        }

        const { getMessaging, getToken } = lib;
        const messaging = getMessaging();

        // Request notification permission
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            console.warn('[Push] Notification permission denied.');
            return null;
        }

        // Ensure service worker is ready and pass registration to getToken
        const registration = await navigator.serviceWorker.ready;
        // Get FCM token
        console.log("🔥 VAPID:", import.meta.env.VITE_FIREBASE_VAPID_KEY);
        const token = await getToken(messaging, {
            vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
            serviceWorkerRegistration: registration,
        });

        console.log("🔥 TOKEN:", token);

        if (!token) {
            console.warn('[Push] Failed to get FCM token.');
            return null;
        }

        // Save to backend
        await saveFCMToken(token, 'web', navigator.userAgent.substring(0, 255));

        // Set up message listener for foreground notifications
        setUpMessageListener();

        return token;
    } catch (err) {
        console.error('[Push] Failed to register:', err);
        return null;
    }
}

/**
 * Unregister and remove FCM token
 */
export async function unregisterPushSubscription() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    if (isCordova()) {
        console.warn('[Push] Unregister not supported in Cordova environment.');
        return;
    }

    try {
        const supported = await isMessagingSupportedInThisEnvironment();
        if (!supported) {
            return;
        }

        const lib = await loadMessaging();
        if (!lib) {
            console.warn('[Push] Failed to load Firebase messaging SDK.');
            return;
        }

        const { getMessaging, getToken, deleteToken } = lib;
        const messaging = getMessaging();
        const registration = await navigator.serviceWorker.ready.catch(() => null);
        const token = await getToken(messaging, {
            vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
            serviceWorkerRegistration: registration,
        }).catch(() => null);

        if (token) {
            await deleteFCMToken(token);
            await deleteToken(messaging);
        }
    } catch (err) {
        console.error('[Push] Failed to unregister:', err);
    }
}

/**
 * Set up listener for foreground messages
 */
export async function setUpMessageListener() {
    if (foregroundMessageListenerReady) {
        return;
    }

    if (isCordova()) {
        console.log("📱 Skip web listener (Cordova)");
        return;
    }

    try {
        const supported = await isMessagingSupportedInThisEnvironment();
        if (!supported) {
            console.warn('[Push] Foreground listener skipped: unsupported environment.');
            return;
        }

        const lib = await loadMessaging();
        if (!lib) {
            console.warn('[Push] Failed to load Firebase messaging SDK.');
            return;
        }

        const { getMessaging, onMessage } = lib;
        const messaging = getMessaging();

        onMessage(messaging, (payload) => {
            console.log('[Push] Foreground message:', payload);

            const title = payload.notification?.title || 'Notification';
            const body = payload.notification?.body || '';

            // 🔔 SHOW NOTIFICATION
            if (!isCordova() && "Notification" in window) {
                if (Notification.permission === 'granted') {
                    new Notification(title, {
                        body: body,
                        icon: '/suki-cart-logo.png',
                    });
                }
            }

            // OPTIONAL: still dispatch event to your app
            const notification = {
                id: payload.data?.notification_id || Date.now(),
                type: payload.data?.type || 'system',
                title,
                message: body,
                data: payload.data || {},
                created_at: new Date().toISOString(),
            };

            window.dispatchEvent(
                new CustomEvent('pushNotification', { detail: notification })
            );
        });

        foregroundMessageListenerReady = true;

    } catch (err) {
        console.warn('[Push] Failed to set up message listener:', err);
    }
}
