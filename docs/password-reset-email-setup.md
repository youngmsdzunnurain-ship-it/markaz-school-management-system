# MARKAZ password-reset email (Gmail SMTP)

Password-reset emails are sent through authenticated Gmail SMTP using PHPMailer. The recipient is always the active Admin email currently stored in the `admins` table; it is not configured in `.env`.

## Install the mail dependency

From the project root, run:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
```

This creates `vendor/autoload.php`, which the password-reset mailer loads. Keep `vendor/` out of Git.

## Configure the Gmail account

1. Enable 2-Step Verification on the Gmail account that MARKAZ will send from.
2. Create a Google App Password for this application. Do not use the account's normal Google password.
3. Copy `.env.example` to `.env` if the project does not already have a local `.env`.
4. Set the following values privately in the server's `.env`:

```dotenv
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-markaz-gmail@gmail.com
SMTP_PASSWORD=your-16-character-app-password
MAIL_FROM=your-markaz-gmail@gmail.com
MAIL_FROM_NAME=MARKAZ
APP_URL=https://your-public-markaz-domain.example
```

For Gmail, the From address must be the authenticated Gmail account or an alias already configured in that account. The configured sender is separate from the Admin recipient address stored in MARKAZ.

Never commit `.env`, paste the App Password into chat, or use the normal Google account password. If the App Password is exposed, revoke it in Google Account security settings and generate a replacement.

## Important URL requirement

`APP_URL` must be the public base URL that the Admin can open from the device receiving the email. Do not use `localhost` or `127.0.0.1` for a reset email that will be opened on another device.

## Security behavior retained

- Only active, unfrozen Admin accounts can request a reset.
- The request response does not reveal whether an email is registered.
- Reset tokens remain random, SHA-256 hashed in storage, one-time-use, and valid for 30 minutes.
- Failed email delivery removes the newly created token.
- SMTP errors are written to the server error log; SMTP credentials are never returned to the browser.
