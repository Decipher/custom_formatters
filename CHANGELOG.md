# Changelog

## 4.1.x-dev (development)

### Features

- Added per-instance formatter settings via Field UI: formatter entities now
  support configurable fields (via "Manage fields" / "Manage form display"
  tabs) that appear as inline settings in the "Manage display" UI.

## 4.1.0-beta2 (2026-05-24)

### Features

- Added "Save & Edit" button to formatter edit form.
- Added live preview using real entities, with per-engine debug output
  (variables dump and raw HTML).
- Added [CodeMirror Editor](https://www.drupal.org/project/codemirror_editor)
  integration for syntax-highlighted code editing in PHP, HTML+Token, and
  Twig engines.
- Added [Devel Generate](https://www.drupal.org/project/devel) integration
  to generate sample entities for the live preview when no real entities
  with the target field type exist.
- [#2885050](https://www.drupal.org/project/custom_formatters/issues/2885050):
  Added `{{ entity }}` variable to Twig formatter template context.
- [#3335203](https://www.drupal.org/project/custom_formatters/issues/3335203):
  Added missing `hook_help()` implementation.
- Added rewritten PHP example formatter and new Twig example formatter.
- [#3429720](https://www.drupal.org/project/custom_formatters/issues/3429720)
  / [#3527609](https://www.drupal.org/project/custom_formatters/issues/3527609):
  Drupal 11 compatibility.

### Bug fixes

- [#3404747](https://www.drupal.org/project/custom_formatters/issues/3404747):
  Fixed module not working on Drupal 10 with PHP 8.2 (critical).
- [#3188668](https://www.drupal.org/project/custom_formatters/issues/3188668):
  Fixed formatter entity failing to load properties from storage.
- [#3572914](https://www.drupal.org/project/custom_formatters/issues/3572914):
  Fixed broken config export — formatter properties missing on import.
- [#3572918](https://www.drupal.org/project/custom_formatters/issues/3572918):
  Added hardening against corrupted formatter config.
- [#3478998](https://www.drupal.org/project/custom_formatters/issues/3478998):
  Fixed call to `render()` on string instead of render array.
- [#3387578](https://www.drupal.org/project/custom_formatters/issues/3387578):
  Fixed `MissingMandatoryParametersException` when Devel module is installed.
- [#3025496](https://www.drupal.org/project/custom_formatters/issues/3025496):
  Fixed contextual link placeholders leaking into CSV/JSON exports.

### Developer experience

- [#3335202](https://www.drupal.org/project/custom_formatters/issues/3335202):
  Raised minimum `core_version_requirement` to `^8.8`.
- Resolved all PHPStan level 7 errors across the module.
- Replaced static `\Drupal` calls with dependency injection throughout.
- Added `declare(strict_types=1)` to all source files.
- [#3592679](https://www.drupal.org/project/custom_formatters/issues/3592679):
  Added GitLab CI pipeline, upgraded PHPStan to ^2, updated CI scaffold.
- Improved test coverage: 30 tests, 272 assertions (D10).
- Updated CI matrix to test PHP 8.2–8.4 across Drupal 10 and 11.

## 4.1.0-beta1 (2025-04-30)

- Initial 4.1.x beta release.
- Added HTML+Token engine with Token support.
- Added Formatter Preset engine.
- Added Formatter Extras plugin system (contextual links integration).
- Added Twig and PHP formatter engines.
- Added configuration schema for formatter entities.
- Added deriver-based field formatter plugin.
- Compatible with Drupal 8, 9, 10, and 11.

## 8.x-3.x-dev

- Initial 8.x release.
