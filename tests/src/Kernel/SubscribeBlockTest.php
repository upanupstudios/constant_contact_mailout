<?php

namespace Drupal\Tests\constant_contact_mailout\Kernel;

use Drupal\block\Entity\Block;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that each subscribe block is built from its own settings.
 *
 * @group constant_contact_mailout
 */
#[RunTestsInSeparateProcesses]
class SubscribeBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'block',
    'constant_contact_mailout',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('theme_installer')->install(['stark']);

    $this->config('constant_contact_mailout.settings')
      ->set('connections', [
        'conn1' => [
          'id' => 'conn1',
          'name' => 'City',
          'lists' => [
            ['list_id' => 'aaa', 'name' => 'Board A', 'membership_count' => 3],
            ['list_id' => 'bbb', 'name' => 'Board B', 'membership_count' => 5],
          ],
        ],
      ])
      ->save();
  }

  /**
   * Creates a subscribe block.
   *
   * @param string $id
   *   The block machine name.
   * @param array $settings
   *   Block settings merged over a block that shows no contact lists.
   *
   * @return \Drupal\block\Entity\Block
   *   The saved block.
   */
  protected function createSubscribeBlock(string $id, array $settings = []): Block {
    $block = Block::create([
      'id' => $id,
      'theme' => 'stark',
      'region' => 'content',
      'plugin' => 'constant_contact_mailout_subscribe_block',
      'settings' => $settings + [
        'label' => 'Sign Up for Email Updates',
        'fields' => ['email' => 'email'],
        'subscription' => 'select',
        'contact_list_ids' => ['conn1:aaa' => 'conn1:aaa'],
        'show_contact_lists' => FALSE,
        'group_contact_lists_label' => '',
      ],
    ]);
    $block->save();

    return $block;
  }

  /**
   * Returns the subscribe form a block builds.
   *
   * @param string $id
   *   The block machine name.
   *
   * @return array
   *   The form.
   */
  protected function buildBlockForm(string $id): array {
    // Load the block again so the plugin is built from the saved settings.
    \Drupal::entityTypeManager()->getStorage('block')->resetCache([$id]);
    $build = Block::load($id)->getPlugin()->build();

    return $build['#subscribe_block_form'];
  }

  /**
   * Blocks sharing a title each use their own settings and machine name.
   */
  public function testBlocksSharingTitle(): void {
    $this->createSubscribeBlock('first', [
      'show_contact_lists' => TRUE,
      'contact_list_ids' => ['conn1:aaa' => 'conn1:aaa', 'conn1:bbb' => 'conn1:bbb'],
    ]);
    // Saved last, so the old title lookup picked this block for both.
    $this->createSubscribeBlock('second');

    $first = $this->buildBlockForm('first');
    $this->assertSame('first', $first['machine_name']['#value']);
    $this->assertSame([
      'conn1:aaa' => 'Board A',
      'conn1:bbb' => 'Board B',
    ], $first['contact_list_ids-general']['#options']);

    $second = $this->buildBlockForm('second');
    $this->assertSame('second', $second['machine_name']['#value']);
    $this->assertArrayNotHasKey('contact_list_ids-general', $second);
  }

  /**
   * Saving a subscribe block stores its machine name in its settings.
   */
  public function testMachineNameStored(): void {
    $this->createSubscribeBlock('subscribe');

    $settings = $this->config('block.block.subscribe')->get('settings');
    $this->assertSame('subscribe', $settings['machine_name']);
  }

  /**
   * The update hook stores the machine name on blocks saved before 1.13.
   */
  public function testUpdateStoresMachineName(): void {
    $this->createSubscribeBlock('legacy');

    // Remove the machine name, as on a block saved by an earlier version.
    $config = \Drupal::configFactory()->getEditable('block.block.legacy');
    $settings = $config->get('settings');
    unset($settings['machine_name']);
    $config->set('settings', $settings)->save();

    \Drupal::moduleHandler()->loadInclude('constant_contact_mailout', 'install');
    $sandbox = [];
    constant_contact_mailout_update_10004($sandbox);

    $settings = $this->config('block.block.legacy')->get('settings');
    $this->assertSame('legacy', $settings['machine_name']);
  }

}
