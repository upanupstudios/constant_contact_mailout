<?php

namespace Drupal\Tests\constant_contact_mailout\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "send now" default value of a mailout field.
 *
 * Nodes are unpublished and nothing is sent, so the Constant Contact API is
 * never called; contact lists come from the cached lists in config.
 *
 * @group constant_contact_mailout
 */
#[RunTestsInSeparateProcesses]
class SendNowDefaultValueTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'constant_contact_mailout',
    'field_ui',
    'token',
  ];

  /**
   * The field name.
   */
  public const FIELD_NAME = 'field_mailout';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'filter']);

    $this->config('constant_contact_mailout.settings')
      ->set('connections', [
        [
          'id' => 'conn1',
          'name' => 'City',
          'lists' => [
            ['list_id' => 'aaa', 'name' => 'Board A', 'membership_count' => 3],
            ['list_id' => 'bbb', 'name' => 'Board B', 'membership_count' => 5],
          ],
        ],
      ])
      ->save();

    NodeType::create(['type' => 'board', 'name' => 'Board'])->save();

    FieldStorageConfig::create([
      'field_name' => static::FIELD_NAME,
      'entity_type' => 'node',
      'type' => 'constant_contact_mailout',
    ])->save();

    FieldConfig::create([
      'field_name' => static::FIELD_NAME,
      'entity_type' => 'node',
      'bundle' => 'board',
      'settings' => [
        'contact_list_creation' => 'dynamic',
        'connection_id' => 'conn1',
        'contact_list_prefix' => 'Board: ',
        // The default (NULL) fails strict schema checks; it is a sequence.
        'vocabularies' => [],
        // Adds the scheduling elements, which must not end up in config.
        'scheduled_mailout' => TRUE,
      ],
    ])->save();

    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'board',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent(static::FIELD_NAME, [
      'type' => 'constant_contact_mailout_default',
    ])->save();
  }

  /**
   * Submits the field settings form with the given default value input.
   */
  protected function submitFieldSettings(array $default_value_input): FieldConfig {
    $field = FieldConfig::loadByName('node', 'board', static::FIELD_NAME);
    $form_object = \Drupal::entityTypeManager()->getFormObject('field_config', 'edit');
    $form_object->setEntity($field);

    $form_state = new FormState();
    $form_state->setValues([
      'label' => 'Mailout',
      'set_default_value' => 1,
      'default_value_input' => [static::FIELD_NAME => [0 => $default_value_input]],
    ]);
    \Drupal::formBuilder()->submitForm($form_object, $form_state);
    $this->assertSame([], $form_state->getErrors());

    // Without a triggering element only ::submitForm runs, so save here.
    $form_object->getEntity()->save();

    return FieldConfig::loadByName('node', 'board', static::FIELD_NAME);
  }

  /**
   * Builds the widget element for delta 0 of the given node's form.
   */
  protected function buildWidget(NodeInterface $node): array {
    $display = EntityFormDisplay::collectRenderDisplay($node, 'default');
    $form = ['#parents' => []];
    $display->buildForm($node, $form, new FormState());

    return $form[static::FIELD_NAME]['widget'][0];
  }

  /**
   * A checked "send now" default is saved and pre-checks new entity forms.
   */
  public function testSendNowDefaultIsSaved(): void {
    $field = $this->submitFieldSettings(['sendnow' => 1]);
    $this->assertSame([['contact_list_id' => NULL, 'sendnow' => TRUE]], $field->getDefaultValueLiteral());

    $node = Node::create(['type' => 'board', 'title' => 'New board']);
    $this->assertTrue($this->buildWidget($node)['sendnow']['#default_value']);

    // The default never becomes an item value, so saving in code cannot send.
    $this->assertNull($node->get(static::FIELD_NAME)->sendnow);

    // Existing entities are never pre-checked.
    $node->save();
    $this->assertFalse($this->buildWidget($node)['sendnow']['#default_value']);
  }

  /**
   * Extracts "send now" values from a new node form into the node.
   */
  protected function extractSendNow(bool $field_access): ?bool {
    $node = Node::create(['type' => 'board', 'title' => 'New board']);

    // Build and submit the form display through the form builder, like the
    // node form does, so element access and value callbacks are applied.
    $form_object = new class($node, $field_access) extends FormBase {

      public function __construct(
        public NodeInterface $node,
        protected bool $fieldAccess,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function getFormId() {
        return 'constant_contact_mailout_test_sendnow';
      }

      /**
       * {@inheritdoc}
       */
      public function buildForm(array $form, FormStateInterface $form_state) {
        EntityFormDisplay::collectRenderDisplay($this->node, 'default')->buildForm($this->node, $form, $form_state);
        $form[SendNowDefaultValueTest::FIELD_NAME]['#access'] = $this->fieldAccess;
        return $form;
      }

      /**
       * {@inheritdoc}
       */
      public function submitForm(array &$form, FormStateInterface $form_state) {
        EntityFormDisplay::collectRenderDisplay($this->node, 'default')->extractFormValues($this->node, $form, $form_state);
      }

    };

    // Leave sendnow out of the input, as a browser does for an unchecked or
    // hidden checkbox.
    $form_state = new FormState();
    \Drupal::formBuilder()->submitForm($form_object, $form_state);

    $sendnow = $form_object->node->get(static::FIELD_NAME)->sendnow;
    return $sendnow === NULL ? NULL : (bool) $sendnow;
  }

  /**
   * Only a checkbox the editor can see is able to send a mailout.
   */
  public function testSendNowRequiresAccess(): void {
    $this->submitFieldSettings(['sendnow' => 1]);

    $this->assertTrue($this->extractSendNow(TRUE));
    $this->assertFalse($this->extractSendNow(FALSE));
  }

  /**
   * An unchecked "send now" default can be saved again.
   */
  public function testSendNowDefaultCanBeUnset(): void {
    $this->submitFieldSettings(['sendnow' => 1]);
    // Programmatic submissions treat any set checkbox value as checked.
    $field = $this->submitFieldSettings(['sendnow' => NULL]);
    $this->assertSame([['contact_list_id' => NULL, 'sendnow' => FALSE]], $field->getDefaultValueLiteral());

    $node = Node::create(['type' => 'board', 'title' => 'New board']);
    $this->assertFalse($this->buildWidget($node)['sendnow']['#default_value']);
  }

}
