=== Zero Friction Login ===
Contributors: justwpthings
Donate link: https://justwpthings.com/donate/
Tags: login, passwordless, otp, magic link, authentication
Requires at least: 6.0
Tested up to: 6.9.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Passwordless authentication for WordPress using OTP and magic links, with configurable UI, redirects, SMTP, and security controls.

== Description ==

Zero Friction Login lets users sign in without passwords using one-time codes or magic links.

Core features:

* OTP login (numeric or alphanumeric)
* Magic link login
* Optional new user registration control
* Redirect controls after login/logout
* Optional plugin-level SMTP settings
* Design and branding controls from admin
* Rate limiting and lockout protections
* Audit logging for authentication events
* Shortcode-based frontend form: `[zero_friction_login]`

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to `Settings > Zero Friction Login`.
4. Configure login method, email/smtp, and design settings.
5. Add the shortcode `[zero_friction_login]` to your login page.

== Frequently Asked Questions ==

= Does this replace the default WordPress login? =

It can. You can enable forced custom login redirects from the plugin settings.

= Does it support WooCommerce? =

Yes. The plugin includes options to force login before checkout and can redirect common WooCommerce auth entry points.

= Can I disable registration and allow only existing users? =

Yes. Disable registration in General Settings to prevent new account creation.

= Is there protection against abuse? =

Yes. The plugin includes request rate limiting, temporary lockouts, OTP expiry, and audit logging.

== Screenshots ==

1. Admin settings (General).
2. OTP verification UI.
3. Design & Branding controls.

== Changelog ==

= 1.0.0 =

* Initial public release.
* Passwordless OTP and magic-link authentication.
* Admin settings for auth, redirects, SMTP, and UI styling.
* Frontend shortcode app and REST API endpoints.

== Upgrade Notice ==

= 1.0.0 =

Initial release.
