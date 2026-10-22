# Apache security hardening

## Protect application source and private files

The repository-root `.htaccess` disables directory listings, serves `obrs.php`
as the directory entry point, and denies direct web requests to development
directories, source/configuration artifacts, database scripts, and PHP files
that are intended only to be included by other pages. Public application pages,
assets, and authenticated PHP endpoints remain available. The `uploads`
directory has its own rule to deny all direct requests; attachments are served
through the authenticated `view_attachment.php` endpoint.

Apache must allow `.htaccess` overrides for the application directory and have
`mod_rewrite` enabled for the private-directory rules. On Ubuntu, the active
site configuration can use:

```apache
<Directory /var/www/html/OBRS>
    AllowOverride All
</Directory>
```

Enable rewrite with `sudo a2enmod rewrite`, run `sudo apache2ctl configtest`,
then reload Apache. On XAMPP, ensure `AllowOverride All` applies to the
application directory and restart Apache. Verify that `/OBRS/schema.sql`,
`/OBRS/face_verification/app.py`, and `/OBRS/.git/config` are denied while
`/OBRS/obrs.php` and `/OBRS/obrs.css` still load. Adjust `/OBRS` in these
example URLs if the application is installed at a different path.

The ZAP report found missing CSP headers on root-level `robots.txt` and
`sitemap.xml` responses and Apache version/signature disclosure. The PHP
application already sets a nonce-based CSP for its PHP pages. The Apache
configuration in `apache-obrs-security.conf` adds a restrictive policy only to
the reported root resources (and their `/OBRS/` equivalents), so it does not
override the application's per-request nonce policy.

## Ubuntu Apache

From the repository directory on the server:

```sh
sudo cp apache-obrs-security.conf /etc/apache2/conf-available/zz-obrs-security.conf
sudo a2enmod headers
sudo a2disconf obrs-security || true
sudo a2enconf zz-obrs-security
sudo apache2ctl configtest
sudo systemctl reload apache2
```

The `zz-` prefix makes Apache load these `ServerTokens` and `ServerSignature`
settings after Ubuntu's `security.conf`, which otherwise may override them.
If `a2disconf obrs-security` reports that the old config is not enabled, the
`|| true` lets the remaining setup continue. Only reload Apache if `configtest`
reports `Syntax OK`. Check the exact paths from the ZAP report and the
application-prefixed paths:

```sh
curl -sS -D - -o /dev/null http://localhost/robots.txt
curl -sS -D - -o /dev/null http://localhost/sitemap.xml
curl -sS -D - -o /dev/null http://localhost/OBRS/robots.txt
curl -sS -D - -o /dev/null http://localhost/OBRS/sitemap.xml
```

The responses should include the restrictive `Content-Security-Policy` header.
The `Server` header should no longer include the Apache version, and Apache
generated error pages should not include a server signature. ZAP can still
report the generic `Apache` product name; `ServerTokens Prod` hides version and
platform details but does not remove the header entirely.

## Protect uploaded ID images

Uploaded resident ID images contain sensitive personal information. The
`uploads/.htaccess` file disables directory listings and direct HTTP access;
authorized dashboard pages serve images through `view_attachment.php`, which
checks the logged-in staff session or resident ownership. Ensure Apache permits
the `.htaccess` directives for this directory. On Ubuntu, the default
`/var/www/html` configuration may disable overrides; add this to the active
site configuration instead if needed:

```apache
<Directory /var/www/html/OBRS/uploads>
    Options -Indexes
    AllowOverride None
    Require all denied
</Directory>
```

Run `sudo apache2ctl configtest` and reload Apache after changing its
configuration. On XAMPP, ensure the `htdocs` directory allows the
`uploads/.htaccess` file to take effect, then restart Apache. Verify that a
direct image URL and `/OBRS/uploads/` both return `403 Forbidden`; authorized
users should still be able to view attachments from the dashboard or My
Requests page.

## XAMPP on Windows

The same server-banner directives must be applied to the active Apache server
configuration, not just the project directory. In XAMPP's Apache configuration,
set:

```apache
ServerTokens Prod
ServerSignature Off
```

Keep the `mod_headers` `LocationMatch` policy from
`apache-obrs-security.conf` in an included Apache configuration file, then
restart Apache and verify the response headers before rescanning.

## Rescan scope

Scan the actual application URL (for example, `http://192.168.1.29/OBRS/`) as
well as the root URLs reported by ZAP if they are in scope. A report against
`http://localhost/` is not equivalent to a scan of the deployed application.
The informational user-controlled-attribute alerts should be rechecked if
those rendering paths change; the reviewed report did not demonstrate script
execution.
