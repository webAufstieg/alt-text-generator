# Releasing Alt Text Generator - AutoAlt.ai

The current release is **1.0.9**, dated 30 October 2026. It adds a complete
German backend interface while preserving English as the source and fallback.
Progress indicators, notifications, language names, error presentation and
completion emails follow the backend user's language. Existing site-language
and AI generation behavior is unchanged. No database migration is introduced.
Clear TYPO3 caches and reload the backend after updating.

## Develop and validate

1. Prepare changes on a release or feature branch and preserve existing history
   and PHPUnit tests. Keep release code, version metadata and public docs aligned.
2. Install development dependencies with `composer install` using a PHP version
   supported by the selected TYPO3 release.
3. Run `composer test`. It validates Composer, PHP and YAML, then runs PHPUnit
   and the isolated privacy suite. These checks must not submit real diagnostic
   data to the production API.
4. In a staging TYPO3 site, run `vendor/bin/typo3 extension:setup`, apply any
   pending schema changes and flush caches. Smoke-test API connection/OTP,
   credits, generation, uploads, Filelist/bulk processing, translations, history,
   permissions and filename rename/undo.
5. Verify default-off sharing, administrator opt-in/revocation, review/cancel/
   confirm, expiry, CSRF/permission failures, safe report contents and desktop/
   mobile presentation. Confirm failures do not interrupt normal operations.
6. Update `ext_emconf.php`, `Documentation/guides.xml`, README/install examples
   and both changelogs. Keep `autoaltai/alt-text-generator` and extension key
   `alt_text_generator` unchanged. Composer derives versions from tags.

## Maintain the release source

Prepare feature work in the internal development repository. Bring approved
production changes into a release branch here, preserving this repository's
history, unit tests and public documentation. Review public contributions and
bring accepted fixes back into internal development before the next release.
For each published version, compare the production file contents between the
approved internal source, public tag and distribution archive. Internal notes
and customer documentation can differ; the shipped production code must match.

## Version 1.0.9 verification

Production source is synchronized with the approved internal 1.0.9 tag. All
existing test files are preserved, including the localization suite covering
German/English backend preferences, German regional fallback, all 562 catalogue
labels and placeholders, module/JavaScript labels, history language names,
error responses, Fluid parsing and a completion email with a mocked mailer.
The owner supplied a working-site archive with matching production code and
screenshots showing successful English and German generated metadata.
Public validation on PHP 8.2.35 passed the full `composer test` suite with
TYPO3 13.4.37: **49 PHPUnit tests, 5,331 assertions, and 76 privacy behavior
checks**, plus Composer/PHP/YAML validation. The same 49 PHPUnit tests and
5,331 assertions also passed with TYPO3 14.3.10. All **89 production files**
match the approved 1.0.9 development tag exactly. No real API request or email
was sent during these automated checks.

Automated checks do not replace browser/FAL/API integration testing on every
supported TYPO3/PHP combination.

## Version 1.0.8 verification

The public release preserves all ten existing PHPUnit test files and updates the
diagnostic payload tests to assert that API keys and sensitive settings are
excluded. The full check ran on PHP 8.2.35 with TYPO3 core 14.3.10 and PHPUnit
11.5.57: **38 unit tests, 92 assertions, and 76 privacy behavior checks passed**,
including Composer/PHP/YAML validation. All 87 production source/resource files
match the approved 1.0.8 development release. This is not a full TYPO3 runtime
or supported-version matrix certification.

## Publish approved source

1. Review the complete release diff and test results, then merge the release
   branch into `main` through the repository's review process.
2. Create the new version tag only at the reviewed commit. Do not replace
   existing tags. For this release, use `v1.0.9`.
3. Push the approved branch/tag and create a GitHub Release with release notes
   and the tested distribution ZIP.
4. Verify that Packagist indexes the public tag and that Composer can resolve
   the released package. The package's source, issue and documentation links
   must be publicly accessible.
5. Publish the approved package to TYPO3 TER using the extension-owner account
   or its configured publishing integration. Confirm the TER version, package
   identity and public source/issue links. GitHub publication alone does not
   publish a TER release.

## Build the distribution ZIP

From the reviewed tagged repository:

```bash
mkdir -p dist
git archive --format=zip --output=dist/alt_text_generator_1.0.9.zip v1.0.9
unzip -t dist/alt_text_generator_1.0.9.zip
unzip -l dist/alt_text_generator_1.0.9.zip
```

The extension files must be at the ZIP root: `composer.json`, `ext_emconf.php`,
`ext_localconf.php`, `ext_tables.sql`, `Classes/`, `Configuration/`, `Resources/`
and the user documentation. Verify that all version declarations match the tag.
The archive excludes tests, dependencies, credentials and local build tools.
Keep useful test sources in the Git repository.

## Customer update

From the TYPO3 project root:

```bash
composer require autoaltai/alt-text-generator:^1.0.9 --with-all-dependencies
vendor/bin/typo3 extension:setup
```

Clear TYPO3 caches and refresh the browser, then check connection/credits and
one test generation before a larger run. Back up the project database and files
before updates. Existing settings and history are retained; optional diagnostic
sharing remains disabled until explicitly enabled by an administrator.

The extension does not enforce remote diagnostic retention. Backend enforcement
of a maximum 90-day period is not verified; do not advertise that guarantee from
this client release alone.
