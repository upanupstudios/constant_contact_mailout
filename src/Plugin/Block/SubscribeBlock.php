<?php

namespace Drupal\constant_contact_mailout\Plugin\Block;

use Drupal\constant_contact_mailout\Form\SubscribeBlockForm;
use Drupal\constant_contact_mailout\Utility\TextHelper;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Constant Contact Subscribe block.
 *
 * @Block(
 *   id = "constant_contact_mailout_subscribe_block",
 *   admin_label = @Translation("Constant Contact Subscribe"),
 * )
 */
class SubscribeBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Config service.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The config instance.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * Creates a SelectionsBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory interface.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
    $this->configFactory = $config_factory;

    // Get settings.
    $this->settings = $this->configFactory->get('constant_contact_mailout.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'description' => NULL,
      'subscription' => 'select',
      'contact_list_ids' => [],
      'group_contact_lists_label' => $this->t('General'),
      'group_contact_lists' => FALSE,
      'group_contact_lists_delimiter' => NULL,
      'success_message' => NULL,
      'unsubscribe' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    // Get settings.
    $connections = $this->settings->get('connections');
    $contact_list_options = [];

    $form['description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#default_value' => $this->configuration['description'],
    ];

    $form['subscription'] = [
      '#title' => $this->t('Subscription'),
      '#type' => 'radios',
      '#default_value' => $this->configuration['subscription'],
      '#options' => [
        'dynamic' => $this->t('Subscribe users to dynamically created contact list from an entity'),
        // - Note mailout settings needs to be configured with Dynamic?
        // The signup should be independent of the mailout settings.
        // Which entity? chose which bundle
        'select' => $this->t('Subscribe users to selected contact lists'),
      ],
      '#attributes' => [
        'class' => [
          'subscription-radios',
        ],
      ],
    ];

    $form['subscription_select'] = [
      '#type' => 'details',
      // @todo Title is not working
      '#title' => $this > t('Select contact lists'),
      '#open' => TRUE,
      '#attributes' => [
        'class' => [
          'subscription-select-details',
        ],
      ],
    ];

    // @todo For dynamic creation, choose which entity
    // @todo Allow which connetions to use?
    if (!empty($connections)) {
      foreach ($connections as $connection) {
        if (!empty($connection['lists'])) {
          foreach ($connection['lists'] as $list) {
            $contact_list_id = $connection['id'] . ':' . $list['list_id'];

            $contact_list_options[$connection['name']][$contact_list_id] = $this->t('@name (@membership_count subscribers)', [
              '@name' => $list['name'],
              '@membership_count' => $list['membership_count'],
            ]);
          }
        }

        if (!empty($contact_list_options)) {
          foreach ($contact_list_options as $name => $options) {
            // Sort by value.
            // @todo Sort when saving
            uasort($options, function ($a, $b) {
              return strcmp($a->__toString(), $b->__toString());
            });

            // Store sorted list.
            $contact_list_options[$name] = $options;

            $id = TextHelper::textToMachineName($name);

            $form['subscription_select']['contact_list_ids'][$id] = [
              '#title' => $name,
              '#type' => 'checkboxes',
              '#multiple' => TRUE,
              '#options' => $options,
              '#default_value' => $this->configuration['contact_list_ids'],
            ];
          }
        }
        else {
          $form['subscription_select']['contact_list_ids'] = [
            '#markup' => $this->t('No contact lists.'),
          ];
        }
      }
    }
    else {
      $form['connections'] = [
        '#markup' => $this->t('No connections.'),
      ];
    }

    $form['group_contact_lists_label'] = [
      '#title' => $this->t('List Label'),
      '#description' => $this->t('If grouping contact lists is enabled, this list label will be used if the delimeter is not found.'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['group_contact_lists_label'],
    ];

    $form['group_contact_lists'] = [
      '#title' => $this->t('Group contact lists by delimiter'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['group_contact_lists'],
    ];

    $form['group_contact_lists_delimiter'] = [
      '#title' => $this->t('Delimeter'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['group_contact_lists_delimiter'],
    ];

    // @todo If no delimeter found put in general group automatically
    $form['success_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Success Message'),
      '#default_value' => $this->configuration['success_message'],
    ];

    $form['unsubscribe'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Unsubscribe'),
      '#default_value' => $this->configuration['unsubscribe'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);

    $values = $form_state->getValues();

    // Filter contact_list_ids.
    $contact_list_ids = [];

    if (!empty($values['subscription_select']['contact_list_ids'])) {
      foreach ($values['subscription_select']['contact_list_ids'] as $lists) {
        $contact_list_ids = array_merge($contact_list_ids, array_filter($lists));
      }
    }

    $this->configuration['description'] = $values['description']['value'];
    $this->configuration['subscription'] = $values['subscription'];
    $this->configuration['contact_list_ids'] = $contact_list_ids;
    $this->configuration['group_contact_lists_label'] = $values['group_contact_lists_label'];
    $this->configuration['group_contact_lists'] = $values['group_contact_lists'];
    $this->configuration['group_contact_lists_delimiter'] = $values['group_contact_lists_delimiter'];
    $this->configuration['success_message'] = $values['success_message'];
    $this->configuration['unsubscribe'] = $values['unsubscribe']['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $machine_name = $this->getMachineNameSuggestion();

    $build = [
      '#theme' => 'subscribe_block',
      '#subscribe_block_form' => $this->formBuilder->getForm(SubscribeBlockForm::class, $machine_name, $this->configuration),
      '#data' => [
        'description' => $this->configuration['description'],
      ],
    ];

    return $build;
  }

}
