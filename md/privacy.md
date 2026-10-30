# Privacy Policy

Last updated: October 2026

**Vaulta** is designed from the ground up with a radical commitment to user privacy: **your data belongs to you, and nobody else.**

---

## 1. Zero External Telemetry

Unlike commercial authenticators or cloud-hosted services, Vaulta contains:

- **No tracking scripts or analytics engines** (no Google Analytics, Mixpanel, Sentry, or Segment).
- **No third-party content delivery networks (CDNs)**. All JavaScript, CSS, brand icons, and WOFF2 web fonts are bundled and served strictly from your own host.
- **No external API phone-homes**. Vaulta never calls home to verify license keys, report metrics, or transmit user behaviors.

---

## 2. Cryptographic Storage & Secrets

All two-factor shared secrets (`otpauth://` secrets, Base32 seeds) stored in the database are encrypted at rest using **AES-256-CBC** with a Message Authentication Code (HMAC-SHA256) via Laravel's native encrypter.

- Decryption occurs only in memory on the server during code computation or explicit authenticated vault exports.
- Passwords are encrypted using robust hashing algorithms (Bcrypt/Argon2id).
- WebAuthn passkey public credentials and challenge nonces conform strictly to W3C WebAuthn Level 2 specifications.

---

## 3. Client-Side Browser Processing

When you add accounts using QR codes:

- Images are processed **locally in your browser** via the browser's native `BarcodeDetector` or `Canvas` APIs.
- The raw image bytes and QR graphic are never uploaded to the server; only the parsed URI is submitted upon your confirmation.

---

## 4. Cookies & Local Storage

Vaulta uses minimal, essential client storage:

- `vaulta.palette`: Stored in `localStorage` and an unencrypted `vaulta_palette` cookie to persist your chosen retro/modern theme across browser sessions.
- Session Cookies: Secure, HTTP-only session cookies used solely for authenticated session state.
- **No advertising, tracking, or cross-site cookies** are ever generated.

---

## 5. Data Ownership & Deletion

You maintain complete ownership of your vault:

- **Full Export**: You can export your entire vault at any time in open JSON or encrypted AES-256 formats.
- **Account Deletion**: Deleting your account instantly purges all your authenticators, personal credentials, and session records from the database with zero retained copies.

---

## 6. Self-Host Operator Contact

Because Vaulta is self-hosted software, each instance is administered by its respective server operator. For security disclosures or questions regarding a specific instance, please reach out via the [Contact Page](/contact).
