import React from "react";
import ReactDOM from "react-dom/client";
import App from "./App.jsx";
import "./index.css";

import {
    setUpMessageListener,
    registerPushSubscription,
} from "./services/notificationService";

import {
    registerPushMobile,
    listenPushMobile,
} from "./services/pushMobile";

// 🔥 Firebase config
const firebaseConfig = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
};

// ─────────────────────────
// 🌐 WEB PUSH
// ─────────────────────────
async function initializeWebPush() {
    try {
        const { initializeApp } = await import("firebase/app");
        initializeApp(firebaseConfig);

        console.log("[Firebase] Initialized (Web)");

        if ("serviceWorker" in navigator && "Notification" in window) {
            navigator.serviceWorker
                .register("/firebase-messaging-sw.js")
                .then((reg) => console.log("[SW] Firebase SW registered:", reg))
                .catch((err) => console.error("[SW] Registration failed:", err));

            setUpMessageListener();
            registerPushSubscription();
        }
    } catch (err) {
        console.error("[Firebase] Init failed:", err);
    }
}

// ─────────────────────────
// 🧠 WAIT FOR CORDOVA OR FALLBACK
// ─────────────────────────
function waitForCordovaOrTimeout(timeout = 3000) {
    return new Promise((resolve) => {
        let resolved = false;

        // 📱 Cordova detected
        document.addEventListener("deviceready", () => {
            if (!resolved) {
                resolved = true;
                resolve("cordova");
            }
        });

        // 🌐 Fallback to web
        setTimeout(() => {
            if (!resolved) {
                resolved = true;
                resolve("web");
            }
        }, timeout);
    });
}

// ─────────────────────────
// 🚀 INIT APP
// ─────────────────────────
waitForCordovaOrTimeout().then((mode) => {
    if (mode === "cordova") {
        console.log("📱 Cordova ready → MOBILE MODE");

        registerPushMobile();
        listenPushMobile();
    } else {
        console.log("🌐 Web detected → init push");

        initializeWebPush();
    }
});

// ─────────────────────────
// ⚛️ REACT APP
// ─────────────────────────
ReactDOM.createRoot(document.getElementById("root")).render(
    <React.StrictMode>
        <App />
    </React.StrictMode>
);