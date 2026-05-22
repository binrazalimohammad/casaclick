# Web Dev 2 Features - Implementation Summary

## 1. Landing Page (10 pts) ✅
- **Route:** `/home`
- **Sections:** Hero, Features (3 cards), How It Works (3 steps), Stats (4 metrics), Testimonials (3), CTA
- **Responsive:** Mobile-friendly with flexbox/grid, media queries
- **Root `/`** redirects to landing

## 2. About Page (10 pts) ✅
- **Route:** `/about`
- **Meet the Team:** 4 members with names and positions (Maria Santos, John Davis, Sarah Lee, Michael Chen)
- **Consistent branding:** CasaClick colors, fonts
- **Layout:** Responsive team grid, values section

## 3. Contact Us Page (10 pts) ✅
- **Route:** `/contact`
- **Form:** Google Forms embed (create your form at forms.google.com and replace the iframe src in `templates/contact/index.html.twig`)
- **Feedback:** Google shows "Your response has been recorded" on submit
- **Alternative:** Can switch to Formspree or Brevo by replacing the iframe

## 4. Admin & Staff UI / Bug Fixes (15 pts) ✅
- **UserChecker:** Disabled users cannot log in (proper error message)
- **Registration redirect:** Non-admin users go to product index, not dashboard
- **MichaelController:** Fixed hero background path (was absolute local path)
- **Admin page:** Improved with links to Manage Users and Activity Logs (Admin only)
- **Base template:** Staff role badge, refined navigation

## 5. Staff & Admin Workflows / RBAC (15 pts) ✅
- **ROLE_STAFF** added to hierarchy (between Admin and Landlord)
- **Staff can:** Dashboard, Admin Area (limited), Pending Listings (view), Active Listings
- **Admin only:** Manage Users, Activity Logs, Pending approval/reject
- **Distinct nav:** Staff sees "Admin Area" but not "Manage Users" or "Activity Logs"

## 6. Google OAuth (Staff) (15 pts) ✅
- **Route:** `/connect/google` → redirects to Google
- **Callback:** `/connect/google/check`
- **Setup:** Add to `.env.local`:
  ```
  GOOGLE_CLIENT_ID=your_client_id
  GOOGLE_CLIENT_SECRET=your_client_secret
  ```
  Get credentials at https://console.cloud.google.com/apis/credentials
- **Behavior:** New users get ROLE_STAFF; existing users linked by email get email auto-verified
- **Login page:** "Sign in with Google (Staff)" button

## 7. Email Verification (Web & API) (15 pts) ✅
- **User fields:** `emailVerified`, `verificationToken`, `googleId`
- **Web registration:** Sends verification email; link `/verify-email/{token}` marks verified
- **API registration:** Same flow; returns `emailVerified: false` until verified
- **API verify:** `GET /api/mobile/verify-email/{token}` returns JSON
- **Configure MAILER_DSN** in `.env` for real email (e.g. `MAILER_DSN=smtp://...`)

## 8. Mobile & customer API (15+ pts) ✅
Base URL: `/api/mobile`

**Public**

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/listings` | GET | Approved listings (id, name, price, description, image, category) |
| `/listings/{id}` | GET | Single listing details |
| `/categories` | GET | All categories |
| `/register` | POST | Register (JSON: name, email, password, role) |
| `/verify-email/{token}` | GET | Verify email via token |

**Customer (JWT, tenant / ROLE_USER only — not staff/admin/landlord)**

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/customer/profile` | GET, PATCH | Profile; update `name` |
| `/customer/bookings` | GET, POST | List / create booking (application) |
| `/customer/bookings/{id}` | GET | Single booking + nested payments |
| `/customer/orders` | GET | Approved / completed bookings (“orders”) |
| `/customer/payments` | GET, POST | List payments; submit pending payment |

**Authenticated (any role)**

- `/me` — current user JSON

See `docs/RUBRIC_COVERAGE.md`, `docs/MOBILE_APP_INTEGRATION.md`, and Postman folder **Customer API (tenant only)**.

**Response format:**
```json
{
  "success": true,
  "data": [...],
  "meta": { "count": n }
}
```

---

## Running the Project

1. **Migrations:** `php bin/console doctrine:migrations:migrate`
2. **Create Staff user (Admin UI):** Log in as Admin → Manage Users → New → Role: Staff
3. **Google OAuth:** Add credentials to `.env.local`
4. **Contact form:** Replace Google Form iframe src in `templates/contact/index.html.twig`
5. **Email:** Set `MAILER_DSN` for verification emails
