# Changelog

## 4.1.0 (2026-07-31)

### Features

- Added [Insert](https://www.drupal.org/project/insert) module integration —
  custom formatters targeting `image`, `file`, or `entity_reference` fields
  are automatically exposed as Insert styles, allowing formatted output to be
  inserted directly into WYSIWYG editors.

### Bug fixes

- Fixed the `formatter_setting` entity schema not being installed when updating
  from earlier 4.1.x betas. The `update_8401` hook shipped in 4.1.0-beta3 never
  installed the schema (it awaited a `PluginNotFoundException` that is never
  thrown); the condition is now corrected, and a new `update_8402`
  retroactively installs the schema for sites that already ran the broken
  update.
- Fixed per-instance formatter settings (the `FormatterSetting` entity) not
  being persisted when saved from the Field UI "Manage display" form. Field
  formatter plugins receive no submit hook from core, so the entity is now
  saved during the settings fieldset's `#element_validate`.
- Fixed the example Twig formatter (`example_twig_title`) link branch throwing
  under Drupal's Twig sandbox. The link `href` now uses the sandbox-safe `path()`
  function instead of the blocked `entity.toUrl()` method call.

### Known limitations

- Per-instance formatter settings are saved during form validation. If a
  later validator rejects the "Manage display" submission, an orphaned
  `FormatterSetting` entity may be left in the database with no
  `entity_view_display` referencing it. This has no functional impact and is
  tracked as a follow-up.

## 4.1.0-beta3 (2026-06-07)

### Features

- Added per-instance formatter settings via Field UI: formatter entities now
  support configurable fields (via "Manage fields" / "Manage form display"
  tabs) that appear as inline settings in the "Manage display" UI.
- Added raw settings access alongside rendered settings in all engine plugins:
  - **PHP**: `$raw_settings['field_name']` — unformatted `getString()` value.
  - **Twig**: `raw_settings.field_name` — unformatted `getString()` value.
  - **HTML+Token**: `[formatter_setting:field_name:raw]` token — unformatted value.
  - Raw values are also available in the formatter preview form.
- Added context-aware CodeMirror autocomplete hints (requires optional
  [CodeMirror Editor](https://www.drupal.org/project/codemirror_editor) module):
  - `{{` auto-triggers Twig variable completions (`items`, `settings`,
    `raw_settings`, `settings.field`, `raw_settings.field`, etc.).
  - Typing letters inside a `{{ expr` expression re-triggers completions.
  - `[` auto-triggers Drupal token completions including
    `[formatter_setting:field]` and `[formatter_setting:field:raw]` entries.
  - `<` auto-triggers HTML tag completions (HTML+Token and Twig engines).
  - `Ctrl+Space` provides mode-appropriate completions: PHP context variables
    (`$items`, `$settings`, `$raw_settings`) with structure-aware
    `$settings['field']` / `$raw_settings['field']` completions; Twig context
    variables; HTML tag and attribute completions.
- Added settings reference table to the formatter edit form, listing each
  configurable field's machine name, type, and label for quick reference.

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
