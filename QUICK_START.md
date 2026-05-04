# Quick Implementation Checklist

## Phase 1: Firebase Setup (5-10 minutes)
- [ ] Create Firebase project at https://console.firebase.google.com
- [ ] Add Web app to project
- [ ] Copy Firebase config (apiKey, projectId, etc.)
- [ ] Enable Cloud Messaging API
- [ ] Generate Web Push Certificate and get public VAPID key
- [ ] Create Service Account and download JSON key

## Phase 2: Backend Configuration (5 minutes)
- [ ] Copy `api/.env.example` to `api/.env` (if not exists)
- [ ] Add `FCM_PROJECT_ID=your-project-id` to `.env`
- [ ] Add `FCM_SERVICE_ACCOUNT_JSON=...` to `.env` (entire JSON from service account)
- [ ] Run `cd api && composer install`
- [ ] Run `php artisan migrate`

## Phase 3: Frontend Configuration (5 minutes)
- [ ] Copy `.env.local.example` to `client/.env.local`
- [ ] Fill in all Firebase config values from web app
- [ ] Add VAPID key from Web Push Certificate
- [ ] Run `cd client && npm install`

## Phase 4: Testing (10 minutes)
- [ ] Start backend: `cd api && php artisan serve`
- [ ] Start frontend: `cd client && npm run dev`
- [ ] Login to application
- [ ] Grant notification permission when prompted
- [ ] Check browser DevTools > Application > Service Workers (should see 2 SW)
- [ ] Test push notification:
  ```bash
  cd api
  php artisan tinker
  > App\Helpers\NotificationHelper::send(1, 'system', 'Test', 'Hello!');
  ```
- [ ] Verify notification appears

## Important Notes

- **FCM_SERVICE_ACCOUNT_JSON** must be valid JSON in `.env` - ensure proper escaping
- **VAPID_KEY** should be the PUBLIC key from Web Push Certificate, not the private one
- Old VAPID keys in `.env` can be removed but aren't required
- Database will have all old push subscriptions cleared on migration (expected)
- Two service workers will be registered (main SW + Firebase messaging SW)

## Files Changed Summary

### Backend
✅ Created:
- `database/migrations/2026_05_03_000001_convert_push_subscriptions_to_fcm.php`
- `app/Services/FCMService.php`

✅ Modified:
- `app/Models/PushSubscription.php`
- `app/Helpers/NotificationHelper.php`
- `app/Http/Controllers/Notification/NotificationController.php`
- `config/app.php`
- `routes/api.php`
- `.env.example`
- `composer.json`

🗑️ Can delete:
- `generate_vapid.php`

### Frontend
✅ Created:
- `public/firebase-messaging-sw.js`
- `.env.local.example`

✅ Modified:
- `src/services/notificationService.js`
- `src/main.jsx`
- `package.json`

✅ No changes needed:
- `src/components/NotificationBell.jsx`
- `src/pages/notifications/NotificationsPage.jsx`
- `src/pages/auth/Login.jsx`

## Rollback (if needed)

```bash
# Backend
cd api
php artisan migrate:rollback
git checkout app/Models/PushSubscription.php app/Helpers/NotificationHelper.php app/Http/Controllers/Notification/NotificationController.php config/app.php routes/api.php .env.example composer.json
composer install

# Frontend
cd client
git checkout src/services/notificationService.js src/main.jsx package.json
npm install
```

## Support

For detailed setup instructions, see: **FCM_SETUP_GUIDE.md**
For complete migration details, see: **MIGRATION_SUMMARY.md**
