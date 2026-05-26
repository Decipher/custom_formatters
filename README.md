Custom Formatters
=================

[![Test](https://github.com/Decipher/custom_formatters/actions/workflows/test.yml/badge.svg?branch=4.1.x)](https://github.com/Decipher/custom_formatters/actions/workflows/test.yml?query=branch%3A4.1.x)
[![Coverage](https://codecov.io/gh/Decipher/custom_formatters/branch/4.1.x/graph/badge.svg)](https://codecov.io/gh/Decipher/custom_formatters/branch/4.1.x)

The Custom Formatters module allows users to easily create custom Field
Formatters through an admin UI without writing a custom module. Custom Formatters
are exported as Drupal configuration entities.

Features
--------

* Pluggable formatter engines:
  * **Formatter Preset**
    Build formatters from existing field formatters with preset settings.
  * **HTML + Tokens**
    A HTML based editor with Token support, including a Token tree browser
    when the Token module is installed.
  * **PHP**
    A PHP based editor with support for multiple fields and multiple values.
  * **Twig**
    A Twig based editor with support for multiple fields and multiple values.
* Supports all fieldable entities, including but not limited to:
  * Drupal core — Comment, Node, Taxonomy term, User, and Media entities.
* Exportable as Drupal configuration entities.
* Integrates with:
  * **Contextual links** _(Drupal core)_
    Adds a hover link for quick editing of Custom Formatters.
  * **Token**
    Adds the Token tree browser to the HTML + Tokens engine with automatic
    entity reference token support.

Recommended Modules
-------------------

* [Token](https://www.drupal.org/project/token)
* [Field tokens](https://www.drupal.org/project/field_tokens)
* [CodeMirror Editor](https://www.drupal.org/project/codemirror_editor) —
  Provides syntax-highlighted code editing for the PHP, HTML+Token, and Twig
  formatter engines.

Usage/Configuration
-------------------

Read the manual at: [drupal.org/node/2514412](https://www.drupal.org/node/2514412)

Requirements
------------

* Drupal 10 or 11
* PHP 8.2+

Testing
-------

This project includes a Makefile and [Ahoy](https://ahoy-cli.readthedocs.io/)
based development environment.

    make build       # Build the development environment
    make provision   # Install Drupal
    make test        # Run all PHPUnit tests
    make lint        # Run PHPCS, PHPStan, Rector, and Twig CS Fixer

See `AGENTS.md` in the module root for the full list of available commands.

TODOs / Roadmap
---------------

* Add Contextual links configuration as formatter setting.
* Add granular permissions to Formatter types.
* Add Formatter list view?
  * Would require adding support for Formatter config entities in Views.
* Add custom support for admin theme / Formatter add page.
* Add ability to change field types that aren't in use.
* Set usages of formatters to default formatter on deletion.
* ~~Re-add save & edit?~~ (Added in 4.1.x)
* Re-add preview.
* ~~Replace EditArea with a modern code editor.~~ (Added in 4.1.x, via
  [CodeMirror Editor](https://www.drupal.org/project/codemirror_editor))
* Re-add export?
