/**
 * @file
 * Context-aware CodeMirror autocomplete for Custom Formatters editor forms.
 *
 * Provides:
 * - Ctrl+Space: mode-appropriate completions (HTML tags, PHP/Twig context
 *   variables, structure-aware $settings/'settings' field names, anyword).
 * - Auto-trigger on '<': HTML tag completions (HTML+Token and Twig modes).
 * - Auto-trigger on '[': Drupal token completions (HTML+Token mode), including
 *   [formatter_setting:field] and [formatter_setting:field:raw] entries.
 * - Auto-trigger on '{{' or a letter within a Twig expression: Twig
 *   variable completions (Twig mode).
 *
 * Context data (drupalSettings.customFormatters.context.settingsFields, .mode)
 * is attached server-side by FormatterTypeBase::buildCodeEditorElement().
 *
 * @todo Remove hint wiring if codemirror_editor ever exposes extraKeys config.
 * @param {object} Drupal - The Drupal global.
 * @param {Function} once - Drupal once() utility.
 * @param {object} drupalSettings - Drupal settings object.
 */
(function customFormattersCodeMirrorHints(Drupal, once, drupalSettings) {
  /** Variables injected into the PHP formatter engine. */
  const PHP_VARS = ['$items', '$langcode', '$settings', '$raw_settings'];

  /** Variables injected into the Twig formatter engine. */
  const TWIG_VARS = ['items', 'entity', 'langcode', 'settings', 'raw_settings'];

  /**
   * Returns the formatter's settings field names from drupalSettings context.
   *
   * @return {string[]}
   */
  function getSettingsFields() {
    return (
      (drupalSettings &&
        drupalSettings.customFormatters &&
        drupalSettings.customFormatters.context &&
        drupalSettings.customFormatters.context.settingsFields) ||
      []
    );
  }

  /**
   * Returns a hint function for the given mode.
   *
   * Combines context-specific variable suggestions with structure-aware
   * completions for $settings['field'] (PHP) or settings.field (Twig), then
   * falls back to anyword for everything else.
   *
   * @param {string[]} predefined - Top-level context variable names.
   * @param {'php'|'twig'} engineType - Which engine-specific logic to apply.
   * @return {Function} A CodeMirror hint function.
   */
  function makeContextHint(predefined, engineType) {
    /* global CodeMirror */
    return (editor) => {
      const cur = editor.getCursor();
      const line = editor.getLine(cur.line);
      const before = line.slice(0, cur.ch);
      const token = editor.getTokenAt(cur);
      const fields = getSettingsFields();

      // PHP: after $settings[' or $raw_settings[', suggest field names.
      if (engineType === 'php' && fields.length) {
        const m = before.match(/\$(?:raw_)?settings\[(['"])([a-z0-9_]*)$/);
        if (m) {
          const [, q, prefix] = m;
          const matches = fields
            .filter((f) => f.startsWith(prefix))
            .map((f) => f + q + ']');
          if (matches.length) {
            return {
              list: matches,
              from: CodeMirror.Pos(cur.line, cur.ch - prefix.length),
              to: cur,
            };
          }
        }
      }

      // Twig: after settings. or raw_settings., suggest field names.
      if (engineType === 'twig' && fields.length) {
        const m = before.match(/(?:raw_)?settings\.([a-z0-9_]*)$/);
        if (m) {
          const prefix = m[1];
          const matches = fields.filter((f) => f.startsWith(prefix));
          if (matches.length) {
            return {
              list: matches,
              from: CodeMirror.Pos(cur.line, cur.ch - prefix.length),
              to: cur,
            };
          }
        }
      }

      // Fallback: predefined context variables merged with anyword completions.
      const anyword = CodeMirror.hint.anyword(editor);
      const from = anyword
        ? anyword.from
        : CodeMirror.Pos(cur.line, token.start);
      const to = anyword ? anyword.to : CodeMirror.Pos(cur.line, token.end);
      const prefix = token.string.toLowerCase();
      const ctxMatches = predefined.filter((v) =>
        v.toLowerCase().startsWith(prefix),
      );
      const combined = [
        ...ctxMatches,
        ...(anyword ? anyword.list : []),
      ].filter((v, i, a) => a.indexOf(v) === i);

      return combined.length ? { list: combined, from, to } : null;
    };
  }

  /**
   * CodeMirror hint function for Twig expressions opened with '{{'.
   *
   * Includes engine context variables and settings.field / raw_settings.field
   * entries for each configured formatter field, filtered to the typed prefix.
   *
   * @param {object} editor - The CodeMirror editor instance.
   * @return {object|null} Hint result, or null if nothing to complete.
   */
  function twigVarHint(editor) {
    const cur = editor.getCursor();
    const before = editor.getLine(cur.line).slice(0, cur.ch);
    const m = before.match(/\{\{\s*([a-z_]*)$/i);
    if (!m) return null;
    const prefix = m[1].toLowerCase();
    const fields = getSettingsFields();
    const allVars = [
      ...TWIG_VARS,
      ...fields.flatMap((f) => [`settings.${f}`, `raw_settings.${f}`]),
    ];
    const matches = allVars.filter((v) => v.toLowerCase().startsWith(prefix));
    if (!matches.length) return null;
    return {
      list: matches,
      from: CodeMirror.Pos(cur.line, cur.ch - prefix.length),
      to: cur,
    };
  }

  /**
   * CodeMirror hint function for Drupal tokens typed as '[type:name]'.
   *
   * Includes [formatter_setting:field] and [formatter_setting:field:raw]
   * entries from drupalSettings context alongside the full token catalogue.
   *
   * @param {object} editor - The CodeMirror editor instance.
   * @return {object|null} Hint result, or null if nothing to complete.
   */
  function tokenHint(editor) {
    const tokens =
      drupalSettings &&
      drupalSettings.customFormatters &&
      drupalSettings.customFormatters.tokens
        ? drupalSettings.customFormatters.tokens
        : [];

    // Prepend formatter_setting tokens for this formatter's own fields.
    const fields = getSettingsFields();
    const settingTokens = fields.flatMap((f) => [
      `[formatter_setting:${f}]`,
      `[formatter_setting:${f}:raw]`,
    ]);
    const allTokens = [...settingTokens, ...tokens];

    const cur = editor.getCursor();
    const line = editor.getLine(cur.line);
    const bracketPos = line.lastIndexOf('[', cur.ch - 1);
    if (bracketPos === -1) return null;

    const typed = line.slice(bracketPos + 1, cur.ch);
    const matches = allTokens.filter((t) => t.slice(1).startsWith(typed));
    if (!matches.length) return null;

    return {
      list: matches,
      from: CodeMirror.Pos(cur.line, bracketPos),
      to: cur,
    };
  }

  Drupal.behaviors.customFormattersCodeMirrorHints = {
    /**
     * Wires context-aware autocomplete onto each CodeMirror instance.
     *
     * @param {Element} context - DOM element to attach behaviors to.
     */
    attach(context) {
      once('cf-codemirror-hints', 'textarea[data-codemirror]', context).forEach(
        (textarea) => {
          const wrapper = textarea.nextElementSibling;
          if (
            !wrapper ||
            !wrapper.classList.contains('CodeMirror') ||
            !wrapper.CodeMirror
          ) {
            return;
          }

          const cm = wrapper.CodeMirror;
          let options = {};
          try {
            options = JSON.parse(textarea.dataset.codemirror || '{}');
          } catch {
            // Malformed JSON — proceed with default options.
          }

          const mode = options.mode || '';
          const isHtml = mode === 'text/html' || mode === 'application/xml';
          const isPhp = mode === 'text/x-php';
          const isTwig = mode === 'html_twig';

          // Choose Ctrl+Space hint function per mode.
          let hintFn;
          if (isHtml) {
            hintFn = CodeMirror.hint.html;
          } else if (isPhp) {
            hintFn = makeContextHint(PHP_VARS, 'php');
          } else if (isTwig) {
            hintFn = makeContextHint(TWIG_VARS, 'twig');
          } else {
            hintFn = CodeMirror.hint.anyword;
          }

          cm.addKeyMap({
            'Ctrl-Space': (editor) => {
              CodeMirror.showHint(editor, hintFn, { completeSingle: false });
            },
          });

          // Auto-open HTML tag hints when '<' is typed or a letter is
          // typed while the cursor is within an opening tag name.
          // Uses a raw-text regex so it works even after autoCloseTags has
          // inserted '<>' and repositioned the cursor synchronously.
          if (isHtml || isTwig) {
            cm.on('keyup', (editor, event) => {
              if (editor.state.completionActive) return;
              const { key } = event;
              if (key !== '<' && !/^[a-zA-Z]$/.test(key)) return;
              const cur = editor.getCursor();
              const before = editor.getLine(cur.line).slice(0, cur.ch);
              if (/<[a-zA-Z]*$/.test(before)) {
                CodeMirror.showHint(editor, CodeMirror.hint.html, {
                  completeSingle: false,
                });
              }
            });
          }

          // Auto-open token hints when '[' is typed (HTML+Token only).
          if (isHtml) {
            cm.on('keyup', (editor, event) => {
              if (!editor.state.completionActive && event.key === '[') {
                CodeMirror.showHint(editor, tokenHint, {
                  completeSingle: false,
                });
              }
            });
          }

          // Auto-open Twig variable hints when '{{' is typed or a letter is
          // typed while the cursor is within a '{{ ' expression.
          if (isTwig) {
            cm.on('keyup', (editor, event) => {
              if (editor.state.completionActive) return;
              const { key } = event;
              if (key !== '{' && !/^[a-zA-Z_]$/.test(key)) return;
              const cur = editor.getCursor();
              const before = editor.getLine(cur.line).slice(0, cur.ch);
              if (/\{\{\s*[a-z_]*$/i.test(before)) {
                CodeMirror.showHint(editor, twigVarHint, {
                  completeSingle: false,
                });
              }
            });
          }
        },
      );
    },
  };
})(Drupal, once, drupalSettings);
