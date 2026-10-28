# AutoAlt.ai for TYPO3

Version **1.0.8** · Extension key `alt_text_generator` · GPL-2.0-or-later

Generate image alternative text, titles, descriptions, and SEO-friendly filenames
through AutoAlt.ai from TYPO3's File Abstraction Layer (FAL). Editors can review
results in the backend. The extension metadata marks this release as stable;
the declared compatibility range is not a certification of every TYPO3/PHP combination.

## Features

- Backend dashboard with image statistics, generation defaults, and credits.
- Single-image generation from the metadata form and selected Filelist items.
- Browser-driven bulk generation with independent Generate, Keep, or Clear actions.
- Automatic generation and optional AI filename renaming on upload.
- Bulk filename review, manual or AI renaming, rename history, and supported undo.
- Writing style, length, prefix/suffix, keywords, and custom prompt controls.
- Generation history, inline editing, retries, and error diagnostics.
- Site-language metadata workflows and optional completion email notifications.
- TYPO3 FAL access checks and User TSconfig feature permissions.
- Default-off diagnostic sharing and a separate, confirmed one-time support report.

Review generated text in its page context. Keep the bulk-processing browser tab
open while work is running. AI operations require an AutoAlt.ai account, a valid
API key, available credits, and outbound HTTPS access.

## Requirements

The existing compatibility declarations are preserved:

| Component | Declared requirement |
| --- | --- |
| TYPO3 core, backend, filelist, fluid | `>=13.0 <14.4` (Extension Manager: 13.0.0–14.3.99) |
| PHP | `^8.2` (8.2 through 8.x; not PHP 9) |
| PHP extensions | JSON and mbstring, plus the requirements of the installed TYPO3 version |
| PSR logging | `psr/log ^3.0` |
| Installation | Composer-based TYPO3 project or a supported classic TYPO3 installation |

Use the PHP/database combination required by your selected TYPO3 version.
The declared range is not a record of tested combinations. No application,
database, or live API integration environment is bundled with this archive.
The privacy behavior suite uses mocked TYPO3 adapters, persistence, and HTTP.

## Installation

Install the public package from your TYPO3 project root:

```bash
composer require autoaltai/alt-text-generator:^1.0.8
vendor/bin/typo3 extension:setup
```

The Composer package name is `autoaltai/alt-text-generator` and the extension key
is `alt_text_generator`. No private GitHub access or custom VCS repository is
required. Apply any pending extension schema changes through TYPO3's Database
Analyzer and flush TYPO3 caches after installation or update.

For an existing classic TYPO3 13 installation, extract the extension contents
into `typo3conf/ext/alt_text_generator/`, activate it in Extension Manager, and
apply the schema. Do not copy the backup ZIP or development tools into that folder.
The source archive uses the extension files at its root, not a nested project directory.

## Configuration

Open **File > AutoAlt.ai** in TYPO3 13 or **Media > AutoAlt.ai** in TYPO3 14,
then **Extension Configuration**. Connect an API key or use email verification.
Settings are stored in the extension's database configuration table.

Review these defaults before uploading images:

| Setting | Default |
| --- | --- |
| Extension enabled / automatic generation on upload | Enabled |
| Automatic filename rename on upload | Disabled |
| Overwrite existing alternative text | Enabled |
| Generate title / description | Enabled |
| Requested alternative-text length | 100–150 characters |
| Short alternative-text threshold | 40 characters |
| Allowed image extensions | `jpg,jpeg,png,webp,gif,avif,svg` |
| Completion email notification | Disabled |
| Share diagnostic data with AutoAlt.ai | Disabled |

Writing style, prefix/suffix, SEO keywords, negative keywords, and a custom prompt
are optional. Language selection follows TYPO3 site languages. The API endpoint,
30-second request timeout, enabled API-error logging, and use of image content
rather than public image URLs are fixed by the code, not configurable options.

Backend users need module access, appropriate FAL mounts, and metadata permissions.
Optional User TSconfig controls:

```typoscript
tx_alttextgenerator.permissions {
    generateSingle = 1
    runBulkGeneration = 1
    manageSettings = 0
}
```

Start with a small batch and review results before enabling broader processing.
See [configuration](Documentation/Configuration/Index.rst) and
[administration](Documentation/Administration/Index.rst) for details.

## Data handling and uninstall

Generation sends image content and relevant prompts/settings to AutoAlt.ai over
HTTPS. Optional installation diagnostics require explicit administrator consent,
or confirmation of a separate one-time report. See **External services** below.
Protect database backups and keep credentials and confidential information out
of Git, prompts, and issue reports.

TYPO3 13 Extension Manager deactivation triggers cleanup of extension-owned
settings and history. Composer removal runs cleanup only when the root project
registers the documented callback, or when an administrator runs `autoalt:cleanup`
first. Cleanup does not reverse generated metadata or filenames. Read the
[uninstall instructions](Documentation/Installation/Index.rst) before removal.

## External services

The extension uses the AutoAlt.ai API at `https://ahxdfj.autoalt.ai/api/` for
account connection, credits, AI image metadata/filenames, translations, and optional
diagnostics. All requests below use HTTPS POST. Requests with a saved API key
normally authenticate using `Authorization: Bearer …` and `x-api-key` headers.
API-key validation and OTP use their existing JSON authentication/account fields.

| Endpoint | Purpose and JSON fields |
| --- | --- |
| `autoalt-verify-api-key` | Validate and associate an account: `apiKey`, `website_domain`, `framework` (`TYPO3`). |
| `autoalt-send-otp` | Request account registration/login OTP: `email`, `domain`. |
| `autoalt-verify-otp` | Verify registration/login OTP: `email`, `otp`, `domain`, `framework` (`TYPO3`). The returned API key is stored locally. |
| `autoalt-credit-count` | Retrieve credits: `apiKey`, `website_domain`. |
| `autoalt-generate-alt` | Generate ALT/title/description/filename: `image_url`, `language`, `ai_writing_style`, `seo_keywords`, `negative_keywords`, `hardcoded_string_begin`, `hardcoded_string_end`, `customPromptFromUser`, `website_domain`; image content in `base64_img`; where applicable `alttext_min_limit`, `alttext_max_limit`, `product_name`, `autoaltai_title`, `autoaltai_description`, `autoaltai_rename_file`, `autoaltai_alt_text`. Image content rather than a publicly accessible image URL is used by the extension's current configuration. |
| `autoalt-translate` | Translate generated metadata: `word_org`, `targetLang`, `baseLang`, `website_domain`. |
| `autoalt-get-data-from-plugin` | Optional automatic diagnostics or an explicitly confirmed one-time support report, with the exact fields below. |

The operational requests above remain available with telemetry off. Domains,
email addresses, OTPs, API keys, images and generation instructions needed by
those operations remain part of their established contracts. The domain supports
account association; disabling optional diagnostics does not anonymize operational
requests. Network infrastructure also necessarily sees the connection's IP address
and HTTP headers. Do not include secrets in images, prompts, or metadata.
The unused `autoalt-save-settings` client method has been removed; no runtime
caller used it, and no optional remote-settings synchronization is performed.

### Optional diagnostic sharing

An administrator can open **AutoAlt.ai > Extension Configuration**, find
**Telemetry & Privacy** below **Error Logs** in the side column, select
**Share diagnostic data with AutoAlt.ai**, and save settings. The card includes
an expandable **What data is shared?** explanation and a Privacy Policy link.
Sharing is disabled by default, including for existing installations and upgrades.
Missing, malformed, or invalid consent is disabled. Uncheck and save to stop future
optional submissions. Delegated settings editors cannot change this choice.

With consent and a configured API key, diagnostics are attempted after saving
settings, connecting an API key, OTP verification, or processing a bulk batch or
Filelist selection. Opening a backend page alone does not trigger diagnostics.
Consent is reread from the database before collection and immediately before HTTP;
collection/network failures do not interrupt the operational workflow. Requests
have a five-second total timeout, a three-second connection timeout, and no redirects.

The complete diagnostic JSON envelope is:

- `total_images`, `missing_alt`, `process_by_plugin`: nonnegative aggregate image
  and successful-processing counts.
- `framework`: `typo3`; `framework_version`, `plugin_version`: numeric TYPO3 and
  extension versions, or `unknown` if unavailable.
- `site_url`, `website_domain`: the backend request's host name, lowercased with
  leading `www.` removed; URL credentials, paths, query strings and fragments
  are excluded. This identifies the installation for compatibility/support.
- `error_log`: always an empty array. No local error messages or exceptions are sent.
- `plugin_setting_data` contains only:
  - `generate_title`, `generate_description`, `upload_enabled`, `rename_file`:
    feature booleans.
  - `alt_text_min` (0–250), `alt_text_max` (0–300): requested text lengths.
  - `allowed_imagetype`: comma-separated subset of `jpg,jpeg,png,webp,gif,avif,svg`.
  - `typo3.enabled`, `typo3.overwrite_existing_alt_text`,
    `typo3.notify_on_bulk_complete`: feature booleans;
    `typo3.short_alt_text_length` (0–50): short-text threshold.
  - `_diagnostic_metadata.diagnostic_type`: `telemetry` or `support`;
    `_diagnostic_metadata.php_version`: numeric PHP version.

These fields help diagnose compatibility, feature behavior and image-processing
coverage. Reports exclude API keys, passwords, tokens, cookies, authentication
headers, database credentials, environment variables, arbitrary configuration,
raw error logs, image content, filenames, generated text, writing style, prompts,
keywords, prefixes/suffixes, and notification email addresses. The API key is
still used in HTTPS authentication headers, outside the report.

### One-time support diagnostics

An administrator can choose **Send diagnostic data to support** in the same
section. Review the exact JSON and then choose **Confirm and send diagnostic data**
or **Cancel**. Opening the review collects locally and sends nothing. The review
is stored in the backend session, valid for confirmation for 15 minutes, tied to that user, and
consumed before an attempted submission. A new review replaces the previous one.
The confirmation uses POST, TYPO3 form protection and administrator authorization.
Only the reviewed server-side snapshot is submitted. Ongoing consent is unchanged,
even when it is off. Success is displayed only for an HTTP success with the
backend's explicit JSON `success: true`; failures do not expose upstream bodies.

Support reports use the existing diagnostic endpoint with type `support` in the
stored settings envelope. This submits diagnostic data; it does not create a
support ticket or guarantee email delivery to support. Contact support with your
issue separately if needed. The current backend stores the latest record per
account/domain, so another submission can replace that report.

The existing [Privacy Policy](https://www.autoalt.ai/privacy-policy/) and
[Terms of Service](https://www.autoalt.ai/terms/) apply. These URLs come from the
existing TYPO3 documentation and supplied reference plugins, respectively.
**90-day backend retention is pending, not verified or enforced by this extension.**
The extension does not enforce server-side retention. Contact AutoAlt.ai support
for verified retention information; this release makes no 90-day retention guarantee.

Optional bulk-completion notifications use the TYPO3 site's configured mail
transport and send the configured recipient aggregate completion counts. No
additional analytics service or new diagnostic provider is used.

## Development setup

```bash
git clone https://github.com/webAufstieg/alt-text-generator.git
cd alt-text-generator
composer install
composer test
```

Use PHP satisfying the selected TYPO3 version, Composer 2, Git, and a POSIX shell
with `find` and `xargs` for the lint command. A TYPO3 development site with a database,
file storage, backend user, and test API account is needed for functional checks.
For local integration, add this checkout as a Composer `path` repository in that
site and require the package there. Standalone dependency installation does not
create a runnable TYPO3 website.

## Project structure

```text
Classes/                  Controllers, services, DTOs, listeners, CLI and FAL integration
Configuration/            Backend routes/modules, DI, icons, JavaScript, TCA and TSconfig
Documentation/            TYPO3 reStructuredText user and administrator guide
Resources/Private/        Fluid templates/partials and XLIFF translations
Resources/Public/         CSS, JavaScript and SVG icons
composer.json             Package requirements, PSR-4 autoloading and lint commands
ext_emconf.php            Extension Manager metadata and release version
ext_localconf.php         Logging and FormEngine registrations
ext_tables.sql            Extension database tables
CHANGELOG.md              Release history and provenance notes
RELEASE.md                Validation, packaging and future release workflow
LICENSE.txt               Existing GPL license
```

`Tests/Unit/` preserves the existing PHPUnit suite; `Tests/Privacy/` adds isolated
privacy behavior checks. Tests are available in Git and excluded from release archives. No `ext_tables.php` is needed. See
[TYPO3's extension structure reference](https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/ExtensionArchitecture/FileStructure/Index.html).

## Testing and validation

```bash
composer validate --strict --no-check-publish
composer run lint:php
composer test
```

`composer test` validates Composer metadata, PHP syntax and YAML configuration,
runs the preserved PHPUnit unit suite, and runs the isolated privacy behavior
suite. You can run the suites individually with `composer test:unit` and
`composer test:privacy`.

The PHPUnit suite uses the installed TYPO3 classes and mocks; the privacy suite
uses isolated adapters and mock HTTP, and runs in its own PHP process. Automated
checks do not send real reports to AutoAlt.ai. They do not replace a running
TYPO3/FAL/database/browser integration test. No functional test suite or static
analysis/coding-standard runner is configured. Follow [RELEASE.md](RELEASE.md)
for deployment and release smoke checks.

## Versioning, support and contribution

Maintain this repository for all future versions. Update `ext_emconf.php`,
`Documentation/guides.xml`, the current README version, and [CHANGELOG.md](CHANGELOG.md)
before committing and tagging the next release, such as `v1.0.9` or `v1.1.0`. Composer infers
package versions from Git tags; do not add a hardcoded `version` field.
Follow [RELEASE.md](RELEASE.md) for the full workflow and archive command.

Repository collaborators can submit changes and issues at
[webAufstieg/alt-text-generator](https://github.com/webAufstieg/alt-text-generator).
Include reproduction steps and validation results without credentials or private
image data. Vendor support: [support@autoalt.ai](mailto:support@autoalt.ai).
Documentation is maintained in [Documentation/](Documentation/Index.rst).
Licensed under [GPL-2.0-or-later](LICENSE.txt).
