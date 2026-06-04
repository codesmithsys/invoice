# InvoiceApp

A clean, self-hosted invoice management app built with **PHP + MySQL**. Create, edit, and share professional A4-sized invoices with live preview, PDF export, QR codes, and shareable links.

![InvoiceApp](https://img.shields.io/badge/PHP-8.0%2B-777BB4) ![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1) ![License](https://img.shields.io/badge/license-MIT-green)

## Inspired by

This project's editor UX and invoice templates are inspired by **[EasyInvoicePDF](https://github.com/VladSez/easy-invoice-pdf)** by [Vlad Sazonau](https://vladsazon.com) — a fantastic browser-only invoice generator. If you want a no-sign-up, client-side alternative, check it out. InvoiceApp takes the same "live preview + instant PDF" approach and adds a **server-side backend** (PHP + MySQL) so you can store, search, duplicate, and share invoices with your team.

Differences from EasyInvoicePDF:
- **Persistent storage** — invoices are saved to MySQL, not just held in browser state
- **Multi-invoice management** — dashboard, search, filter, status tracking, duplicate
- **Shareable links** — tokenized public URLs to send clients
- **Your own data** — self-hosted, no third-party services required

## Features

- **Live preview editor** — see changes as you type (split-screen 33/67 layout)
- **Two invoice templates** — Classic (light, clean) and Modern (gradient header)
- **A4-sized PDF output** — print-ready, single-page
- **Shareable links** — generate tokenized URLs to share invoices with anyone
- **QR codes** — opt-in QR on invoice for payments/links
- **Custom logo upload** — with size slider, removable
- **Custom footer text** — per invoice
- **80+ currencies** — with proper symbols and names
- **Amount in words** — auto-generated from total
- **Discount + tax** — % or fixed discount, applied before tax
- **Payment tracking** — To pay / Paid / Left to pay
- **Search + filter** — by invoice number, client, or status
- **Duplicate invoice** — clone any invoice as a new draft

## Tech stack

- **Backend:** PHP 8+ (no framework, plain PHP)
- **Database:** MySQL 5.7+ / MariaDB
- **Frontend:** Vanilla JS, CSS, no build step
- **Server:** Apache (LAMP/WAMP/MAMP)
- **PDF:** Browser print-to-PDF (no extra libraries)

## Requirements

- PHP 8.0 or higher (with `pdo_mysql` extension)
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` (for clean URLs)
- ~10 MB disk space

## Installation

### 1. Clone the repo

```bash
git clone https://github.com/yourusername/invoice-app.git
cd invoice-app
```

### 2. Create the database

```bash
mysql -u root -p < setup.sql
```

This creates the `invoice` database with two tables: `invoices` and `invoice_items`.

### 3. Configure database credentials

```bash
cp config.example.php config.php
```

Edit `config.php` and fill in your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'invoice');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
```

### 4. Set permissions

The `uploads/` directory needs to be writable by the web server:

```bash
chmod 755 uploads/
chown www-data:www-data uploads/   # Apache on Debian/Ubuntu
```

### 5. Point your web server at the project root

**Apache** — Make sure `mod_rewrite` is enabled and `.htaccess` is allowed. The included `.htaccess` handles clean URLs (no `.php` extension needed).

**Nginx** — Add this to your server config:

```nginx
location / {
    try_files $uri $uri/ $uri.php?$query_string;
}
```

### 6. Open the app

Navigate to `http://localhost/invoice-app/` in your browser. You should see the dashboard with no invoices yet. Click **+ New Invoice** to create your first one.

## Configuration

### Customizing the app

- **Currency list** — edit the `currencies` array in `create.php` (search for `const currencies` or `let currencies`)
- **Templates** — each template is a JavaScript template literal in `create.php` (search for `if (currentTemplate === 'modern')`)
- **Logo directory** — change `uploads/` path in the file upload handler in `create.php`

### Environment variables (optional)

Instead of editing `config.php`, the app reads these env vars (used automatically by `config.php`):

| Variable | Default | Notes |
|----------|---------|-------|
| `DB_HOST` | _none — required for env mode_ | MySQL host |
| `DB_PORT` | `3306` | MySQL port |
| `DB_NAME` | `invoice` | Database name |
| `DB_USER` | `root` | Database user |
| `DB_PASS` | _empty_ | Database password |

If any of these are set, `config.php` uses them. Otherwise it falls back to `config.example.php`.

## Deploy to Railway

The repo includes a `Dockerfile` and `railway.json` for one-click deployment.

### 1. Create a new Railway project
1. Go to [railway.app/new](https://railway.app/new)
2. Click **Deploy from GitHub repo** → select `codesmithsys/invoice`

### 2. Add a MySQL database
1. In your Railway project, click **+ New** → **Database** → **MySQL**
2. Wait for it to provision (1-2 minutes)
3. Click the MySQL service → **Variables** tab. You'll see `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`

### 3. Wire env vars into the web service
Click your **web service** → **Variables** tab → add:

```
DB_HOST   = ${{MySQL.MYSQLHOST}}
DB_PORT   = ${{MySQL.MYSQLPORT}}
DB_NAME   = ${{MySQL.MYSQLDATABASE}}
DB_USER   = ${{MySQL.MYSQLUSER}}
DB_PASS   = ${{MySQL.MYSQLPASSWORD}}
```

(Railway auto-fills these from the linked MySQL service.)

### 4. Wait for first deploy
- The Dockerfile's entrypoint runs `setup.sql` automatically on first boot, creating the `invoices` and `invoice_items` tables
- Railway assigns a public URL like `https://invoice-production.up.railway.app`
- Open it — you should see the dashboard

### 5. (Optional) Custom domain
- Settings → **Domains** → **Custom Domain**
- Add your domain and update DNS as instructed

> **Note:** The free Railway trial includes $5 of credit. After that, the hobby plan starts at ~$5/month + usage. The MySQL plugin is the main cost driver.

## Usage

### Creating an invoice

1. Click **+ New Invoice**
2. Fill in invoice details, your info (From), client info (To)
3. Add line items
4. Optionally enable QR code, set discount, tax, payment details
5. Watch the live preview on the right
6. Click **Save Invoice**

### Sharing an invoice

1. Open any invoice
2. Click **Share** in the actions bar
3. Click **Generate Shareable Link**
4. Copy the link and send it to your client
5. Anyone with the link can view and download the invoice (no login required)
6. Click **Revoke Link** to disable access

### Printing / PDF

- From the editor: click **Download PDF** (opens print dialog → Save as PDF)
- From the invoice view: click **Print / PDF**
- From the share link: click **Download PDF**

The output is a single A4 page (210mm × 297mm) with all colors preserved.

## File structure

```
.
├── .htaccess                # Apache clean-URL rewrite rules
├── LICENSE                  # MIT license
├── README.md                # This file
├── config.example.php       # Database config template
├── setup.sql                # Database schema
├── create.php               # Editor page (split-screen)
├── index.php                # Dashboard (invoice list)
├── view.php                 # Invoice detail view (auth)
├── view-public.php          # Public invoice view (token-based)
├── share.php                # API for generating/revoking share links
├── duplicate.php            # Clone invoice handler
├── delete.php               # Delete invoice handler
├── style.css                # All app styles
├── favicon.svg              # App icon
├── uploads/                 # Logo storage (gitignored)
│   └── .gitkeep
```

## Security notes

This app is designed for **self-hosted, single-user use** (e.g., a freelancer or small business owner). It does **not** include:

- User authentication / multi-tenancy
- CSRF protection
- Rate limiting
- Input sanitization beyond `htmlspecialchars` on output

If you want to expose this to the public internet, you should:
1. Add authentication (e.g., HTTP Basic Auth, or a proper login system)
2. Add CSRF tokens to all forms
3. Add rate limiting on `share.php` and `view-public.php`
4. Use HTTPS (Let's Encrypt)
5. Move `uploads/` outside the web root and serve via PHP
6. Restrict `view.php` and the actions to authenticated users only

The **public share link** is intentionally token-based and read-only, so it's safe to share.

## Credits

- **[EasyInvoicePDF](https://github.com/VladSez/easy-invoice-pdf)** by [Vlad Sazonau](https://vladsazon.com) — inspiration for the editor UX, live preview, and invoice template design. Licensed under [AGPL-3.0](https://opensource.org/license/AGPL-3.0).
- QR code generation powered by [goqr.me API](https://goqr.me/api/).

## Contributing

Pull requests welcome! For major changes, please open an issue first to discuss.

## License

MIT — see [LICENSE](LICENSE) for details.
