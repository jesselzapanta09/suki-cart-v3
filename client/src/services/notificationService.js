import api from './api';
import { getMessaging, getToken, deleteToken, onMessage } from 'firebase/messaging';

// ── Notifications ──────────────────────────────────────────────────────────────

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
    if (!('serviceWorker' in navigator) || !('Notification' in window)) {
        console.warn('[Push] Not supported in this environment.');
        return null;
    }

    try {
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
    if (!('serviceWorker' in navigator)) return;

    try {
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
export function setUpMessageListener() {
    try {
        const messaging = getMessaging();

        onMessage(messaging, (payload) => {
            console.log('[Push] Foreground message:', payload);

            const title = payload.notification?.title || 'Notification';
            const body = payload.notification?.body || '';

            // 🔔 SHOW NOTIFICATION
            if (Notification.permission === 'granted') {
                new Notification(title, {
                    body: body,
                    icon: '/suki-cart-logo.png',
                });
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

    } catch (err) {
        console.warn('[Push] Failed to set up message listener:', err);
    }
}
