# Mobile app integration guide

CasaClick does not ship a native project inside this repository. Any iOS/Android client can integrate using HTTPS + JSON + JWT. Below is a minimal **JavaScript (React Native / Expo–style)** flow you can translate to Dart, Kotlin, or Swift.

## Base URL

Use your server origin, e.g. `https://api.example.com` or dev `http://10.0.2.2:8000` (Android emulator) / device LAN IP.

Set the same origin in server **CORS** (`CORS_ALLOW_ORIGIN` in `.env`).

## 1. Register and verify

- `POST /api/mobile/register`  
  Body: `{ "name", "email", "password", "role": "ROLE_TENANT" }`  
  Or use `POST /api/register` with `username`, `email`, `password`.

- Open the link from the verification email, or  
  `GET /api/mobile/verify-email/{token}`

Until verified, **login will fail** (by design).

## 2. Login (JWT)

```http
POST /api/login_check
Content-Type: application/json

{ "email": "tenant@example.com", "password": "your-password" }
```

Response contains `token`. Send on every protected request:

```http
Authorization: Bearer <token>
```

## 3. Browse listings (no auth)

```javascript
const base = 'https://your-host';
const res = await fetch(`${base}/api/mobile/listings`);
const json = await res.json();
// json.success, json.data[], json.meta.count
```

Image paths are site-relative (e.g. `/uploads/images/...`); prepend `base` when building URIs.

## 4. Customer profile and bookings

```javascript
const headers = {
  Authorization: `Bearer ${token}`,
  'Content-Type': 'application/json',
};

// Profile
let r = await fetch(`${base}/api/mobile/customer/profile`, { headers });
// PATCH name
r = await fetch(`${base}/api/mobile/customer/profile`, {
  method: 'PATCH',
  headers,
  body: JSON.stringify({ name: 'New Name' }),
});

// Create booking
r = await fetch(`${base}/api/mobile/customer/bookings`, {
  method: 'POST',
  headers,
  body: JSON.stringify({
    listingId: 1,
    message: 'I would like to view the unit.',
  }),
});

// List bookings
r = await fetch(`${base}/api/mobile/customer/bookings`, { headers });
```

## 5. Orders & payments

- **Orders:** `GET /api/mobile/customer/orders` — applications in `approved` or `completed` status.
- **List payments:** `GET /api/mobile/customer/payments`
- **Submit payment** (after landlord approved the application):

```javascript
await fetch(`${base}/api/mobile/customer/payments`, {
  method: 'POST',
  headers,
  body: JSON.stringify({
    applicationId: 5,
    paymentMethod: 'gcash', // cash | bank_transfer | gcash | paymaya | credit_card
    amount: '12000.50',     // optional; defaults to listing price
    notes: 'Ref ABC123',
  }),
});
```

Landlord confirms payment in the **web** dashboard; until then status stays `pending`.

## 6. Synchronization with web

After actions on web (approve application, complete payment), call the same list/show endpoints again. There is a single database — no merge logic required on the device beyond normal refresh.

## 7. Error handling

Always check `res.ok` and parse JSON:

```javascript
const json = await res.json();
if (!json.success) {
  const msg = json.error || (json.errors && json.errors.join(', '));
  // show msg to user
}
```

HTTP codes: `401` missing/invalid JWT, `403` wrong role (e.g. admin token on customer routes), `404` missing resource, `409` conflict (duplicate booking), `422` business rule (e.g. payment before approval).
