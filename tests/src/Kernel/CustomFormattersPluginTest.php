<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_formatters\Entity\FormatterSetting;
use Drupal\custom_formatters\FormatterInterface;
use Drupal\custom_formatters\Plugin\Field\FieldFormatter\CustomFormatters;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests internal methods of the CustomFormatters field formatter plugin.
 *
 * @group custom_formatters
 */
class CustomFormattersPluginTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_formatters',
    'field',
    'filter',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * The field formatter plugin under test.
   */
  private CustomFormatters $plugin;

  /**
   * The formatter config entity used in tests.
   */
  private FormatterInterface $formatter;

  /**
   * A test node with a body field.
   */
  private Node $node;

  /**
   * The entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('formatter_setting');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['custom_formatters', 'filter']);

    $this->entityTypeManager = \Drupal::entityTypeManager();

    $this->formatter = $this->createFormatter();
    $this->node = $this->createNodeWithBody();
    $this->plugin = $this->createPluginInstance();
  }

  /**
   * Tests that default settings includes an empty formatter_setting_uuid.
   */
  public function testDefaultSettings(): void {
    $this->assertSame('', $this->plugin->defaultSettings()['formatter_setting_uuid']);
  }

  /**
   * Tests that loadFormatter returns the expected formatter entity.
   */
  public function testLoadFormatter(): void {
    $formatter = $this->callProtected('loadFormatter');
    $this->assertInstanceOf(FormatterInterface::class, $formatter);
    $this->assertSame($this->formatter->id(), $formatter->id());
  }

  /**
   * Tests that loadFormatter returns the same instance on repeated calls.
   */
  public function testLoadFormatterReturnsCached(): void {
    $first = $this->callProtected('loadFormatter');
    $second = $this->callProtected('loadFormatter');
    $this->assertSame($first, $second);
  }

  /**
   * Tests that viewElements returns an empty array when the formatter is gone.
   */
  public function testViewElementsReturnsEmptyWhenFormatterDeleted(): void {
    $this->formatter->delete();
    \Drupal::service('plugin.manager.field.formatter')->clearCachedDefinitions();

    $result = $this->plugin->viewElements($this->node->get('body'), 'en');
    $this->assertSame([], $result);
  }

  /**
   * Tests that viewElements includes cache tags for the settings entity.
   */
  public function testViewElementsIncludesSettingsCacheTags(): void {
    $setting = $this->createFormatterSetting('test_value');
    $this->plugin->setSetting('formatter_setting_uuid', $setting->uuid());

    $result = $this->plugin->viewElements($this->node->get('body'), 'en');
    $this->assertNotEmpty($result);
    $delta = key($result);
    $cache_tags = $result[$delta]['#cache']['tags'];
    $this->assertContains('formatter_setting:' . $setting->id(), $cache_tags);
  }

  /**
   * Tests that settingsForm returns empty when no configurable fields exist.
   */
  public function testSettingsFormEmptyWithoutFields(): void {
    $result = $this->plugin->settingsForm([], new FormState());
    $this->assertSame([], $result);
  }

  /**
   * Tests that settingsForm includes the expected keys when fields exist.
   */
  public function testSettingsFormWithConfigurableFields(): void {
    $this->addSettingField();
    $form_state = new FormState();
    $result = $this->plugin->settingsForm([], $form_state);
    $this->assertArrayHasKey('formatter_setting_uuid', $result);
    $this->assertArrayHasKey('formatter_setting', $result);
  }

  /**
   * Tests that settingsSummary is empty when no setting entity is configured.
   */
  public function testSettingsSummaryEmptyWithoutSetting(): void {
    $this->addSettingField();
    $summary = $this->plugin->settingsSummary();
    $this->assertSame([], $summary);
  }

  /**
   * Tests that settingsSummary includes field values from the setting entity.
   */
  public function testSettingsSummaryWithSettingEntity(): void {
    $this->addSettingField();
    $setting = $this->createFormatterSetting('my label value');
    $this->plugin->setSetting('formatter_setting_uuid', $setting->uuid());

    $summary = $this->plugin->settingsSummary();
    $this->assertNotEmpty($summary);
    $this->assertStringContainsString('my label value', (string) reset($summary));
  }

  /**
   * Tests that loadSettingEntity returns null for an invalid UUID.
   */
  public function testLoadSettingEntityWithInvalidUuid(): void {
    $this->plugin->setSetting('formatter_setting_uuid', 'not-a-uuid');
    $result = $this->callProtected('loadSettingEntity');
    $this->assertNull($result);
  }

  /**
   * Tests that loadSettingEntity returns the correct entity for a valid UUID.
   */
  public function testLoadSettingEntityWithValidUuid(): void {
    $setting = $this->createFormatterSetting('test');
    $this->plugin->setSetting('formatter_setting_uuid', $setting->uuid());

    $result = $this->callProtected('loadSettingEntity');
    $this->assertInstanceOf(FormatterSetting::class, $result);
    $this->assertSame($setting->id(), $result->id());
  }

  /**
   * Tests that loadOrCreateSettingEntity returns an existing entity by UUID.
   */
  public function testLoadOrCreateSettingEntityExisting(): void {
    $setting = $this->createFormatterSetting('existing');
    $this->plugin->setSetting('formatter_setting_uuid', $setting->uuid());

    $formatter = $this->callProtected('loadFormatter');
    $result = $this->callProtected('loadOrCreateSettingEntity', [$formatter]);
    $this->assertSame($setting->id(), $result->id());
  }

  /**
   * Tests that loadOrCreateSettingEntity creates a new unsaved entity.
   */
  public function testLoadOrCreateSettingEntityNew(): void {
    $formatter = $this->callProtected('loadFormatter');
    $result = $this->callProtected('loadOrCreateSettingEntity', [$formatter]);
    $this->assertInstanceOf(FormatterSetting::class, $result);
    $this->assertTrue($result->isNew());
    $this->assertSame((string) $this->formatter->id(), $result->bundle());
  }

  /**
   * Tests that loadSettingsFromEntity returns rendered field values as strings.
   */
  public function testLoadSettingsFromEntityRendersValue(): void {
    $this->addSettingField();
    $setting = $this->createFormatterSetting('hello-rendered');
    $this->plugin->setSetting('formatter_setting_uuid', $setting->uuid());

    $this->createSettingViewDisplay([
      'field_setting_label' => ['type' => 'string', 'settings' => ['link_to_entity' => FALSE]],
    ]);

    $settings = $this->callProtected('loadSettingsFromEntity', ['en']);
    $this->assertArrayHasKey('field_setting_label', $settings);
    $this->assertStringContainsString('hello-rendered', (string) $settings['field_setting_label']);
  }

  /**
   * Tests that loadSettingsFromEntity returns empty when no setting is set.
   */
  public function testLoadSettingsFromEntityEmptyWithoutSetting(): void {
    $result = $this->callProtected('loadSettingsFromEntity', ['en']);
    $this->assertSame([], $result);
  }

  /**
   * Tests that validateSettingsEntity stashes the entity in form state.
   */
  public function testValidateSettingsEntityStashesEntity(): void {
    $this->addSettingField();
    $form_state = new FormState();
    $element = $this->buildSettingsElement($form_state);
    if (empty($element)) {
      $this->markTestSkipped('Settings form returned no element; cannot validate.');
    }

    $form_state->setValue(['formatter_setting', 'field_setting_label', 0, 'value'], 'stashed-value');
    CustomFormatters::validateSettingsEntity($element, $form_state);

    $stashed = $form_state->get('formatter_setting_entity_' . $this->formatter->id());
    $this->assertInstanceOf(FormatterSetting::class, $stashed);
    $this->assertTrue($stashed->isNew(), 'Entity should not be saved during validation.');
    $this->assertEquals('stashed-value', $stashed->get('field_setting_label')->value);
  }

  /**
   * Tests that validateSettingsEntity writes a valid UUID into form state.
   */
  public function testValidateSettingsEntitySetsUuid(): void {
    $this->addSettingField();
    $form_state = new FormState();
    $element = $this->buildSettingsElement($form_state);
    if (empty($element)) {
      $this->markTestSkipped('Settings form returned no element.');
    }

    $form_state->setValue(['formatter_setting', 'field_setting_label', 0, 'value'], 'uuid-test');
    CustomFormatters::validateSettingsEntity($element, $form_state);

    $uuid_value = $form_state->getValue(['formatter_setting_uuid']);
    $this->assertNotNull($uuid_value);
    $this->assertTrue(Uuid::isValid($uuid_value), 'UUID in form state must be valid.');
  }

  /**
   * Tests that submitForm persists the stashed entity from form state.
   */
  public function testSubmitFormSavesStashedEntity(): void {
    $this->addSettingField();
    $form_state = new FormState();
    $element = $this->buildSettingsElement($form_state);
    if (empty($element)) {
      $this->markTestSkipped('Settings form returned no element.');
    }

    $form_state->setValue(['formatter_setting', 'field_setting_label', 0, 'value'], 'saved-value');
    CustomFormatters::validateSettingsEntity($element, $form_state);

    $uuid = $form_state->getValue(['formatter_setting_uuid']);
    $this->assertNotNull($uuid);

    $this->plugin->submitForm([], $form_state);

    $entities = $this->entityTypeManager->getStorage('formatter_setting')
      ->loadByProperties(['uuid' => $uuid]);
    $this->assertCount(1, $entities, 'Entity must be saved after submitForm.');
  }

  /**
   * Creates and saves a test formatter entity.
   */
  private function createFormatter(): FormatterInterface {
    $formatter = $this->entityTypeManager
      ->getStorage('formatter')
      ->create([
        'id' => 'plugin_test',
        'label' => 'Plugin Test',
        'type' => 'twig',
        'field_types' => ['text_with_summary', 'text_long'],
        'data' => '{{ settings.field_setting_label }}-{{ items[0].value }}',
      ]);
    \assert($formatter instanceof FormatterInterface);
    $formatter->save();
    \Drupal::service('plugin.manager.field.formatter')->clearCachedDefinitions();
    return $formatter;
  }

  /**
   * Creates an article node type with a body field and returns a test node.
   */
  private function createNodeWithBody(): Node {
    NodeType::create(['type' => 'article'])->save();
    FieldStorageConfig::create([
      'field_name' => 'body',
      'type' => 'text_with_summary',
      'entity_type' => 'node',
    ])->save();
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Body',
    ])->save();

    $node = Node::create([
      'type' => 'article',
      'title' => 'Test article',
      'body' => ['value' => 'Body text', 'format' => 'plain_text'],
    ]);
    $node->save();
    return $node;
  }

  /**
   * Creates a node view display using the test formatter and returns it.
   */
  private function createPluginInstance(?string $override_formatter_id = NULL): CustomFormatters {
    $formatter_id = $override_formatter_id ?? $this->formatter->id();

    $display = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'default',
      'status' => TRUE,
      'content' => [
        'body' => [
          'type' => 'custom_formatters:' . $formatter_id,
          'label' => 'hidden',
          'settings' => [],
          'weight' => 0,
          'region' => 'content',
        ],
      ],
    ]);
    $display->save();

    $plugin = $display->getRenderer('body');
    \assert($plugin instanceof CustomFormatters);
    return $plugin;
  }

  /**
   * Adds a string field to the test formatter's formatter_setting bundle.
   */
  private function addSettingField(): void {
    if (!FieldStorageConfig::loadByName('formatter_setting', 'field_setting_label')) {
      FieldStorageConfig::create([
        'field_name' => 'field_setting_label',
        'type' => 'string',
        'entity_type' => 'formatter_setting',
      ])->save();
    }
    FieldConfig::create([
      'field_name' => 'field_setting_label',
      'entity_type' => 'formatter_setting',
      'bundle' => (string) $this->formatter->id(),
      'label' => 'Label text',
    ])->save();
  }

  /**
   * Creates and saves a FormatterSetting entity with the given field value.
   */
  private function createFormatterSetting(string $value): FormatterSetting {
    $setting = FormatterSetting::create([
      'formatter' => (string) $this->formatter->id(),
      'label' => 'Test setting',
      'field_setting_label' => $value,
    ]);
    $setting->save();
    return $setting;
  }

  /**
   * Creates a formatter_setting view display with the given field configs.
   */
  private function createSettingViewDisplay(array $fields): void {
    $content = [];
    $weight = 0;
    foreach ($fields as $field_name => $config) {
      $content[$field_name] = [
        'type' => $config['type'],
        'label' => 'hidden',
        'settings' => $config['settings'] ?? [],
        'third_party_settings' => [],
        'weight' => $weight++,
        'region' => 'content',
      ];
    }
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'formatter_setting',
      'bundle' => (string) $this->formatter->id(),
      'mode' => 'default',
      'status' => TRUE,
      'content' => $content,
    ]);
    $display->save();
  }

  /**
   * Returns the settings form element for the plugin, with parents set.
   */
  private function buildSettingsElement(FormStateInterface $form_state): array {
    $settings_form = $this->plugin->settingsForm([], $form_state);
    if (empty($settings_form['formatter_setting'])) {
      return [];
    }

    $element = $settings_form['formatter_setting'];
    $element['#parents'] = ['formatter_setting'];
    return $element;
  }

  /**
   * Invokes a protected method on the plugin and returns the result.
   */
  private function callProtected(string $method, array $args = []): mixed {
    $ref = new \ReflectionMethod(CustomFormatters::class, $method);
    $ref->setAccessible(TRUE);
    return $ref->invokeArgs($this->plugin, $args);
  }

}
