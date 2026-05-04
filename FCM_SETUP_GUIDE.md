# FCM Migration Guide

This project has been migrated from VAPID-based Web Push Notifications to Firebase Cloud Messaging (FCM).

## What Changed

### Backend
- ✅ Replaced WebPush library with Firebase Admin SDK via HTTP
- ✅ Updated PushSubscription model (VAPID endpoint → FCM device token)
- ✅ Created FCMService for Firebase integration
- ✅ Updated NotificationHelper to use FCM
- ✅ Removed VAPID configuration
- ✅ Database migration converts old push subscriptions to FCM format

### Frontend
- ✅ Updated notificationService to use Firebase SDK
- ✅ Added Firebase initialization in main.jsx
- ✅ Updated service worker for FCM background messages
- ✅ All components (NotificationBell, NotificationsPage) work with FCM

## Setup Instructions

### 1. Create Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com)
2. Click "Add project" and follow the wizard
3. Name your project (e.g., "SukiCart")
4. Accept default settings and create the project

### 2. Set Up Firebase Web App

1. In Firebase Console, click the **Settings icon** (⚙️) → **Project Settings**
2. Under "Your apps", click **Add app** → **Web** (</>)
3. Register app with any name (e.g., "SukiCart Web")
4. You'll get a Firebase config with these keys:
   - `apiKey`
   - `authDomain`
   - `projectId`
   - `storageBucket`
   - `messagingSenderId`
   - `appId`

5. Copy these values to `client/.env.local` (see below)

### 3. Enable Cloud Messaging

1. In Firebase Console, go to **Cloud Messaging** tab
2. Click **"Manage API"** link
3. Enable the API
4. Go back to Cloud Messaging tab
5. Under "Web Push certificates", generate or get your **Web Push certificates**
6. Copy the **Key pair** (Public Key) to `client/.env.local` as `VITE_FIREBASE_VAPID_KEY`

### 4. Create Firebase Service Account

1. In Firebase Console, click **Settings** (⚙️) → **Service accounts**
2. Click **Generate new private key** button
3. A JSON file will download - open it
4. Copy the ENTIRE JSON content (it's a large JSON object)
5. This will go in your backend `.env` file

### 5. Backend Configuration

Create/update your `.env` file in the `api/` folder:

```bash
# Get these from Firebase Service Account JSON file
FCM_PROJECT_ID=your-firebase-project-id
FCM_SERVICE_ACCOUNT_JSON={"type":"service_account","project_id":"your-project-id","private_key":"-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n","client_email":"firebase-adminsdk-xxxxx@your-project-id.iam.gserviceaccount.com","client_id":"123456789","auth_uri":"https://accounts.google.com/o/oauth2/auth",...}
```

**Important**: Make sure to escape or properly format the JSON. If you have issues, you can store this in a JSON file and reference it.

### 6. Frontend Configuration

Create `client/.env.local`:

```bash
VITE_FIREBASE_API_KEY=your-api-key
VITE_FIREBASE_AUTH_DOMAIN=your-project.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=your-project-id
VITE_FIREBASE_STORAGE_BUCKET=your-project.appspot.com
VITE_FIREBASE_MESSAGING_SENDER_ID=your-messaging-sender-id
VITE_FIREBASE_APP_ID=your-app-id
VITE_FIREBASE_VAPID_KEY=your-vapid-public-key
```

### 7. Install Dependencies

**Backend**:
```bash
cd api
composer install
php artisan migrate
```

**Frontend**:
```bash
cd client
npm install
```

### 8. Testing

1. Start your backend server
2. Start your frontend dev server
3. Log in or create an account
4. When prompted, allow notifications
5. Send a test notification:
   ```bash
   # From API folder, use tinker or create a test
   php artisan tinker
   > App\Helpers\NotificationHelper::send(1, 'system', 'Test', 'This is a test notification', ['test' => true]);
   ```

## API Endpoints

The following endpoints work with FCM:

- **POST** `/api/notifications/push-subscription` - Register/update device token
  ```json
  {
    "device_token": "fcm-token-here",
    "device_type": "web",
    "device_name": "Chrome on Windows"
  }
  ```

- **DELETE** `/api/notifications/push-subscription` - Unregister device
  ```json
  {
    "device_token": "fcm-token-here"
  }
  ```

## Old Files to Clean Up

The following files are no longer needed:

- `api/generate_vapid.php` - Can be deleted
- Old VAPID keys in `.env` - Can be removed

## Troubleshooting

### "FCM is not configured" warning
- Make sure `FCM_PROJECT_ID` and `FCM_SERVICE_ACCOUNT_JSON` are set in `.env`
- Check that the service account JSON is valid

### Tokens not being saved
- Check that frontend Firebase config is correct
- Verify that VAPID key is set in `.env.local`
- Check browser console for Firebase errors

### Notifications not being sent
- Verify service account has correct permissions (should have by default)
- Check that device tokens exist in database: `PushSubscription` table
- Check logs: `storage/logs/laravel.log`

### Service Worker Issues
- Make sure `firebase-messaging-sw.js` and `sw.js` are in `client/public/`
- Check browser DevTools > Application > Service Workers
- Clear browser cache if you see stale SW

## Database Migration

Run the migration to convert old subscriptions:

```bash
php artisan migrate
```

This will:
- Drop old VAPID columns (endpoint, public_key, auth_token)
- Add new FCM columns (device_token, device_type, device_name, last_used_at)
- All old data will be lost (expected, as VAPID tokens aren't compatible with FCM)

To rollback:
```bash
php artisan migrate:rollback
```

## Notes

- FCM is free and more reliable than VAPID
- Works on web, Android (via React Native), and iOS (via React Native)
- No additional costs for push notifications
- Firebase handles token expiry and automatic cleanup
- Tokens are specific to each browser/device installation
