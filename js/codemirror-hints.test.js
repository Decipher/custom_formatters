/**
 * @file
 * Tests for the CodeMirror context-aware hints behavior.
 */

describe('Drupal.behaviors.customFormattersCodeMirrorHints', () => {
  let form;
  let textarea;
  let cmWrapper;
  let mockAddKeyMap;
  let mockOn;

  const htmlHint = jest.fn();
  const xmlHint = jest.fn();
  const anywordHint = jest
    .fn()
    .mockReturnValue({ list: ['word'], from: { line: 0, ch: 0 }, to: { line: 0, ch: 4 } });
  const mockShowHint = jest.fn();

  function setMode(mode) {
    textarea.dataset.codemirror = JSON.stringify({ mode });
  }

  function makeCm(overrides = {}) {
    return {
      addKeyMap: mockAddKeyMap,
      on: mockOn,
      getCursor: jest.fn().mockReturnValue({ line: 0, ch: 0 }),
      getTokenAt: jest.fn().mockReturnValue({ string: '', start: 0, end: 0 }),
      getLine: jest.fn().mockReturnValue(''),
      state: { completionActive: false },
      ...overrides,
    };
  }

  beforeEach(() => {
    global.Drupal = { behaviors: {} };
    global.drupalSettings = {
      customFormatters: {
        tokens: ['[node:title]', '[node:body]', '[user:name]'],
        context: { mode: 'text/x-php', settingsFields: ['color', 'size'] },
      },
    };
    global.CodeMirror = {
      showHint: mockShowHint,
      hint: { html: htmlHint, xml: xmlHint, anyword: anywordHint },
      Pos: (line, ch) => ({ line, ch }),
    };
    global.once = jest
      .fn()
      .mockImplementation((_key, selector, ctx) =>
        Array.from(ctx.querySelectorAll(selector)),
      );

    mockAddKeyMap = jest.fn();
    mockOn = jest.fn();

    jest.isolateModules(() => {
      require('./codemirror-hints');
    });

    form = document.createElement('form');
    textarea = document.createElement('textarea');
    setMode('text/x-php');
    cmWrapper = document.createElement('div');
    cmWrapper.classList.add('CodeMirror');
    cmWrapper.CodeMirror = makeCm();
    form.appendChild(textarea);
    form.appendChild(cmWrapper);
    document.body.appendChild(form);
  });

  afterEach(() => {
    document.body.removeChild(form);
    delete global.Drupal;
    delete global.drupalSettings;
    delete global.CodeMirror;
    delete global.once;
    jest.resetAllMocks();
  });

  it('registers the behavior on Drupal.behaviors', () => {
    expect(global.Drupal.behaviors.customFormattersCodeMirrorHints).toBeDefined();
    expect(typeof global.Drupal.behaviors.customFormattersCodeMirrorHints.attach).toBe('function');
  });

  describe('attach() guards', () => {
    it('does nothing when context has no matching textarea', () => {
      global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(document.createElement('div'));
      expect(mockAddKeyMap).not.toHaveBeenCalled();
    });

    it('skips a textarea with no .CodeMirror next sibling', () => {
      const el = document.createElement('form');
      const ta = document.createElement('textarea');
      ta.dataset.codemirror = '{}';
      el.appendChild(ta);
      document.body.appendChild(el);
      global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(el);
      expect(mockAddKeyMap).not.toHaveBeenCalled();
      document.body.removeChild(el);
    });

    it('does not throw when data-codemirror is malformed JSON', () => {
      textarea.dataset.codemirror = 'not-json{';
      expect(() =>
        global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(form),
      ).not.toThrow();
    });

    it('uses once() to prevent wiring the same instance twice', () => {
      global.once.mockReturnValueOnce([textarea]).mockReturnValueOnce([]);
      global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(form);
      global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(form);
      expect(mockAddKeyMap).toHaveBeenCalledTimes(1);
    });
  });

  describe('Ctrl+Space hint selection', () => {
    function triggerCtrlSpace() {
      global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(form);
      const [keyMap] = mockAddKeyMap.mock.calls[0];
      keyMap['Ctrl-Space'](cmWrapper.CodeMirror);
    }

    it('uses html hint for text/html mode', () => {
      setMode('text/html');
      triggerCtrlSpace();
      expect(mockShowHint).toHaveBeenCalledWith(cmWrapper.CodeMirror, htmlHint, { completeSingle: false });
    });

    it('uses html hint for application/xml mode', () => {
      setMode('application/xml');
      triggerCtrlSpace();
      expect(mockShowHint).toHaveBeenCalledWith(cmWrapper.CodeMirror, htmlHint, { completeSingle: false });
    });

    it('uses a context hint function for text/x-php mode', () => {
      setMode('text/x-php');
      triggerCtrlSpace();
      expect(mockShowHint).toHaveBeenCalledWith(
        cmWrapper.CodeMirror,
        expect.any(Function),
        { completeSingle: false },
      );
    });

    it('uses a context hint function for html_twig mode', () => {
      setMode('html_twig');
      triggerCtrlSpace();
      expect(mockShowHint).toHaveBeenCalledWith(
        cmWrapper.CodeMirror,
        expect.any(Function),
        { completeSingle: false },
      );
    });

    it('uses anyword for an unknown mode', () => {
      setMode('text/x-sql');
      triggerCtrlSpace();
      expect(mockShowHint).toHaveBeenCalledWith(cmWrapper.CodeMirror, anywordHint, { completeSingle: false });
    });
  });

  describe('PHP context hint (makeContextHint)', () => {
    function getHintFn() {
      setMode('text/x-php');
      global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(form);
      const [keyMap] = mockAddKeyMap.mock.calls[0];
      let captured;
      mockShowHint.mockImplementation((_e, fn) => { captured = fn; });
      keyMap['Ctrl-Space'](cmWrapper.CodeMirror);
      return captured;
    }

    it('includes $raw_settings in predefined PHP vars', () => {
      cmWrapper.CodeMirror.getTokenAt = jest.fn().mockReturnValue({ string: '$r', start: 0, end: 2 });
      cmWrapper.CodeMirror.getLine = jest.fn().mockReturnValue('$r');
      cmWrapper.CodeMirror.getCursor = jest.fn().mockReturnValue({ line: 0, ch: 2 });
      const fn = getHintFn();
      const result = fn(cmWrapper.CodeMirror);
      expect(result.list).toContain('$raw_settings');
    });

    it('suggests field names after $settings[\'', () => {
      const before = "$settings['col";
      cmWrapper.CodeMirror.getLine = jest.fn().mockReturnValue(before + 'or');
      cmWrapper.CodeMirror.getCursor = jest.fn().mockReturnValue({ line: 0, ch: before.length });
      cmWrapper.CodeMirror.getTokenAt = jest.fn().mockReturnValue({ string: 'col', start: 11, end: 14 });
      const fn = getHintFn();
      const result = fn(cmWrapper.CodeMirror);
      expect(result.list).toContain("color']");
      expect(result.list).not.toContain("size']");
    });

    it('suggests field names after $raw_settings[\'', () => {
      const before = "$raw_settings['";
      cmWrapper.CodeMirror.getLine = jest.fn().mockReturnValue(before);
      cmWrapper.CodeMirror.getCursor = jest.fn().mockReturnValue({ line: 0, ch: before.length });
      cmWrapper.CodeMirror.getTokenAt = jest.fn().mockReturnValue({ string: '', start: 15, end: 15 });
      const fn = getHintFn();
      const result = fn(cmWrapper.CodeMirror);
      expect(result.list).toContain("color']");
      expect(result.list).toContain("size']");
    });

    it('falls back to predefined + anyword when not in settings context', () => {
      cmWrapper.CodeMirror.getTokenAt = jest.fn().mockReturnValue({ string: '$it', start: 0, end: 3 });
      cmWrapper.CodeMirror.getLine = jest.fn().mockReturnValue('$items');
      cmWrapper.CodeMirror.getCursor = jest.fn().mockReturnValue({ line: 0, ch: 3 });
      anywordHint.mockReturnValue({ list: ['$items'], from: { line: 0, ch: 0 }, to: { line: 0, ch: 3 } });
      const fn = getHintFn();
      const result = fn(cmWrapper.CodeMirror);
      expect(result.list).toContain('$items');
    });
  });

  describe('Twig context hint (makeContextHint)', () => {
    function getHintFn() {
      setMode('html_twig');
      global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(form);
      const [keyMap] = mockAddKeyMap.mock.calls[0];
      let captured;
      mockShowHint.mockImplementation((_e, fn) => { captured = fn; });
      keyMap['Ctrl-Space'](cmWrapper.CodeMirror);
      return captured;
    }

    it('includes raw_settings in predefined Twig vars', () => {
      cmWrapper.CodeMirror.getTokenAt = jest.fn().mockReturnValue({ string: 'ra', start: 0, end: 2 });
      cmWrapper.CodeMirror.getLine = jest.fn().mockReturnValue('ra');
      cmWrapper.CodeMirror.getCursor = jest.fn().mockReturnValue({ line: 0, ch: 2 });
      const fn = getHintFn();
      const result = fn(cmWrapper.CodeMirror);
      expect(result.list).toContain('raw_settings');
    });

    it('suggests field names after settings.', () => {
      const before = 'settings.col';
      cmWrapper.CodeMirror.getLine = jest.fn().mockReturnValue(before);
      cmWrapper.CodeMirror.getCursor = jest.fn().mockReturnValue({ line: 0, ch: before.length });
      cmWrapper.CodeMirror.getTokenAt = jest.fn().mockReturnValue({ string: 'col', start: 9, end: 12 });
      const fn = getHintFn();
      const result = fn(cmWrapper.CodeMirror);
      expect(result.list).toContain('color');
      expect(result.list).not.toContain('size');
    });

    it('suggests field names after raw_settings.', () => {
      const before = 'raw_settings.';
      cmWrapper.CodeMirror.getLine = jest.fn().mockReturnValue(before);
      cmWrapper.CodeMirror.getCursor = jest.fn().mockReturnValue({ line: 0, ch: before.length });
      cmWrapper.CodeMirror.getTokenAt = jest.fn().mockReturnValue({ string: '', start: 13, end: 13 });
      const fn = getHintFn();
      const result = fn(cmWrapper.CodeMirror);
      expect(result.list).toContain('color');
      expect(result.list).toContain('size');
    });
  });

  describe('auto-trigger: < for HTML tags', () => {
    function keyupHandler() {
      setMode('text/html');
      global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(form);
      return mockOn.mock.calls.filter(([e]) => e === 'keyup')[0][1];
    }

    it('triggers html hint when < is typed', () => {
      const editor = makeCm({
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 1 }),
        getLine: jest.fn().mockReturnValue('<'),
      });
      keyupHandler()(editor, { key: '<' });
      expect(mockShowHint).toHaveBeenCalledWith(editor, htmlHint, { completeSingle: false });
    });

    it('triggers html hint when a letter is typed inside a tag name', () => {
      const editor = makeCm({
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 4 }),
        getLine: jest.fn().mockReturnValue('<div'),
      });
      keyupHandler()(editor, { key: 'v' });
      expect(mockShowHint).toHaveBeenCalledWith(editor, htmlHint, { completeSingle: false });
    });

    it('does not trigger for a letter outside a tag context', () => {
      const editor = makeCm({
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 5 }),
        getLine: jest.fn().mockReturnValue('hello'),
      });
      keyupHandler()(editor, { key: 'o' });
      expect(mockShowHint).not.toHaveBeenCalled();
    });

    it('does not trigger when a completion is already active', () => {
      const editor = makeCm({
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 1 }),
        getLine: jest.fn().mockReturnValue('<'),
        state: { completionActive: true },
      });
      keyupHandler()(editor, { key: '<' });
      expect(mockShowHint).not.toHaveBeenCalled();
    });

    it('also registers < trigger for html_twig mode', () => {
      setMode('html_twig');
      global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(form);
      expect(mockOn).toHaveBeenCalledWith('keyup', expect.any(Function));
    });
  });

  describe('auto-trigger: [ for tokens (HTML+Token)', () => {
    function getTokenKeyupHandler() {
      setMode('text/html');
      global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(form);
      // Two keyup listeners in html mode: one for '<', one for '['
      return mockOn.mock.calls.filter(([e]) => e === 'keyup')[1][1];
    }

    it('triggers token hint when [ is typed', () => {
      const editor = makeCm({
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 6 }),
        getLine: jest.fn().mockReturnValue('[node:'),
      });
      getTokenKeyupHandler()(editor, { key: '[' });
      expect(mockShowHint).toHaveBeenCalledWith(editor, expect.any(Function), { completeSingle: false });
    });

    it('does not register [ trigger for PHP mode', () => {
      setMode('text/x-php');
      global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(form);
      expect(mockOn).not.toHaveBeenCalled();
    });
  });

  describe('auto-trigger: {{ for Twig variables', () => {
    function getTwigKeyupHandler() {
      setMode('html_twig');
      global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(form);
      // In Twig mode there are two keyup listeners: '<' and '{{'
      return mockOn.mock.calls.filter(([e]) => e === 'keyup')[1][1];
    }

    function captureTwigHintFn(line, ch, key) {
      const editor = makeCm({
        getCursor: jest.fn().mockReturnValue({ line: 0, ch }),
        getLine: jest.fn().mockReturnValue(line),
      });
      let capturedFn;
      mockShowHint.mockImplementation((_e, fn) => { capturedFn = fn; });
      getTwigKeyupHandler()(editor, { key });
      return capturedFn;
    }

    it('triggers twig hint when {{ is typed', () => {
      const editor = makeCm({
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 2 }),
        getLine: jest.fn().mockReturnValue('{{'),
      });
      getTwigKeyupHandler()(editor, { key: '{' });
      expect(mockShowHint).toHaveBeenCalledWith(editor, expect.any(Function), { completeSingle: false });
    });

    it('twigVarHint returns all TWIG_VARS when cursor is right after {{', () => {
      const fn = captureTwigHintFn('{{', 2, '{');
      expect(fn).toBeDefined();
      const fakeEditor = {
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 2 }),
        getLine: jest.fn().mockReturnValue('{{'),
      };
      const result = fn(fakeEditor);
      expect(result).not.toBeNull();
      expect(result.list).toContain('items');
      expect(result.list).toContain('settings');
      expect(result.list).toContain('raw_settings');
    });

    it('twigVarHint filters by prefix after {{ space', () => {
      const fn = captureTwigHintFn('{{ set', 6, '{');
      expect(fn).toBeDefined();
      const fakeEditor = {
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 6 }),
        getLine: jest.fn().mockReturnValue('{{ set'),
      };
      const result = fn(fakeEditor);
      expect(result).not.toBeNull();
      expect(result.list).toContain('settings');
      expect(result.list).not.toContain('items');
    });

    it('twigVarHint includes settings.field and raw_settings.field for configured fields', () => {
      const fn = captureTwigHintFn('{{', 2, '{');
      expect(fn).toBeDefined();
      const fakeEditor = {
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 2 }),
        getLine: jest.fn().mockReturnValue('{{'),
      };
      const result = fn(fakeEditor);
      expect(result.list).toContain('settings.color');
      expect(result.list).toContain('raw_settings.color');
      expect(result.list).toContain('settings.size');
      expect(result.list).toContain('raw_settings.size');
    });

    it('triggers twig hint when a letter is typed inside a {{ expression', () => {
      const editor = makeCm({
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 5 }),
        getLine: jest.fn().mockReturnValue('{{ se'),
      });
      getTwigKeyupHandler()(editor, { key: 'e' });
      expect(mockShowHint).toHaveBeenCalledWith(editor, expect.any(Function), { completeSingle: false });
    });

    it('does not trigger for a letter outside a {{ context', () => {
      const editor = makeCm({
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 5 }),
        getLine: jest.fn().mockReturnValue('hello'),
      });
      getTwigKeyupHandler()(editor, { key: 'o' });
      expect(mockShowHint).not.toHaveBeenCalled();
    });

    it('does not trigger when not in a {{ context', () => {
      const editor = makeCm({
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 1 }),
        getLine: jest.fn().mockReturnValue('{'),
      });
      getTwigKeyupHandler()(editor, { key: '{' });
      expect(mockShowHint).not.toHaveBeenCalled();
    });

    it('does not trigger when a completion is already active', () => {
      const editor = makeCm({
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 2 }),
        getLine: jest.fn().mockReturnValue('{{'),
        state: { completionActive: true },
      });
      getTwigKeyupHandler()(editor, { key: '{' });
      expect(mockShowHint).not.toHaveBeenCalled();
    });
  });

  describe('tokenHint includes formatter_setting tokens', () => {
    it('returns [formatter_setting:field] and :raw variants', () => {
      setMode('text/html');
      const editor = makeCm({
        getCursor: jest.fn().mockReturnValue({ line: 0, ch: 20 }),
        getLine: jest.fn().mockReturnValue('[formatter_setting:co'),
        state: { completionActive: false },
      });
      global.Drupal.behaviors.customFormattersCodeMirrorHints.attach(form);
      const tokenKeyup = mockOn.mock.calls.filter(([e]) => e === 'keyup')[1][1];
      let tokenHintFn;
      mockShowHint.mockImplementation((_e, fn) => { tokenHintFn = fn; });
      tokenKeyup(editor, { key: '[' });
      const result = tokenHintFn(editor);
      expect(result.list).toContain('[formatter_setting:color]');
      expect(result.list).toContain('[formatter_setting:color:raw]');
    });
  });
});
