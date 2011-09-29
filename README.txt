The Custom Formatters module allows users to easily create custom Field
Formatters without the need to write a custom module. Custom Formatters can then
be exported as CTools Exportables, Features or Drupal API Field Formatters.

Custom Formatters was written and is maintained by Stuart Clark (deciphered).
- http://stuar.tc/lark


Features
--------------------------------------------------------------------------------

* Two default editor/renderer engines:
  * HTML + Tokens (requires Token module).
  * PHP (with support for EditArea for real-time syntax highlighting).
* Supports for all fieldable entities, including but not limited to:
  * Drupal Core - Comment, Node, Taxonomy term and User entities.
  * Field collection module - Field-collection item entity.
  * Media module - Media entity.
* Exportable as:
  * Drupal API formatter via:
    * Custom Formatters export interface.
  * CTools exportable via:
    * Custom Formatters export interface.
    * CTools Bulk Export module.
    * Features module.
* Live preview using real entities.
* Integrates with:
  * Drupal Contextual links module - Adds a hover link for quick editing of
      Custom Formatters.
  * Features module - Adds dependent Custom Formatters (from Views or Content
      types) to Feature.
  * Insert module - Exposes Custom Formatters to the Insert module.


Required Modules
--------------------------------------------------------------------------------

* Chaos tool suite - http://drupal.org/project/ctools


Recommended Modules
--------------------------------------------------------------------------------

* Devel - http://drupal.org/project/devel
* Libraries API - http://drupal.org/project/libraries
* Token - http://drupal.org/project/token


EditArea - Real-time PHP syntax highlighting
--------------------------------------------------------------------------------

The EditArea javascript library allows real-time syntax highlighting in the PHP
editor, to install it follow these steps:

1. Download and install the Libraries API module.
    http://drupal.org/project/libraries

2. Download the EditArea library and extract and move it into your libraries
   folder as 'editarea' (eg. sites/all/libraries/editarea).
    http://sourceforge.net/projects/editarea/files/EditArea/EditArea%200.8.2/editarea_0_8_2.zip/download


Roadmap
--------------------------------------------------------------------------------

7.x-2.1
- Add formatter settings functionality.
- Add Devel generate integration for Preview.
