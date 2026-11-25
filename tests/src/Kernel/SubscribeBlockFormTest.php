<?php

namespace Drupal\Tests\constant_contact_mailout\Kernel;

use Drupal\constant_contact_mailout\Form\SubscribeBlockForm;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the contact lists shown on the subscribe block form.
 *
 * @group constant_contact_mailout
 */
#[RunTestsInSeparateProcesses]
class SubscribeBlockFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'constant_contact_mailout',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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
   * Builds the subscribe form with the given block settings.
   *
   * @param array $settings
   *   Block settings merged over the defaults of a block that shows lists.
   *
   * @return array
   *   The built form.
   */
  protected function buildSubscribeForm(array $settings): array {
    $settings += [
      'machine_name' => 'subscribe',
      'subscription' => 'select',
      'fields' => ['email' => 'email'],
      'contact_list_ids' => ['conn1:aaa', 'conn1:bbb'],
      'show_contact_lists' => TRUE,
      'show_select_all' => FALSE,
      'group_contact_lists_label' => '',
      'group_contact_lists' => FALSE,
      'group_contact_lists_delimiter' => '',
      'group_contact_lists_order' => '',
    ];

    return \Drupal::formBuilder()->getForm(SubscribeBlockForm::class, ['settings' => $settings]);
  }

  /**
   * Lists fall under "General" when the group label is left empty.
   */
  public function testEmptyGroupLabelUsesGeneral(): void {
    $form = $this->buildSubscribeForm([]);

    $this->assertArrayHasKey('contact_list_ids-general', $form);
    $this->assertSame('General', (string) $form['contact_list_ids-general']['#title']);
    $this->assertSame([
      'conn1:aaa' => 'Board A',
      'conn1:bbb' => 'Board B',
    ], $form['contact_list_ids-general']['#options']);
  }

  /**
   * A group label set on the block is used as the group title.
   */
  public function testGroupLabel(): void {
    $form = $this->buildSubscribeForm(['group_contact_lists_label' => 'Boards']);

    $this->assertArrayHasKey('contact_list_ids-boards', $form);
    $this->assertSame('Boards', (string) $form['contact_list_ids-boards']['#title']);
  }

  /**
   * No list checkboxes are shown when "Show contact lists" is off.
   */
  public function testContactListsHidden(): void {
    $form = $this->buildSubscribeForm(['show_contact_lists' => FALSE]);

    $this->assertEmpty(preg_grep('/^contact_list_ids-/', array_keys($form)));
  }

}
