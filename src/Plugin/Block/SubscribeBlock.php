<?php

namespace Drupal\constant_contact_mailout\Plugin\Block;

use Drupal\block\Entity\Block;
use Drupal\constant_contact_mailout\Form\SubscribeBlockForm;
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
      'fields' => [],
      'subscription' => 'select',
      'connection_id' => NULL,
      'contact_list_prefix' => NULL,
      'contact_list_ids' => [],
      'show_contact_lists' => TRUE,
      'show_select_all' => FALSE,
      'group_contact_lists_label' => $this->t('General'),
      'group_contact_lists' => FALSE,
      'group_contact_lists_delimiter' => NULL,
      'group_contact_lists_order' => NULL,
      'subscribed_message' => NULL,
      'unsubscribed_message' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['constant_contact'] = [
      '#type' => 'details',
      '#title' => $this->t('Constant Contact'),
      '#open' => TRUE,
    ];

    $form['constant_contact']['fields'] = [
      '#title' => $this->t('Fields'),
      '#description' => $this->t('Check fields to be displayed on the subscription form.'),
      '#type' => 'checkboxes',
      '#required' => TRUE,
      '#default_value' => $this->configuration['fields'],
      '#options' => [
        'first_name' => $this->t('First Name'),
        'last_name' => $this->t('Last Name'),
        'email' => $this->t('Email (required)'),
        'confirm_email' => $this->t('Confirm Email'),
      ],
    ];

    $form['constant_contact']['description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#default_value' => $this->configuration['description'],
    ];

    // Get settings.
    $connections = $this->settings->get('connections');
    $active_connections = [];
    $contact_lists = [];

    if (!empty($connections)) {
      foreach ($connections as $connection) {
        // @todo check if authorized? Check access_token?
        $active_connections[$connection['id']] = $connection['name'];

        if (!empty($connection['lists'])) {
          foreach ($connection['lists'] as $list) {
            $id = $connection['id'] . ':' . $list['list_id'];

            $contact_lists[$connection['name']][$id] = $this->t('@name (@membership_count subscribers)', [
              '@name' => $list['name'],
              '@membership_count' => $list['membership_count'],
            ]);
          }
        }
      }
    }

    if (!empty($active_connections)) {
      $form['constant_contact']['subscription'] = [
        '#title' => $this->t('Subscription'),
        '#type' => 'radios',
        '#default_value' => $this->configuration['subscription'],
        '#options' => [
          'dynamic' => $this->t('Subscribe users to dynamically created contact list from a single entity.'),
          'select' => $this->t('Subscribe users to the selected contact lists.'),
        ],
        '#attributes' => [
          'class' => [
            'subscription-radios',
          ],
        ],
        '#attached' => [
          'library' => [
            'constant_contact_mailout/constant_contact_mailout.subscribe_block',
          ],
        ],
      ];

      $form['constant_contact']['subscription_dynamic'] = [
        '#type' => 'details',
        '#title' => $this->t('Dynamic Contact List Creation'),
        '#open' => TRUE,
        '#attributes' => [
          'class' => [
            'subscription-dynamic-details',
          ],
        ],
      ];

      $form['constant_contact']['subscription_dynamic']['connection_id'] = [
        '#title' => 'Connection',
        '#type' => 'select',
        '#options' => $active_connections,
        '#default_value' => $this->configuration['connection_id'],
      ];

      $form['constant_contact']['subscription_dynamic']['contact_list_prefix'] = [
        '#title' => 'Contact List Prefix',
        '#type' => 'textfield',
        '#description' => $this->t('Add a prefix to contact list name for organization. Leave blank for no prefix.'),
        '#default_value' => $this->configuration['contact_list_prefix'],
      ];

      $form['constant_contact']['subscription_select'] = [
        '#type' => 'details',
        '#title' => $this->t('Selected Contact Lists'),
        '#description' => $this->t('Check contact lists to be displayed on the subscription form.'),
        '#open' => TRUE,
        '#attributes' => [
          'class' => [
            'subscription-select-details',
          ],
        ],
      ];

      if (!empty($contact_lists)) {
        foreach ($contact_lists as $name => $lists) {
          // Sort by value.
          uasort($lists, function ($a, $b) {
            return strcmp($a->__toString(), $b->__toString());
          });

          $form['constant_contact']['subscription_select']['contact_list_ids'][$id] = [
            '#title' => $name,
            '#type' => 'checkboxes',
            '#multiple' => TRUE,
            '#options' => $lists,
            '#default_value' => $this->configuration['contact_list_ids'],
          ];
        }

        $form['constant_contact']['subscription_select']['show_contact_lists'] = [
          '#title' => $this->t('Show contact lists'),
          '#type' => 'checkbox',
          '#default_value' => $this->configuration['show_contact_lists'],
          '#attributes' => [
            'class' => [
              'show-contact-lists',
            ],
          ],
        ];

        $form['constant_contact']['subscription_select']['show_select_all'] = [
          '#title' => $this->t('Show select all'),
          '#type' => 'checkbox',
          '#default_value' => $this->configuration['show_select_all'],
        ];

        $form['constant_contact']['subscription_select']['group_contact_lists_label'] = [
          '#title' => $this->t('List Label'),
          '#description' => $this->t('If grouping contact lists is enabled, this list label will be used if the delimeter is not found.'),
          '#type' => 'textfield',
          '#default_value' => $this->configuration['group_contact_lists_label'],
        ];

        $form['constant_contact']['subscription_select']['group_contact_lists'] = [
          '#title' => $this->t('Group contact lists by delimiter'),
          '#type' => 'checkbox',
          '#default_value' => $this->configuration['group_contact_lists'],
        ];

        $form['constant_contact']['subscription_select']['group_contact_lists_delimiter'] = [
          '#title' => $this->t('Delimeter'),
          '#type' => 'textfield',
          '#default_value' => $this->configuration['group_contact_lists_delimiter'],
        ];

        $form['constant_contact']['subscription_select']['group_contact_lists_order'] = [
          '#title' => $this->t('Group Order'),
          '#type' => 'textarea',
          '#default_value' => $this->configuration['group_contact_lists_order'],
          '#description' => $this->t('Enter the custom group order name.'),
        ];
      }
      else {
        $form['constant_contact']['subscription_select']['contact_list_ids'] = [
          '#markup' => $this->t('No contact lists.'),
        ];
      }

      $form['constant_contact']['subscribed_message'] = [
        '#type' => 'processed_text',
        '#title' => $this->t('Subscribed Message'),
        '#default_value' => $this->configuration['subscribed_message'],
      ];

      $form['constant_contact']['unsubscribed_message'] = [
        '#type' => 'processed_text',
        '#title' => $this->t('Unsubscribe'),
        '#default_value' => $this->configuration['unsubscribed_message'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);

    $values = $form_state->getValues();
    $constant_contact_values = $values['constant_contact'];

    $this->configuration['fields'] = array_filter($constant_contact_values['fields']);
    $this->configuration['description'] = $constant_contact_values['description']['value'];
    $this->configuration['subscription'] = $constant_contact_values['subscription'];

    if ($constant_contact_values['subscription'] == 'dynamic') {
      $this->configuration['connection_id'] = $constant_contact_values['connection_id'];
      $this->configuration['contact_list_prefix'] = $constant_contact_values['contact_list_prefix'];
    }
    elseif ($constant_contact_values['subscription'] == 'select') {
      // Filter contact_list_ids.
      $contact_list_ids = [];

      if (!empty($constant_contact_values['subscription_select']['contact_list_ids'])) {
        foreach ($constant_contact_values['subscription_select']['contact_list_ids'] as $lists) {
          $contact_list_ids = array_merge($contact_list_ids, array_filter($lists));
        }
      }

      $this->configuration['contact_list_ids'] = $contact_list_ids;
      $this->configuration['show_contact_lists'] = $constant_contact_values['subscription_select']['show_contact_lists'];
      $this->configuration['show_select_all'] = $constant_contact_values['subscription_select']['show_select_all'];
      $this->configuration['group_contact_lists_label'] = $constant_contact_values['subscription_select']['group_contact_lists_label'];
      $this->configuration['group_contact_lists'] = $constant_contact_values['subscription_select']['group_contact_lists'];
      $this->configuration['group_contact_lists_delimiter'] = $constant_contact_values['subscription_select']['group_contact_lists_delimiter'];
      $this->configuration['group_contact_lists_order'] = $constant_contact_values['subscription_select']['group_contact_lists_order'];
    }

    $this->configuration['subscribed_message'] = $constant_contact_values['subscribed_message']['value'];
    $this->configuration['unsubscribed_message'] = $constant_contact_values['unsubscribed_message']['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $configuration = $this->configuration;

    // Get block settings, include in configuration.
    $blocks = Block::loadMultiple();
    foreach ($blocks as $key => $block) {
      $settings = $block->get('settings');

      if ($settings['label'] == $this->configuration['label']) {
        $configuration['settings'] = $settings;
        $configuration['settings']['machine_name'] = $key;
      }
    }

    $build = [
      '#theme' => 'subscribe_block',
      '#subscribe_block_form' => $this->formBuilder->getForm(SubscribeBlockForm::class, $configuration),
      '#data' => [
        'description' => $this->configuration['description'],
      ],
    ];

    return $build;
  }

}
