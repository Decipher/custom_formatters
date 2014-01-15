The Custom Formatters module allows users to easily create custom Field
Formatters without the need to write a custom module. Custom Formatters can then
be exported as CTools Exportables, Features or Drupal API Field Formatters.

Custom Formatters was written and is maintained by Stuart Clark (deciphered).
- http://stuar.tc/lark
- http://twitter.com/Decipher



Features
--------------------------------------------------------------------------------

* Two default editor/renderer engines:
  * HTML + Tokens.
  * PHP.
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
* Live preview using real entities or Devel Generate.
* Integrates with:
  * Coder Review module - Review your Custom Formatter code for Drupal coding
      standards and more.
  * Display Suite module - Format Display Suite fields.
  * Drupal Contextual links module - Adds a hover link for quick editing of
      Custom Formatters.
  * Entity tokens module - Leverages entity tokens for Field token support.
  * Features module - Adds dependent Custom Formatters (from Views or Content
      types) to Feature.
  * Form Builder - Drag'n'Drop interface for builder Formatter Settings forms.
  * Insert module - Exposes Custom Formatters to the Insert module.
  * Libraries API module and the EditArea javascript library - Adds real-time
      syntax highlighting.
  * Token module - Adds the Token tree browser to the HTML + Tokens engine.



Required Modules
--------------------------------------------------------------------------------

* Chaos tool suite - http://drupal.org/project/ctools



Recommended Modules
--------------------------------------------------------------------------------

* Coder - http://drupal.org/project/coder
  * Coder Review (via Coder)
* Devel - http://drupal.org/project/devel
  * Devel Generate (via Devel)
* Entity - http://drupal.org/project/entity
  * Entity tokens (via Entity)
* Form Builder - http://drupal.org/project/form_builder
* Libraries API - http://drupal.org/project/libraries
* Token - http://drupal.org/project/token



EditArea - Real-time syntax highlighting
--------------------------------------------------------------------------------

The EditArea javascript library adds real-time syntax highlighting, to install
it follow these steps:

1. Download and install the Libraries API module.
    http://drupal.org/project/libraries

2. Download the EditArea library and extract and move it into your libraries
   folder as 'editarea' (eg. sites/all/libraries/editarea).
    http://sourceforge.net/projects/editarea/files/EditArea/EditArea%200.8.2/editarea_0_8_2.zip/download



Makefile entries
--------------------------------------------------------------------------------

For easy downloading of Custom Formatters and it's required/recommended modules
and/or libraries, you can use the following entries in your makefile:


  projects[coder][subdir] = contrib
  projects[coder][version] = 2.0

  projects[ctools][subdir] = contrib
  projects[ctools][version] = 1.3

  projects[devel][subdir] = contrib
  projects[devel][version] = 1.3

  projects[entity][subdir] = contrib
  projects[entity][version] = 1.2

  projects[form_builder][subdir] = contrib
  projects[form_builder][version] = 1.4

  projects[libraries][subdir] = contrib
  projects[libraries][version] = 2.1

  projects[options_element][subdir] = contrib
  projects[options_element][version] = 1.9

  projects[token][subdir] = contrib
  projects[token][version] = 1.5

  libraries[editarea][download][type] = get
  libraries[editarea][download][url] = http://downloads.sourceforge.net/project/editarea/EditArea/EditArea%200.8.2/editarea_0_8_2.zip?r=&ts=1334742944&use_mirror=internode



Roadmap
--------------------------------------------------------------------------------

7.x-2.3
- Add Display Suite integration.
- Improve HTML + Tokens engine.

7.x-2.4
- Add Static cache mode (read Formatters from code instead of Database).
