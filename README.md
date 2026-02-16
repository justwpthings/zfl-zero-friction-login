# Zero Friction Login - WordPress Plugin

**Version:** 1.0.0
**Requires PHP:** 8.0+
**Requires WordPress:** 6.0+

## Phase 1: Backend Foundation (Complete)

A secure, passwordless authentication system for WordPress with OTP and magic link support.

## Phase 2A: Admin Settings Panel (Complete)

Comprehensive admin settings panel for configuring the plugin with tabbed interface.

## Phase 2B: REST API Endpoints (Complete)

RESTful API for passwordless authentication with OTP and magic link support.

## Phase 2C: React Frontend (Complete)

Modern, responsive React frontend with TypeScript and Tailwind CSS.

## Plugin Structure

```
zero-friction-login/
├── zero-friction-login.php         # Main plugin file
├── includes/
│   ├── class-database.php          # Database schema and table management
│   ├── class-security.php          # OTP generation and verification
│   ├── class-rate-limiter.php      # Rate limiting and anti-abuse
│   ├── class-auth-handler.php      # WordPress authentication integration
│   ├── class-admin-settings.php    # Admin settings panel
│   ├── class-rest-api.php          # REST API endpoints
│   └── class-frontend.php          # Frontend shortcode and asset enqueuing
├── src/
│   └── frontend/
│       ├── App.tsx                 # Main React component
│       ├── LoginForm.tsx           # Login/Register form with tabs
│       ├── OTPInput.tsx            # OTP input with auto-focus
│       ├── api.ts                  # REST API service
│       ├── types.ts                # TypeScript interfaces
│       ├── index.tsx               # Entry point
│       └── index.css               # Tailwind CSS
├── assets/
│   ├── dist/                       # Built frontend assets
│   │   ├── manifest.json           # Vite manifest
│   │   ├── zfl-main.[hash].js      # Bundled JavaScript
│   │   └── zfl-main.[hash].css     # Bundled CSS
│   ├── js/
│   │   └── admin-settings.js       # Admin JavaScript
│   └── css/
│       └── admin-settings.css      # Admin CSS
├── vite.config.ts                  # Vite bundler configuration
├── tailwind.config.js              # Tailwind CSS configuration
├── tsconfig.json                   # TypeScript configuration
└── README.md
```

## Database Tables

### 1. wp_zfl_otps
Stores one-time passwords with secure hashing.

- `id` - Primary key
- `email_hash` - SHA256 hash of email (indexed)
- `otp_hash` - HMAC-SHA256 hash of OTP
- `type` - 'otp' or 'magic_link'
- `expires_at` - Expiration timestamp (indexed)
- `created_at` - Creation timestamp

### 2. wp_zfl_rate_limits
Manages rate limiting per email and IP.

- `id` - Primary key
- `identifier` - Unique identifier for email/IP (indexed)
- `counter` - Request counter
- `window_start` - Rate limit window start time
- `lockout_until` - Lockout expiration (nullable)

### 3. wp_zfl_audit_log
Security audit trail for all authentication events.

- `id` - Primary key
- `email` - User email (indexed)
- `event` - Event type (indexed)
- `ip` - Client IP address
- `user_agent` - Browser user agent
- `created_at` - Event timestamp

### 4. wp_zfl_guest_sessions
Temporary sessions for unregistered users.

- `id` - Primary key
- `token` - 64-character hex token (unique, indexed)
- `email` - User email
- `expires_at` - Session expiration
- `created_at` - Creation timestamp

## Security Features

### OTP Generation & Verification
- **Numeric OTPs:** 6 digits (default)
- **Alphanumeric OTPs:** 8 characters (for magic links)
- **Hashing:** HMAC-SHA256 using WordPress auth salt
- **Constant-time comparison:** Uses `hash_equals()` to prevent timing attacks
- **Transaction support:** SELECT FOR UPDATE for race condition prevention

### Rate Limiting
- **Email limits:**
  - 3 requests per hour
  - 5 requests per 30 seconds
- **IP limits:** 20 requests per hour
- **Automatic lockout:** 30 minutes after exceeding limits
- **Cleanup:** Automatic cleanup of expired records

### Database Security
- **Prepared statements:** All queries use `$wpdb->prepare()`
- **Proper indexing:** Optimized queries with composite indexes
- **Email hashing:** SHA256 for privacy
- **Transaction support:** ACID compliance for critical operations

## Class Methods

### ZFL_Security
- `generate_otp($length, $type)` - Generate random OTP
- `hash_otp($otp)` - HMAC-SHA256 hash
- `hash_email($email)` - SHA256 hash
- `store_otp($email, $otp, $type, $expiry_minutes)` - Store OTP securely
- `verify_otp($email, $otp)` - Verify with constant-time comparison
- `invalidate_previous_otps($email)` - Clear old OTPs
- `generate_magic_token()` - Generate 64-char hex token
- `store_guest_session()` - Create guest session
- `verify_guest_session()` - Validate guest token

### ZFL_Rate_Limiter
- `check_email_limit($email)` - Validate email rate limits
- `check_ip_limit($ip)` - Validate IP rate limits
- `record_attempt($identifier)` - Log authentication attempt
- `apply_lockout($identifier, $duration)` - Apply temporary lockout
- `get_client_ip()` - Get real client IP (proxy-aware)

### ZFL_Auth_Handler
- `request_otp($email, $type)` - Request OTP with rate limiting
- `verify_and_login($email, $otp)` - Verify OTP and authenticate
- `login_user($user)` - WordPress login integration
- `create_user_from_guest($email, $token)` - Convert guest to user
- `log_event($email, $event)` - Audit logging
- `get_audit_logs($email, $limit)` - Retrieve audit logs

### ZFL_Database
- `create_tables()` - Create all plugin tables
- `cleanup_expired_records()` - Maintenance cleanup

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate through the WordPress admin panel
3. Database tables are created automatically on activation

## Security Considerations

- All email addresses are normalized (lowercase, trimmed)
- OTPs are invalidated after use
- Previous OTPs are cleared when new ones are generated
- Rate limiting prevents brute force attacks
- Audit logging tracks all authentication events
- Guest sessions expire after 24 hours
- OTPs expire after 15 minutes

## Admin Settings Panel

Access the settings panel at: **Settings → Zero Friction Login**

### Tab 1: General Settings
- **Login Method:** Admin controls which method users see (6/8-digit numeric, 6/8-char alphanumeric, or magic link only)
- **OTP Expiry:** Configurable expiration time (1-60 minutes, default: 5)
- **Redirect After Login:** Same page, My Account, or custom URL
- **Redirect After Logout:** Same page, Home, or custom URL

### Tab 2: SMTP Settings
- **Enable Plugin SMTP:** Optional custom SMTP configuration
- **SMTP Configuration:** Host, port, username, password, encryption (TLS/SSL/None)
- **From Settings:** Custom from email and name

### Tab 3: Email Template
- **Subject Line:** Customizable with placeholders
- **Email Body:** Full HTML/text template support
- **Available Placeholders:**
  - `{OTP}` - One-time password code
  - `{MAGIC_LINK}` - Magic link URL
  - `{SITE_NAME}` - Website name
  - `{IP}` - User's IP address
  - `{BROWSER}` - User's browser
  - `{DEVICE}` - User's device type
  - `{TIME}` - Current timestamp

### Tab 4: Design & Branding
- **Logo Upload:** WordPress media library integration
- **Logo Width:** Configurable logo size (50-500px)
- **Colors:** Card background, overlay background, primary color, button styles
- **Typography:** Heading and body font selection (System Default, Roboto, Open Sans, Lato, Montserrat)

All settings are saved in `wp_options` with the `zfl_` prefix and include proper sanitization.

## REST API Endpoints

The plugin exposes 4 REST API endpoints under the namespace `zfl/v1`:

### 1. POST /wp-json/zfl/v1/request-auth

Request an OTP or magic link for authentication.

**Request Body:**
```json
{
  "email": "user@example.com"
}
```

**Response (Success - OTP):**
```json
{
  "success": true,
  "method": "otp",
  "otp_length": 6,
  "otp_type": "numeric",
  "expires_in": 300,
  "email_sent": true,
  "message": "OTP sent to your email."
}
```

**Response (Success - Magic Link):**
```json
{
  "success": true,
  "method": "magic_link",
  "expires_in": 300,
  "email_sent": true,
  "message": "Magic link sent to your email."
}
```

**Error Responses:**
- `400` - Invalid email address
- `429` - Rate limit exceeded
- `500` - Server error

### 2. POST /wp-json/zfl/v1/verify-otp

Verify OTP and authenticate the user.

**Request Body:**
```json
{
  "email": "user@example.com",
  "otp": "123456"
}
```

**Response (Success - Existing User):**
```json
{
  "success": true,
  "user_exists": true,
  "user_id": 123,
  "redirect_url": "https://example.com/my-account",
  "message": "Login successful."
}
```

**Response (Success - New User):**
```json
{
  "success": true,
  "user_exists": false,
  "guest_token": "abc123...",
  "email": "user@example.com",
  "message": "Verification successful. Account creation required."
}
```

**Error Responses:**
- `400` - Invalid email
- `401` - Invalid or expired OTP
- `429` - Rate limit exceeded

### 3. GET /wp-json/zfl/v1/verify-magic

Verify magic link token and authenticate the user (redirects).

**Query Parameters:**
- `token` (required) - Magic link token
- `email` (optional) - User email

**Behavior:**
- On success: Redirects to configured redirect URL
- On error: Redirects to home with error query parameter

**Error Query Parameters:**
- `?zfl_error=invalid_token` - Invalid or missing token
- `?zfl_error=rate_limited` - Too many requests
- `?zfl_error=invalid_or_expired` - Token expired or doesn't exist
- `?zfl_error=login_failed` - Login process failed

### 4. GET /wp-json/zfl/v1/config

Get public configuration for frontend integration.

**Response:**
```json
{
  "login_method": "6_digit_numeric",
  "otp_length": 6,
  "otp_type": "numeric",
  "expiry_seconds": 300,
  "site_name": "My Website",
  "turnstile_enabled": false
}
```

## Email Sending

The plugin sends emails using:
- WordPress default `wp_mail()` function (default)
- Custom SMTP configuration (if enabled in settings)

**Email Template Features:**
- Customizable subject line and body
- Placeholder replacement:
  - `{OTP}` - One-time password
  - `{MAGIC_LINK}` - Magic link URL
  - `{SITE_NAME}` - Website name
  - `{IP}` - User's IP address
  - `{BROWSER}` - User's browser (Firefox, Chrome, Safari, etc.)
  - `{DEVICE}` - User's device (Mobile, Tablet, Desktop)
  - `{TIME}` - Current timestamp

**SMTP Configuration:**
- Host and port
- Username and password
- Encryption (TLS, SSL, or None)
- Custom from email and name

## Rate Limiting

The REST API integrates with the rate limiter:
- **Email limits:** 3 requests per hour, 5 per 30 seconds
- **IP limits:** 20 requests per hour
- **Lockout:** 30 minutes after exceeding limits
- Returns `429` HTTP status when rate limited

## Security Features

- **Input validation:** All inputs sanitized and validated
- **Rate limiting:** Prevents brute force attacks
- **Exponential backoff:** Progressive delays on failed attempts
- **Constant-time comparison:** Prevents timing attacks
- **Audit logging:** All authentication events logged
- **Transaction support:** Race condition prevention
- **CORS:** Same-origin policy enforcement

## React Frontend

The plugin includes a modern React frontend built with TypeScript and Tailwind CSS.

### Tech Stack

- **React 18** - Modern React with hooks
- **TypeScript** - Type-safe development
- **Tailwind CSS** - Utility-first CSS framework
- **Vite** - Fast build tool with HMR
- **Content-hashed filenames** - Optimal caching

### Components

#### App.tsx
Main container component that manages global state and view flow.

**Features:**
- Fetches configuration from REST API on mount
- Manages view states (loading, login, otp, magic-link-sent, success)
- Toast notifications for user feedback
- Automatic redirects after successful authentication
- Gradient background with responsive layout

#### LoginForm.tsx
Login and registration form with tabbed interface.

**Features:**
- Two tabs: Login and Register
- Email validation
- Countdown timer after request (30s cooldown)
- Dynamic button text based on login method
- Responsive design
- Admin settings integration

#### OTPInput.tsx
Individual square input boxes for OTP entry.

**Features:**
- 6 or 8 boxes based on configuration
- Auto-focus next box on input
- Backspace moves to previous box
- Full paste support (distributes OTP across boxes)
- Visual feedback (active, filled, error states)
- Countdown timer showing OTP expiry
- Resend code with cooldown (30s)
- Automatic submission when complete

### Shortcode Usage

Add the login form to any page or post using the shortcode:

```
[zero_friction_login]
```

With custom redirect:

```
[zero_friction_login redirect="https://example.com/dashboard"]
```

### Building Frontend Assets

**Development:**
```bash
npm run dev
```

**Production build:**
```bash
npm run build
```

The build process generates content-hashed files in `assets/dist/`:
- `zfl-main.[hash].js` - Bundled JavaScript
- `zfl-main.[hash].css` - Bundled CSS
- `manifest.json` - Asset manifest for WordPress

### Asset Enqueuing

The plugin automatically enqueues frontend assets when the shortcode is present on a page:

1. Checks for `manifest.json` in `assets/dist/`
2. Reads asset filenames from manifest
3. Enqueues CSS and JS with proper dependencies
4. Localizes script with WordPress REST API nonce and URL

### TypeScript Types

All components use TypeScript for type safety:

```typescript
interface Config {
  login_method: LoginMethod;
  otp_length: number;
  otp_type: OTPType;
  expiry_seconds: number;
  site_name: string;
}

interface AuthResponse {
  success: boolean;
  method: 'otp' | 'magic_link';
  message: string;
  expires_in?: number;
}
```

### API Integration

The frontend communicates with the REST API through the `api.ts` service:

- `getConfig()` - Fetch plugin configuration
- `requestAuth(email)` - Request OTP or magic link
- `verifyOTP(email, otp)` - Verify OTP and authenticate

All API calls include WordPress nonce for security.

### Responsive Design

The frontend is fully responsive with:
- Mobile-first approach
- Breakpoints for tablets and desktops
- Touch-friendly input boxes
- Smooth animations and transitions

## Next Steps (Phase 3)

Future enhancements may include:
- WordPress login page override
- WooCommerce integration
- Social login providers
- Two-factor authentication
- User profile management
- Admin analytics dashboard

## License

GPL v2 or later
