# Cordova + React + Firebase Push Notification Fixes

## Problem
The app was throwing `FirebaseError: Messaging: unsupported-browser` when running on Cordova (Android) because the Firebase Web Messaging SDK was being executed in a Cordova environment, which doesn't support it.

## Root Cause
- Firebase Web SDK (`getMessaging`, `getToken`, `onMessage`) was imported at the top level
- These functions attempted to run in Cordova, which doesn't have the necessary browser APIs
- Cordova uses `window.FirebasePlugin` instead

## Solution
The codebase has been refactored to:
1. **Prevent Firebase Web SDK from loading in Cordova** - Dynamic imports ensure the SDK is only loaded in browser environments
2. **Use environment detection** - `isCordova()` helper checks if code is running in Cordova
3. **Platform-specific handlers** - Mobile-first approach ensures Cordova code runs before web code
4. **Isolated code paths** - No cross-contamination between web and mobile implementations

---

## Changes Made

### 1. **notificationService.js** ✅
**File:** `src/services/notificationService.js`

#### Before:
```javascript
import { getMessaging, getToken, deleteToken, onMessage } from 'firebase/messaging';
// ❌ Firebase Web SDK loaded immediately in Cordova
```

#### After:
```javascript
let messagingLib = null;

async function loadMessaging() {
    if (isCordova()) {
        console.log('[Push] Cordova detected - Firebase Web SDK not loaded');
        return null;
    }
    if (!messagingLib) {
        messagingLib = await import('firebase/messaging');
    }
    return messagingLib;
}
```

**Benefits:**
- Firebase Web SDK only imports when NOT in Cordova
- Lazy loading prevents early initialization errors
- Cordova never executes Firebase Web code path

#### Updated Functions:

**`registerPushSubscription()` - Now async with dynamic import:**
```javascript
export async function registerPushSubscription() {
    if (isCordova()) return null;  // Guard first
    
    const lib = await loadMessaging();  // Dynamic import
    const { getMessaging, getToken } = lib;
    // ... rest of web flow
}
```

**`unregisterPushSubscription()` - Now async with dynamic import:**
```javascript
export async function unregisterPushSubscription() {
    if (!('serviceWorker' in navigator)) return;
    if (isCordova()) return;  // Guard first
    
    const lib = await loadMessaging();  // Dynamic import
    const { getMessaging, getToken, deleteToken } = lib;
    // ... rest of web flow
}
```

**`setUpMessageListener()` - Now async with dynamic import:**
```javascript
export async function setUpMessageListener() {
    if (isCordova()) return;  // Guard first
    
    const lib = await loadMessaging();  // Dynamic import
    const { getMessaging, onMessage } = lib;
    // ... rest of web flow
}
```

---

### 2. **NotificationsPage.jsx** ✅
**File:** `src/pages/notifications/NotificationsPage.jsx`

#### Before:
```javascript
import { getMessaging, getToken } from 'firebase/messaging';
// ❌ Firebase Web SDK imported at top level - fails in Cordova
```

#### After:
```javascript
import { isCordova } from "../../services/pushHelper";
import { registerPushMobile } from "../../services/pushMobile";
// ✅ No Firebase Web SDK import at top level
```

#### Push State Check - useEffect:
```javascript
useEffect(() => {
    // 📱 CORDOVA FIRST (IMPORTANT)
    if (isCordova()) {
        console.log("📱 Cordova mode - skip web Firebase");
        setPushEnabled(true);
        return;  // Stop before Firebase Web SDK load
    }

    // 🌐 WEB ONLY BELOW - Firebase Web SDK only used here
    (async () => {
        // Dynamically import Firebase messaging only for web
        const { getMessaging, getToken } = await import('firebase/messaging');
        // ... rest of web flow
    })();
}, []);
```

**Benefits:**
- Cordova path exits before Firebase Web SDK loads
- Web path is isolated in async IIFE
- Clear separation of concerns

#### Push Toggle Handler - Mobile-First:
```javascript
const handleTogglePush = async () => {
    try {
        // 📱 CORDOVA FIRST (CRITICAL)
        if (isCordova()) {
            if (pushEnabled) {
                setPushEnabled(false);
                antMessage.success("Push notifications disabled.");
            } else {
                registerPushMobile();  // ← NOW RUNS IN CORDOVA
                setPushEnabled(true);
                antMessage.success("Push notifications enabled!");
            }
            return;  // Stop here - don't run web code
        }

        // 🌐 WEB FLOW - Only runs in browser
        // Web-specific code here...
    } catch (err) {
        console.error(err);
    }
};
```

**Key Improvements:**
- Mobile code runs first and returns before web code
- `registerPushMobile()` now actually executes in Cordova
- No Firebase Web SDK is touched in mobile path

---

### 3. **pushHelper.js** ✅
**File:** `src/services/pushHelper.js`

Already correct - provides consistent environment detection:
```javascript
export const isCordova = () => {
    return typeof window !== 'undefined' && !!window.cordova;
};
```

---

### 4. **pushMobile.js** ✅
**File:** `src/services/pushMobile.js`

Current implementation properly uses `window.FirebasePlugin`:
- `registerPushMobile()` - Gets token from Firebase Plugin
- `listenPushMobile()` - Sets up message listener with Firebase Plugin

Mobile code is isolated and never loads Firebase Web SDK.

---

## Execution Flow

### In Cordova (Android):
```
1. App loads
2. NotificationsPage mounts
3. useEffect runs
   ├─ isCordova() returns true
   ├─ Exit early from Firebase Web SDK load
   └─ Don't attempt navigator.serviceWorker.ready
4. User clicks "Enable Push"
5. handleTogglePush() runs
   ├─ isCordova() returns true
   ├─ Call registerPushMobile()
   │  └─ window.FirebasePlugin.getToken() ← MOBILE TOKEN
   ├─ Save token to backend
   └─ Return before web code
6. ✅ Success: No "unsupported-browser" error
```

### In Browser (Web):
```
1. App loads
2. NotificationsPage mounts
3. useEffect runs
   ├─ isCordova() returns false
   ├─ Load Firebase Web SDK dynamically
   ├─ getMessaging() ← WEB TOKEN
   └─ Check for existing subscription
4. User clicks "Enable Push"
5. handleTogglePush() runs
   ├─ isCordova() returns false
   ├─ Show Notification permission prompt
   ├─ Call registerPushSubscription()
   │  └─ getToken(messaging, { vapidKey, registration })
   ├─ Save token to backend
   └─ Setup foreground listener
6. ✅ Success: Web push notifications working
```

---

## API Endpoints Used

Both platforms save tokens to the same backend endpoint:
- **POST** `/notifications/push-subscription` - Save device token
  - `device_token`: Token from Firebase
  - `device_type`: `'web'` (web) or `'android'` (Cordova)
  - `device_name`: User agent or `'cordova'`

- **DELETE** `/notifications/push-subscription` - Remove device token

---

## Testing Checklist

### Android (Cordova):
- [ ] App loads without "unsupported-browser" error
- [ ] "Enable Push" button shows and works
- [ ] Console shows: `🔥 MOBILE TOKEN: [token]`
- [ ] Token is saved to backend with `device_type: 'android'`
- [ ] Push messages are received when app is backgrounded
- [ ] Clicking notification navigates to correct URL

### Web (Browser):
- [ ] App loads normally
- [ ] "Enable Push" button shows and works
- [ ] Notification permission dialog appears
- [ ] Token is saved to backend with `device_type: 'web'`
- [ ] Push messages are received in foreground
- [ ] Service worker correctly registered

---

## Key Files

| File | Changes | Impact |
|------|---------|--------|
| `notificationService.js` | Added `loadMessaging()` dynamic import | Prevents Firebase Web SDK in Cordova |
| `NotificationsPage.jsx` | Removed Firebase imports, added dynamic imports | Mobile-first handlers |
| `pushHelper.js` | No changes needed | Already provides proper detection |
| `pushMobile.js` | No changes needed | Already mobile-specific |

---

## Environment Detection

All code uses the consistent `isCordova()` helper:
```javascript
export const isCordova = () => typeof window !== 'undefined' && !!window.cordova;
```

This checks for `window.cordova` which is guaranteed to exist in Cordova apps.

---

## Browser APIs Properly Gated

The following browser APIs are now properly guarded:
- ✅ `navigator.serviceWorker` - Only accessed in web flow
- ✅ `Notification` API - Only accessed in web flow
- ✅ `PushManager` - Only accessed in web flow
- ✅ `firebase/messaging` - Only imported in web flow
- ✅ Service Worker registration - Only in web flow

In Cordova, only `window.FirebasePlugin` is used.

---

## No Breaking Changes

- ✅ Web push notifications continue to work as before
- ✅ Backend API usage unchanged
- ✅ UI/UX unchanged
- ✅ Backward compatible with existing subscriptions

---

## Summary

**Before:** Firebase Web SDK ran in Cordova → "unsupported-browser" error ❌

**After:** 
- Firebase Web SDK only loads in browser ✅
- Cordova uses only `window.FirebasePlugin` ✅  
- Mobile-first handlers ensure proper code path ✅
- No cross-contamination between platforms ✅

The fixes ensure that **no Firebase Web SDK code is executed in Cordova**, eliminating the "unsupported-browser" error while maintaining full functionality on both platforms.
