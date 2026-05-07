/**
 * @file
 * Custom Formatters admin behaviors.
 */

/* eslint-disable func-names */
/* global editAreaLoader */

(function ($) {
  Drupal.settings.customFormattersAdmin = {};

  Drupal.behaviors.customFormattersAdmin = {
    attach(context) {
      // Set initial fieldset states.
      if (
        typeof Drupal.settings.customFormattersAdmin.fieldsets === 'undefined'
      ) {
        Drupal.settings.customFormattersAdmin.fieldsets = {};
        this.storeStates();
      }
      if (context !== document) {
        this.restoreStates();
      }

      // EditArea real-time syntax highlighter.
      if (
        $('.form-item-code textarea').length >= 1 &&
        typeof editAreaLoader !== 'undefined' &&
        !$('.form-item-code textarea').hasClass('editarea-processed')
      ) {
        $('.form-item-code textarea').addClass('editarea-processed');
        const syntax = $('.form-item-code textarea')
          .attr('class')
          .match(/syntax-(\w+)\b/m);
        editAreaLoader.init({
          id: $('.form-item-code textarea').attr('id'),
          syntax: syntax[1],
          start_highlight: true,
          allow_resize: 'y',
          allow_toggle: false,
          toolbar: '*',
          word_wrap: false,
          language: 'en',
          replace_tab_by_spaces: 2,
          change_callback:
            'Drupal.behaviors.customFormattersAdmin.customFormattersEAUpdate',
        });

        // Make sure '#edit-code' gets updated before we preview the formatter.
        $('#engine-wrapper .form-submit').bind('mouseover', function () {
          Drupal.behaviors.customFormattersAdmin.customFormattersEAUpdate(
            $('.form-item-code textarea').attr('id'),
          );
        });
      }
    },

    detach() {
      this.storeStates();
    },

    storeStates() {
      $('fieldset').each(function () {
        const id =
          $(this).attr('id').indexOf('--') > 0
            ? $(this).attr('id').substr(0, $(this).attr('id').indexOf('--'))
            : $(this).attr('id');
        Drupal.settings.customFormattersAdmin.fieldsets[id] =
          $(this).hasClass('collapsed');
      });
    },

    restoreStates() {
      $('fieldset').each(function () {
        const id =
          $(this).attr('id').indexOf('--') > 0
            ? $(this).attr('id').substr(0, $(this).attr('id').indexOf('--'))
            : $(this).attr('id');
        if (Drupal.settings.customFormattersAdmin.fieldsets[id] === false) {
          $(this).removeClass('collapsed');
        }
      });
    },

    // Update '#edit-code' with the EditArea code.
    customFormattersEAUpdate(editorId) {
      $(`#${editorId}`).val(editAreaLoader.getValue(editorId));
    },
  };
})(jQuery);
