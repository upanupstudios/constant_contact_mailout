<?php

namespace Drupal\Tests\constant_contact_mailout\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the widget of a mailout field using referenced contact lists.
 *
 * Nodes are unpublished and nothing is sent, so the Constant Contact API is
 * never called; contact lists come from the cached lists in config.
 *
 * @group constant_contact_mailout
 */
#[RunTestsInSeparateProcesses]
class ReferenceContactListWidgetTest extends KernelTestBase {

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
    'token',
  ];

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
    NodeType::create(['type' => 'event', 'name' => 'Event'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_mailout',
      'entity_type' => 'node',
      'type' => 'constant_contact_mailout',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_mailout',
      'entity_type' => 'node',
      'bundle' => 'board',
      'settings' => [
        'contact_list_creation' => 'select',
        'contact_list_ids' => ['conn1:bbb' => 'conn1:bbb'],
        'vocabularies' => [],
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_mailout',
      'entity_type' => 'node',
      'bundle' => 'event',
      'settings' => [
        'contact_list_creation' => 'reference',
        'node_types' => ['board' => 'board'],
        'vocabularies' => [],
      ],
    ])->save();

    // A reference that allows all bundles, like one using a Views selection
    // handler, has no target_bundles.
    foreach (['field_related' => NULL, 'field_board' => ['board' => 'board']] as $field_name => $target_bundles) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'settings' => ['target_type' => 'node'],
      ])->save();

      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => 'event',
        'settings' => [
          'handler' => 'default:node',
          'handler_settings' => ['target_bundles' => $target_bundles],
        ],
      ])->save();
    }

    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'event',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_mailout', [
      'type' => 'constant_contact_mailout_default',
    ])->save();
  }

  /**
   * References without target bundles are skipped when building the widget.
   */
  public function testReferenceWithoutTargetBundles(): void {
    $board = Node::create([
      'type' => 'board',
      'title' => 'Board',
      'status' => 0,
    ]);
    $board->save();

    $event = Node::create([
      'type' => 'event',
      'title' => 'Event',
      'status' => 0,
      'field_related' => $board->id(),
      'field_board' => $board->id(),
    ]);

    $display = EntityFormDisplay::collectRenderDisplay($event, 'default');
    $form = ['#parents' => []];
    $display->buildForm($event, $form, new FormState());

    $description = (string) $form['field_mailout']['widget'][0]['sendnow']['#description'];
    $this->assertStringContainsString('Board B', $description);
  }

}
