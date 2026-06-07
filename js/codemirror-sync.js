/**
 * @file
 * Forces CodeMirror instances to sync content before AJAX form submissions.
 *
 * Hooks into Drupal's behavior detach() with the 'serialize' trigger, which is
 * called by Ajax.prototype.beforeSerialize() before every AJAX form submission.
 * This ensures the textarea value is current when the form is serialized.
 * @param {Object} Drupal  The Drupal object.
 * @todo Remove this file and its library entry once codemirror_editor issue
 *       #3511658 (MR !14) is fixed and released.
 */
(function customFormattersCodeMirrorSync(Drupal) {
  Drupal.behaviors.customFormattersCodeMirrorSync = {
    /**
     * Syncs CodeMirror instances before AJAX form serialization.
     *
     * Called by Drupal.Ajax.prototype.beforeSerialize() with the form element
     * as context and trigger = 'serialize'. Skips non-serialize triggers (e.g.
     * 'unload' when the DOM is removed).
     *
     * @param {Element} context   The form DOM element being serialized.
     * @param {Object}  settings  Drupal settings object.
     * @param {string}  trigger   The detach trigger ('serialize' or 'unload').
     */
    detach(context, settings, trigger) {
      if (trigger !== 'serialize') {
        return;
      }

      context
        .querySelectorAll('.CodeMirror')
        .forEach(function saveCodeMirror(el) {
          if (el.CodeMirror) {
            el.CodeMirror.save();
          }
        });
    },
  };
})(Drupal);
