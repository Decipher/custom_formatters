# Custom Formatters

[![Pipeline](https://git.drupalcode.org/project/custom_formatters/badges/4.1.x/pipeline.svg)](https://git.drupalcode.org/project/custom_formatters/-/pipelines)
[![Test](https://github.com/Decipher/custom_formatters/actions/workflows/test.yml/badge.svg?branch=4.1.x)](https://github.com/Decipher/custom_formatters/actions/workflows/test.yml?query=branch%3A4.1.x)
[![Coverage](https://codecov.io/gh/Decipher/custom_formatters/branch/4.1.x/graph/badge.svg)](https://codecov.io/gh/Decipher/custom_formatters/branch/4.1.x)

The Custom Formatters module allows users to easily create custom Field
Formatters through an admin UI without writing a custom module. Custom
Formatters are exported as Drupal configuration entities.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/custom_formatters).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/custom_formatters).

## Table of contents

- Requirements
- Installation
- Configuration
- Features
- Roadmap
- Maintainers

## Requirements

- Drupal 10 or 11
- PHP 8.2+

The following modules are recommended:

- [Token](https://www.drupal.org/project/token) — Provides the token tree
  browser for the HTML + Tokens engine.
- [Field tokens](https://www.drupal.org/project/field_tokens) — Provides
  formatted field and field property tokens for the HTML + Token engine.
- [CodeMirror Editor](https://www.drupal.org/project/codemirror_editor) —
  Provides syntax-highlighted code editing for the PHP, HTML+Token, and Twig
  formatter engines.
- [Devel](https://www.drupal.org/project/devel) — Provides the Devel Generate
  sub-module for generating sample preview entities.

## Installation

1. Download and install via Composer:

   ```bash
   composer require drupal/custom_formatters
   ```

2. Enable the module:

   ```bash
   drush en custom_formatters
   ```

## Configuration

Read the manual at:
[drupal.org/node/2514412](https://www.drupal.org/node/2514412)

## Features

- Pluggable formatter engines:
  - **Formatter Preset** — Build formatters from existing field formatters
    with preset settings.
  - **HTML + Tokens** — A HTML based editor with Token support, including a
    Token tree browser when the Token module is installed.
  - **PHP** — A PHP based editor with support for multiple fields and
    multiple values.
  - **Twig** — A Twig based editor with support for multiple fields and
    multiple values.
- Per-instance formatter settings via Field UI — add configurable fields to a
  formatter via its "Manage fields" tab; settings appear inline in "Manage
  display" and are passed to engine templates as rendered strings.
- Supports all fieldable entities, including but not limited to:
  - Drupal core — Comment, Node, Taxonomy term, User, and Media entities.
- Exportable as Drupal configuration entities.
- Live preview using real entities, with Devel Generate integration for
  generating sample entities when no real entities exist with the target
  field type.
- Integrates with:
  - **Contextual links** _(Drupal core)_ — Adds a hover link for quick
    editing of Custom Formatters.
  - **Token** — Adds the Token tree browser to the HTML + Tokens engine
    with automatic entity reference token support.
  - **Devel Generate** _(optional)_ — Generates sample entities with dummy
    field data for the live preview system when no real entities exist with
    the target field type.

## Roadmap

Planned features for future releases:

- **Granular permissions** — Per-engine permission gates, e.g. a separate
  permission to create PHP formatters.
- **Export** — Generate a standalone Drupal module from a custom formatter.
- **Coder review integration** — Inline PHPCS review of PHP formatter code
  against Drupal coding standards directly in the formatter edit form.
- **Display Suite integration** — Format Display Suite fields with custom
  formatters.
- **Insert integration** — Expose custom formatters as Insert styles for
  image and file fields.
- **JSON:API integration** — Apply custom formatters to JSON:API field
  output.
- **Field type management** — Change a formatter's field types after
  creation; fall back to default formatter on deletion.

## Maintainers

- Stuart Clark - [deciphered](https://www.drupal.org/u/deciphered)
- Andrii Podanenko - [podarok](https://www.drupal.org/u/podarok)
