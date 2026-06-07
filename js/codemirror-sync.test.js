/**
 * @file
 * Tests for the CodeMirror sync behavior.
 *
 * Guards against the codemirror_editor module's once() regression where
 * only the first AJAX serialize call syncs editor content to the textarea.
 */

describe('Drupal.behaviors.customFormattersCodeMirrorSync', () => {
  let form;
  let cmElement;
  let mockSave;

  beforeEach(() => {
    global.Drupal = { behaviors: {} };

    // Re-execute the IIFE in an isolated module registry so each test
    // re-registers the behavior against the fresh global.Drupal.
    jest.isolateModules(() => {
      require('./codemirror-sync');
    });

    form = document.createElement('form');
    cmElement = document.createElement('div');
    cmElement.classList.add('CodeMirror');
    mockSave = jest.fn();
    cmElement.CodeMirror = { save: mockSave };
    form.appendChild(cmElement);
    document.body.appendChild(form);
  });

  afterEach(() => {
    document.body.removeChild(form);
    delete global.Drupal;
  });

  it('registers the behavior on Drupal.behaviors', () => {
    expect(global.Drupal.behaviors.customFormattersCodeMirrorSync).toBeDefined();
    expect(
      typeof global.Drupal.behaviors.customFormattersCodeMirrorSync.detach,
    ).toBe('function');
  });

  describe('detach()', () => {
    it('does nothing for non-serialize triggers', () => {
      global.Drupal.behaviors.customFormattersCodeMirrorSync.detach(
        form,
        {},
        'unload',
      );
      expect(mockSave).not.toHaveBeenCalled();
    });

    it('calls save() on each CodeMirror instance for the serialize trigger', () => {
      global.Drupal.behaviors.customFormattersCodeMirrorSync.detach(
        form,
        {},
        'serialize',
      );
      expect(mockSave).toHaveBeenCalledTimes(1);
    });

    // Regression test: the codemirror_editor module's detach() uses once(),
    // which marks elements on first call so subsequent calls find nothing.
    // Our behavior must call save() on every serialize, not just the first.
    it('calls save() on every repeated serialize trigger', () => {
      const behavior =
        global.Drupal.behaviors.customFormattersCodeMirrorSync;
      behavior.detach(form, {}, 'serialize');
      behavior.detach(form, {}, 'serialize');
      behavior.detach(form, {}, 'serialize');
      expect(mockSave).toHaveBeenCalledTimes(3);
    });

    it('saves all CodeMirror editors within the context', () => {
      const secondEl = document.createElement('div');
      secondEl.classList.add('CodeMirror');
      const secondSave = jest.fn();
      secondEl.CodeMirror = { save: secondSave };
      form.appendChild(secondEl);

      global.Drupal.behaviors.customFormattersCodeMirrorSync.detach(
        form,
        {},
        'serialize',
      );

      expect(mockSave).toHaveBeenCalledTimes(1);
      expect(secondSave).toHaveBeenCalledTimes(1);
    });

    it('skips .CodeMirror elements with no attached CodeMirror instance', () => {
      const bare = document.createElement('div');
      bare.classList.add('CodeMirror');
      form.appendChild(bare);

      expect(() =>
        global.Drupal.behaviors.customFormattersCodeMirrorSync.detach(
          form,
          {},
          'serialize',
        ),
      ).not.toThrow();

      expect(mockSave).toHaveBeenCalledTimes(1);
    });

    it('only syncs editors within the provided context, not outside it', () => {
      const outside = document.createElement('div');
      outside.classList.add('CodeMirror');
      const outsideSave = jest.fn();
      outside.CodeMirror = { save: outsideSave };
      document.body.appendChild(outside);

      global.Drupal.behaviors.customFormattersCodeMirrorSync.detach(
        form,
        {},
        'serialize',
      );

      expect(mockSave).toHaveBeenCalledTimes(1);
      expect(outsideSave).not.toHaveBeenCalled();

      document.body.removeChild(outside);
    });
  });
});
