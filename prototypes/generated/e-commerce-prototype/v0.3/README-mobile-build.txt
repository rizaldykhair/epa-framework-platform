# Mobile Build Scaffold

This folder is a **static mobile-preview scaffold**, not a compiled APK/IPA.

- `mobile-preview.html` / `mobile-style.css` / `mobile-app.js` render an app-like
  preview in any browser (bottom navigation, card list) so stakeholders can review
  the mobile experience without a native build.
- Real APK/IPA generation needs a native build environment (Android SDK / Xcode)
  that is intentionally out of scope for this stage of the EPA Framework.
- Mobile APK/IPA Link is auto-filled as "Not built yet" until a real build step exists.

## Next stage (future, not implemented here)
Wrap this scaffold with Capacitor/Ionic (fastest path to a real Android/iOS build from
this same HTML/CSS/JS) or re-implement natively with Flutter, once this framework and
its demo/thesis validation are stable.