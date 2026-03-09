# VPS Control Panel

Web-based VPS management panel using Terabix Cloud API.

## Features

- Dashboard with server list and quick power controls
- Server detail management (power, rename, rescue, VNC, settings)
- Build/Rebuild server with OS templates
- SSH Key management
- Task history viewer

## Tech Stack

- **Backend:** PHP 8.x (no framework, compatible with shared hosting)
- **Frontend:** Bootstrap 5, Bootstrap Icons, vanilla JavaScript
- **API:** Terabix Cloud API (https://cloud.terabix.net/api)
- **Theme:** Dark mode

## File Structure
vps-control/
├── config/
│   └── config.php          # App configuration
├── includes/
│   ├── Api.php              # Terabix API client
│   ├── Auth.php             # Authentication handler
│   ├── Security.php         # CSRF, XSS, encryption, rate limiting
│   └── helpers.php          # Helper functions
├── pages/
│   ├── dashboard.php        # Server list
│   ├── server-detail.php    # Server management
│   ├── ssh-keys.php         # SSH key management
│   ├── settings.php         # Account info
│   └── build.php            # Build/Rebuild server
├── ajax/
│   └── handler.php          # AJAX request handler
├── assets/
│   ├── css/
│   │   └── style.css        # Dark theme styles
│   └── js/
│       └── app.js           # Frontend JavaScript
├── .htaccess                # Apache config & security headers
├── index.php                # Main router
├── login.php                # Login page
└── logout.php               # Logout handler

## Installation

1. Upload all files to your hosting
2. Edit `config/config.php`:
   - Change `ENCRYPTION_KEY` to a random 64-char string
   - Set `APP_DEBUG` to `false` for production
3. Create `logs/` directory with write permission (chmod 755)
4. Access the site and login with your Terabix API token

## Security

- AES-256-CBC encrypted API token in session
- CSRF protection on all forms and AJAX
- XSS escaping on all output
- Session binding (user agent fingerprint)
- Rate limiting
- Login lockout after 5 failed attempts
- Secure cookie flags (HttpOnly, Secure, SameSite)
- Content Security Policy headers
- Input validation with whitelisted routes

## API Documentation

Based on Terabix Cloud API: https://cloud.terabix.net/api

## Future Plans

- [ ] Telegram Bot integration
- [ ] ISO management page
- [ ] Auto-refresh server status
- [ ] Multi-language support
