<?php

namespace Drupal\Tests\constant_contact_mailout\Kernel;

use Drupal\constant_contact_mailout\EventSubscriber\DebugTemplateSubscriber;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the additional mailout details of a mailout field.
 *
 * Contact lists use the select method, so saving a published node never calls
 * the Constant Contact API, and the on-screen template debug setting stops the
 * mailout after the email template is rendered.
 *
 * @group constant_contact_mailout
 */
#[RunTestsInSeparateProcesses]
class MailoutDetailsTest extends KernelTestBase {

  use UserCreationTrait;

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
    // The subject field on the field settings form is validated by token.
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

    // The email template is rendered with the default theme.
    \Drupal::service('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();

    FilterFormat::create(['format' => 'basic_html', 'name' => 'Basic HTML'])->save();
    FilterFormat::create(['format' => 'restricted_html', 'name' => 'Restricted HTML'])->save();

    // An admin can use every text format offered by the details editor.
    $this->setUpCurrentUser([], [], TRUE);

    $this->config('constant_contact_mailout.settings')
      ->set('debug_render_template', TRUE)
      ->set('connections', [
        'conn1' => [
          'id' => 'conn1',
          'name' => 'City',
          'lists' => [
            ['list_id' => 'aaa', 'name' => 'Board A', 'membership_count' => 3],
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
        'contact_list_creation' => 'select',
        'contact_list_ids' => ['conn1:aaa' => 'conn1:aaa'],
        // The default (NULL) fails strict schema checks; it is a sequence.
        'vocabularies' => [],
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
   * Sets whether the field includes mailout details.
   */
  protected function setIncludeDetails(bool $include_details): void {
    FieldConfig::loadByName('node', 'board', static::FIELD_NAME)
      ->setSetting('include_details', $include_details)
      ->save();
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
   * Submits the node form display with the given widget values.
   */
  protected function submitWidget(NodeInterface $node, array $values): NodeInterface {
    // Build and submit the form display through the form builder, like the
    // node form does, so the text format element processes its value.
    $form_object = new class($node) extends FormBase {

      public function __construct(
        public NodeInterface $node,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function getFormId() {
        return 'constant_contact_mailout_test_details';
      }

      /**
       * {@inheritdoc}
       */
      public function buildForm(array $form, FormStateInterface $form_state) {
        EntityFormDisplay::collectRenderDisplay($this->node, 'default')->buildForm($this->node, $form, $form_state);
        return $form;
      }

      /**
       * {@inheritdoc}
       */
      public function submitForm(array &$form, FormStateInterface $form_state) {
        EntityFormDisplay::collectRenderDisplay($this->node, 'default')->extractFormValues($this->node, $form, $form_state);
      }

    };

    $form_state = new FormState();
    $form_state->setValues([static::FIELD_NAME => [0 => $values]]);
    \Drupal::formBuilder()->submitForm($form_object, $form_state);
    $this->assertSame([], $form_state->getErrors());

    return $form_object->node;
  }

  /**
   * Saves the node and returns the email template rendered for the mailout.
   */
  protected function saveAndGetTemplate(NodeInterface $node): ?string {
    // Node forms are submitted inside a render context, which rendering the
    // email template relies on.
    \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () use ($node) {
      $node->save();
    });

    return \Drupal::requestStack()->getCurrentRequest()->attributes->get(DebugTemplateSubscriber::REQUEST_ATTRIBUTE);
  }

  /**
   * The details editor is off by default.
   */
  public function testDetailsDisabledByDefault(): void {
    $field = FieldConfig::loadByName('node', 'board', static::FIELD_NAME);
    $this->assertFalse($field->getSetting('include_details'));

    $node = Node::create(['type' => 'board', 'title' => 'Board meeting']);
    $this->assertArrayNotHasKey('mailout_details', $this->buildWidget($node));
  }

  /**
   * The field settings form saves the details setting.
   */
  public function testFieldSettingsFormSavesIncludeDetails(): void {
    foreach ([TRUE, FALSE] as $include_details) {
      $field = FieldConfig::loadByName('node', 'board', static::FIELD_NAME);
      $form_object = \Drupal::entityTypeManager()->getFormObject('field_config', 'edit');
      $form_object->setEntity($field);

      $form_state = new FormState();
      $form_state->setValues([
        'label' => 'Mailout',
        'settings' => [
          'constant_contact' => [
            // Programmatic submissions treat any set checkbox value as
            // checked, so leave it out to uncheck it.
            'include_details' => $include_details ?: NULL,
          ],
        ],
      ]);
      \Drupal::formBuilder()->submitForm($form_object, $form_state);
      $this->assertSame([], $form_state->getErrors());

      // Without a triggering element only ::submitForm runs, so save here.
      $form_object->getEntity()->save();

      $field = FieldConfig::loadByName('node', 'board', static::FIELD_NAME);
      $this->assertSame($include_details, $field->getSetting('include_details'));
    }
  }

  /**
   * The widget shows a rich-text details editor when enabled.
   */
  public function testWidgetShowsDetailsEditor(): void {
    $this->setIncludeDetails(TRUE);

    $node = Node::create(['type' => 'board', 'title' => 'Board meeting']);
    $element = $this->buildWidget($node)['mailout_details'];

    $this->assertSame('text_format', $element['#type']);
    $this->assertSame(['basic_html', 'restricted_html'], $element['#allowed_formats']);
  }

  /**
   * Details entered on the form are sent with the mailout but not saved.
   */
  public function testDetailsAreSentWithMailout(): void {
    $this->setIncludeDetails(TRUE);

    $node = Node::create(['type' => 'board', 'title' => 'Board meeting', 'status' => 1]);
    $node = $this->submitWidget($node, [
      'sendnow' => 1,
      'mailout_details' => ['value' => '<p>The meeting moved to <strong>Room 2</strong>.</p>', 'format' => 'basic_html'],
    ]);

    $this->assertSame(
      ['value' => '<p>The meeting moved to <strong>Room 2</strong>.</p>', 'format' => 'basic_html'],
      $node->get(static::FIELD_NAME)->mailout_details,
    );

    $template = $this->saveAndGetTemplate($node);
    $this->assertStringContainsString('<p>The meeting moved to Room 2.</p>', $template);

    // The details belong to this mailout only.
    $node = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node->id());
    $this->assertNull($node->get(static::FIELD_NAME)->mailout_details);
  }

  /**
   * Empty details are left out of the mailout.
   */
  public function testEmptyDetailsAreNotSent(): void {
    $this->setIncludeDetails(TRUE);

    $node = Node::create(['type' => 'board', 'title' => 'Board meeting', 'status' => 1]);
    $node = $this->submitWidget($node, [
      'sendnow' => 1,
      'mailout_details' => ['value' => '', 'format' => 'basic_html'],
    ]);

    $template = $this->saveAndGetTemplate($node);
    $this->assertStringContainsString('Board meeting', $template);
    $this->assertStringNotContainsString('<p></p>', $template);
  }

  /**
   * The template receives the details as a variable.
   */
  public function testTemplateReceivesDetails(): void {
    $node = Node::create(['type' => 'board', 'title' => 'Board meeting']);
    $node->save();
    $item = $node->get(static::FIELD_NAME)->appendItem();

    $details = ['value' => 'Bring a chair.', 'format' => 'basic_html'];
    $render = fn ($details) => \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), fn () => (string) $item->prepareTemplate($node, TRUE, $details));

    $this->assertStringContainsString('<p>Bring a chair.</p>', $render($details));
    $this->assertStringNotContainsString('Bring a chair.', $render(NULL));
  }

}
