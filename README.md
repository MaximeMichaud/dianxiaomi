# Dianxiaomi Enhanced for WooCommerce

[![WPCS](https://github.com/MaximeMichaud/dianxiaomi/actions/workflows/wpcs.yml/badge.svg)](https://github.com/MaximeMichaud/dianxiaomi/actions/workflows/wpcs.yml)
[![PHPStan](https://github.com/MaximeMichaud/dianxiaomi/actions/workflows/phpstan.yml/badge.svg)](https://github.com/MaximeMichaud/dianxiaomi/actions/workflows/phpstan.yml)
[![Psalm](https://github.com/MaximeMichaud/dianxiaomi/actions/workflows/psalm.yml/badge.svg)](https://github.com/MaximeMichaud/dianxiaomi/actions/workflows/psalm.yml)
[![PHPUnit](https://github.com/MaximeMichaud/dianxiaomi/actions/workflows/phpunit.yml/badge.svg)](https://github.com/MaximeMichaud/dianxiaomi/actions/workflows/phpunit.yml)

Enhances WooCommerce by adding tracking numbers and carrier names to orders,
with automatic sync to the [Dianxiaomi](https://www.dianxiaomi.com/) platform.

## Download

**[Download Latest Release](https://github.com/MaximeMichaud/dianxiaomi/releases/latest/download/dianxiaomi.zip)**

## About This Fork

This is our maintained version of the Dianxiaomi plugin. We are releasing it
as open source to share our work with the community and benefit from
collaborative improvements.

**What we maintain:**

- PHP 8.1+ compatibility
- WooCommerce 11.x compatibility
- HPOS (High-Performance Order Storage) support
- Modern WordPress code quality standards

## Requirements

| Software     | Minimum Version | Tested Up To |
|--------------|-----------------|--------------|
| WordPress    | 5.8             | 7.1.4        |
| WooCommerce  | 8.0             | 11.2.2       |
| PHP          | 8.1             | 8.5          |

> **Note:** Static analysis runs on PHP 8.1 through 8.5. Integration tests run
> on PHP 8.1 with both compatibility stacks and on PHP 8.5 with the current
> stack. These are automated checks, not a claim of production testing on
> every combination.

## Code Quality

This plugin follows strict code quality standards:

| Tool     | Level/Standard                    |
|----------|-----------------------------------|
| PHPCS    | WordPress Coding Standards (WPCS) |
| PHPStan  | Level 10                          |
| Psalm    | Level 8                           |
| PHPUnit  | Unit & Integration tests          |

## Installation

1. [Download the latest release](https://github.com/MaximeMichaud/dianxiaomi/releases/latest/download/dianxiaomi.zip)
2. Upload the `dianxiaomi` folder to `/wp-content/plugins/`
3. Activate the plugin in **Plugins > Installed Plugins**

## Contributing

Contributions are welcome. Please submit issues or pull requests on GitHub.

### Local checks

Development requires Docker, Composer, Node.js 24.18 or later, and npm 11.16
or later. From the `dianxiaomi` directory:

```sh
composer install
npm ci
npm run check:compatibility
composer lint
composer audit --locked
npm audit
npm run start
npm test
npm run stop
```

Use `start:legacy`, `test:legacy`, and `stop:legacy` for the older compatibility
stack. To test PHP 8.5, set `WP_ENV_PHP_VERSION=8.5` for each wp-env command.

The `.wp-env.*.json` files define the integration test versions. Renovate groups
current stack updates with the plugin's tested-version headers, constants, and
this requirements table. `check:compatibility` rejects inconsistent metadata;
the workflow also checks the versions actually installed before running tests.

The npm overrides for `rimraf` and `qs` fix transitive security advisories until
wp-env and Express accept the patched versions. Remove them once the upstream
dependency ranges permit those versions and `npm audit` remains clean.
PHPUnit stays on 10.x for PHP 8.1. WordPress stubs stay on 6.x until the analysis
integrations support 7.x; PHP_CodeSniffer stays on 3.x until WPCS supports 4.x.

## Contributors

- [MaximeMichaud](https://github.com/MaximeMichaud)
- [whywilson](https://github.com/whywilson)

## License

This plugin is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Credits

Original plugin by Dianxiaomi.
