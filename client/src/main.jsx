import { initializeApp } from "firebase/app";
import { getMessaging } from "firebase/messaging";
import React from "react";
import ReactDOM from "react-dom/client";
import App from "./App.jsx";
import "./index.css";

// 🔥 Firebase config (from your .env)
const firebaseConfig = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
  authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
  projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
  storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
  messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
  appId: import.meta.env.VITE_FIREBASE_APP_ID,
};

try {
  const app = initializeApp(firebaseConfig);
  console.log('[Firebase] Initialized');

  if ('serviceWorker' in navigator && 'Notification' in window) {
    const messaging = getMessaging(app);

    navigator.serviceWorker.register('/firebase-messaging-sw.js')
      .then((reg) => {
        console.log('[SW] Firebase SW registered:', reg);
      })
      .catch((err) => {
        console.error('[SW] Registration failed:', err);
      });
  }
} catch (err) {
  console.error('[Firebase] Init failed:', err);
}

ReactDOM.createRoot(document.getElementById("root")).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);