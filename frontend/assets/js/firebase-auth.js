// Loaded only in Firebase mode. Auth SDK only: no Analytics/Firestore needed.
import {
  initializeApp,
  getApps,
} from "https://www.gstatic.com/firebasejs/12.19.0/firebase-app.js";
import {
  getAuth,
  setPersistence,
  browserSessionPersistence,
  createUserWithEmailAndPassword,
  signInWithEmailAndPassword,
  signOut,
  updateProfile,
  sendEmailVerification,
  sendPasswordResetEmail,
  reload,
  GoogleAuthProvider,
  signInWithPopup,
} from "https://www.gstatic.com/firebasejs/12.19.0/firebase-auth.js";

let ready;
export function firebaseClient(config) {
  ready ??= (async () => {
    const app =
      getApps().find((app) => app.name === "bta-store") ||
      initializeApp(config, "bta-store");
    const auth = getAuth(app);
    auth.languageCode = "vi";
    await setPersistence(auth, browserSessionPersistence);
    await auth.authStateReady();
    return {
      auth,
      createUserWithEmailAndPassword,
      signInWithEmailAndPassword,
      signOut,
      updateProfile,
      sendEmailVerification,
      sendPasswordResetEmail,
      reload,
      GoogleAuthProvider,
      signInWithPopup,
    };
  })().catch((error) => {
    ready = null;
    throw error;
  });
  return ready;
}
