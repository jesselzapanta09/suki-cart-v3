php artisan serve --host=192.168.123.2 --port=8000


cordova plugin add cordova-plugin-firebasex@16.1.0 --variable FIREBASE_ANALYTICS_COLLECTION_ENABLED=false --variable FIREBASE_CRASHLYTICS_COLLECTION_ENABLED=false

cordova platform add android@13.0.0


cordova plugin rm cordova-plugin-firebasex
cordova plugin add cordova-plugin-firebasex
🔧 Step 3 — Rebuild
cordova platform rm android
cordova platform add android
cordova run 


platforms/android/app/src/main/java/org/apache/cordova/firebase/FirebasePluginMessagingService.java

.bigLargeIcon(null)
.bigLargeIcon((android.graphics.Bitmap) null)

<!-- ADd this  on www.cordova-->
<script src="cordova.js"></script>