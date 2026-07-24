# Developer Toolbar

The Developer Toolbar is no longer part of this repository. It has been
extracted into the standalone package
[`zappzarapp/devtoolbar`](https://packagist.org/packages/zappzarapp/devtoolbar)
([GitHub](https://github.com/marcstraube/zappzarapp-php-devtoolbar)) and is
installed via Composer (`zappzarapp/devtoolbar: ^1.0`).

For feature documentation (collectors, panels, configuration, security model,
frontend bundle), see the package's own documentation.

## Integration in this Boilerplate

- **Activation**: Controlled by the `ENABLE_DEV_TOOLBAR` environment variable
  (plumbed through `compose.yaml` into the `php` container, default `false`; set
  it in `.env`). The package guard is fail-closed: without an explicit
  `ENABLE_DEV_TOOLBAR=true` it only activates when `APP_ENV`/`ENV` is a
  development environment, and never for CLI or AJAX requests.
- **Usage examples**: `src/php/App/Http/Controller/WelcomeController.php`
  (collector setup and demo data) and `src/php/App/Http/ExceptionHandler.php`
  (exception collection).
- **CSP**: The toolbar receives its nonce from `zappzarapp/security`
  (`NonceRegistry`), so it works with the boilerplate's Content Security Policy
  out of the box.
