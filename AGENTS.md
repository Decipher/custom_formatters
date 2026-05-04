# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Overview

Custom Formatters is a Drupal module that allows users to create custom Field Formatters through an admin UI without writing a custom module. It supports multiple formatter engines: Formatter Preset, HTML+Token, PHP, and Twig.

## Development Commands

### Build and Environment Management

**Using Make:**
- `make build` - Complete build (stop -> assemble -> start -> provision)
- `make assemble` - Assemble codebase with dependencies
- `make start` - Start PHP development server
- `make stop` - Stop development server
- `make provision` - Install/provision Drupal site
- `make reset` - Clean build directory and logs

**Using Ahoy:**
- `ahoy build` - Complete build process
- `ahoy assemble` - Assemble codebase
- `ahoy start` / `ahoy stop` - Start/stop development server
- `ahoy provision` - Provision Drupal site

### Code Quality

**Linting:**
- `make lint` / `ahoy lint` - Run all linting tools (phpcs, phpstan, rector dry-run, twig-cs-fixer)
- `make lint-fix` / `ahoy lint-fix` - Auto-fix coding standards violations

**Testing:**
- `make test` / `ahoy test` - Run all PHPUnit tests
- `make test-unit` / `ahoy test-unit` - Run unit tests only
- `make test-kernel` / `ahoy test-kernel` - Run kernel tests only
- `make test-functional` / `ahoy test-functional` - Run functional tests only
- `make test-functional-javascript` / `ahoy test-functional-javascript` - Run FunctionalJavascript tests (requires Selenium)

### Drupal Commands

- `make drush <command>` - Run Drush commands
- `make login` / `ahoy login` - Get one-time login link

## Project Structure

- `src/` - Module source code
  - `Entity/` - Formatter config entity
  - `Form/` - FormatterForm, CustomFormattersSettingsForm
  - `Plugin/CustomFormatters/FormatterType/` - Formatter engine plugins (FormatterPreset, HTMLToken, PHP, Twig)
  - `Plugin/CustomFormatters/FormatterExtras/` - Extras plugins (Contextual)
  - `Plugin/Field/FieldFormatter/` - Deriver-based field formatter
- `tests/` - Test modules and fixtures
- `config/` - Config install, optional, schema
- `templates/` - Twig templates
- `build/` - Assembled Drupal codebase (gitignored, symlinked extension)
- `.devtools/` - Build and deployment scripts used by CI

## Architecture

- **Config Entity**: `Formatter` entity with plugin-based engine system
- **Two plugin types**: `FormatterType` (formatter engines) and `FormatterExtras` (additional features)
- **Deriver**: `CustomFormatters` deriver generates one field formatter plugin per config entity
- **Configuration-driven**: Formatter definitions stored as config entities with schema validation

## Environment Variables

- `DRUPAL_VERSION` - Target Drupal version (e.g., `10`, `11`)
- `WEBSERVER_HOST` - Development server host (default: localhost)
- `WEBSERVER_PORT` - Development server port (default: 8000)
- `GITHUB_TOKEN` - GitHub API token to avoid rate limits

## Code Quality Tools

- **PHPCS**: Drupal and DrupalPractice standards
- **PHPStan**: Static analysis at level 7 with Drupal extensions
- **Rector**: Automated refactoring and deprecation fixes (D9/D10 sets)
- **Twig CS Fixer**: Twig template formatting

## CI/CD

- **GitHub Actions**: `.github/workflows/test.yml` (lint + 15-matrix test)
- **Deploy**: `.github/workflows/deploy.yml` (mirror to Drupal.org via SSH)
- **Matrix testing**: PHP 8.2-8.4, Drupal 10-11 (legacy/stable/canary)

## Important Notes

- The `build/` directory contains the assembled Drupal site
- Extension files are symlinked from root into `build/web/modules/custom/`
- SQLite database created in `/tmp/site_custom_formatters.sqlite`
- All quality tools run from within `build/` directory
- Branch `4.1.x` is the active development branch
- CI triggers on both `4.0.x` and `4.1.x` branch pushes and PRs
