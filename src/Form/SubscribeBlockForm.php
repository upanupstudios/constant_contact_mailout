<?php

namespace Drupal\constant_contact_mailout\Form;

use Drupal\block\Entity\Block;
use Drupal\constant_contact_mailout\ApiService;
use Drupal\constant_contact_mailout\Utility\TextHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the subscribe form for the subscribe block.
 */
class SubscribeBlockForm extends FormBase {

  /**
   * API service.
   *
   * @var \Drupal\constant_contact_mailout\ApiService
   */
  protected $api;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

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
   * {@inheritdoc}
   */
  public function __construct(ApiService $api, EntityFieldManager $entity_field_manager, ConfigFactoryInterface $config_factory, LoggerChannelInterface $logger) {
    $this->api = $api;
    $this->entityFieldManager = $entity_field_manager;
    $this->configFactory = $config_factory;
    $this->logger = $logger;

    // Get settings.
    $this->settings = $this->configFactory->get('constant_contact_mailout.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('constant_contact_mailout.api'),
      $container->get('entity_field.manager'),
      $container->get('config.factory'),
      $container->get('logger.factory')->get('constant_contact_mailout'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'constant_contact_mailout_subscribe_block_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $configuration = NULL) {
    $settings = $configuration['settings'];

    $form['machine_name'] = [
      '#type' => 'hidden',
      '#value' => $settings['machine_name'],
    ];

    $form['subscription'] = [
      '#type' => 'hidden',
      '#value' => $settings['subscription'],
    ];

    $fields = [
      $this->t('First Name'),
      $this->t('Last Name'),
      $this->t('Email'),
      $this->t('Confirm Email'),
    ];

    foreach ($fields as $field) {
      $name = TextHelper::textToMachineName($field);
      $class = TextHelper::textToMachineName($field, '-');

      if (!empty($settings['fields']) && in_array($name, $settings['fields'])) {
        $form[$name] = [
          '#type' => 'textfield',
          '#title' => $field,
          '#required' => TRUE,
          '#attributes' => [
            'class' => [$class],
            'novalidate' => 'novalidate',
          ],
        ];
      }
    }

    if (!empty($settings['show_contact_lists'])) {
      $connections = $this->settings->get('connections');
      $contact_lists = [];
      $label = !empty($settings['group_contact_lists_label']) ? $settings['group_contact_lists_label'] : $this->t('General');

      if (!empty($settings['subscription']) && $settings['subscription'] == 'select') {
        foreach ($connections as $connection) {
          if (!empty($connection['lists'])) {
            foreach ($connection['lists'] as $list) {
              $id = $connection['id'] . ':' . $list['list_id'];

              if (in_array($id, $settings['contact_list_ids'])) {
                if (!empty($settings['group_contact_lists']) && !empty($settings['group_contact_lists_delimiter']) && strpos($list['name'], $settings['group_contact_lists_delimiter']) !== FALSE) {
                  [$group, $name] = explode($settings['group_contact_lists_delimiter'], $list['name']);

                  $contact_lists[trim($group)][$id] = trim($name);
                }
                else {
                  $contact_lists[$label][$id] = $list['name'];
                }
              }
            }
          }
        }

        if (!empty($contact_lists)) {
          // Add select all before the contact lists groups if more than 1.
          if (count($contact_lists) > 1) {
            // Show if enabled.
            if (!empty($settings['show_select_all'])) {
              $form['contact_list_ids-all'] = [
                '#type' => 'checkboxes',
                '#options' => ['all' => $this->t('Select all')],
                '#attributes' => [
                  'class' => ['contact-list-ids-all'],
                  'novalidate' => 'novalidate',
                ],
              ];
            }
          }

          // Sort group.
          ksort($contact_lists);

          if (!empty($settings['group_contact_lists_order'])) {
            $_contact_lists = [];
            $groups = explode("\r\n", $settings['group_contact_lists_order']);

            foreach ($groups as $group) {
              if (array_key_exists($group, $contact_lists)) {
                $_contact_lists[$group] = $contact_lists[$group];
              }
            }

            $contact_lists = $_contact_lists;
          }

          foreach ($contact_lists as $group => $list) {
            // @todo Move "other" to bottom of list?
            // Sort lists.
            asort($list);

            $id = TextHelper::textToMachineName($group);

            // Add select all in the group if there is only 1.
            if (count($contact_lists) == 1) {
              $list = array_merge(['all' => $this->t('Select all')], $list);
            }

            $form['contact_list_ids-' . $id] = [
              '#type' => 'checkboxes',
              '#title' => $group,
              '#options' => $list,
              '#attributes' => [
                'class' => ['contact-list-ids'],
                'novalidate' => 'novalidate',
              ],
            ];
          }
        }
        else {
          // @todo List cannot be empty
        }
      }
    }

    $form['#attached']['library'][] = 'constant_contact_mailout/constant_contact_mailout.subscribe_block_form';

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#id' => 'subscribesubmit',
        '#type' => 'submit',
        '#value' => $this->t('Subscribe'),
        '#attributes' => [
          'class' => ['subscribe-submit'],
        ],
        '#validate' => [],
        '#ajax' => [
          'callback' => [$this, 'submitFormCallback'],
          'event' => 'click',
        ],
      ],
    ];

    // Remove progress message.
    $form['actions']['submit']['#ajax']['progress']['message'] = '';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Uses Ajax to submit data using submitFormCallback function.
  }

  /**
   * {@inheritdoc}
   */
  public function validateFormCallback(FormStateInterface $form_state, array $settings = []) {
    $valid = TRUE;
    $values = $form_state->getValues();

    // Contact list checkboxes are only rendered for the select subscription.
    $show_contact_lists = !empty($settings['show_contact_lists']) && $values['subscription'] == 'select';
    $contact_list_ids = [];

    if (!empty($show_contact_lists)) {
      foreach ($values as $key => $value) {
        if (preg_match('/^contact_list_ids-/', $key)) {
          $contact_list_ids = array_merge($contact_list_ids, array_filter($value));
        }
      }
    }

    $fields = [
      'first_name',
      'last_name',
      'email',
      'confirm_email',
    ];

    foreach ($fields as $field) {
      if ($valid && array_key_exists($field, $values)) {
        if ($field == 'email') {
          if ($valid = !empty($values[$field])) {
            $emailPattern = "/^[^\s@]+@[^\s@]+\.[^\s@]+$/";
            $valid = preg_match($emailPattern, $values[$field]) === 1;
          }
        }
        else {
          $valid = !empty($values[$field]);
        }
      }
    }

    if ($valid && array_key_exists('confirm_email', $values)) {
      $valid = !empty($values['confirm_email']);

      if ($valid) {
        $valid = !empty($values['email']) && !empty($values['confirm_email']) && $values['email'] === $values['confirm_email'];
      }
    }

    if ($valid) {
      $valid = empty($show_contact_lists) || (!empty($show_contact_lists) && !empty($contact_list_ids));
    }

    return $valid;
  }

  /**
   * {@inheritdoc}
   */
  public function submitFormCallback(array &$form, FormStateInterface $form_state) {
    $ajaxResponse = new AjaxResponse();

    // Retrieve block settings.
    $machine_name = $form_state->getValue('machine_name');
    $block = Block::load($machine_name);
    $settings = NULL;
    $subscribed_message = NULL;

    if (!empty($block)) {
      $settings = $block->get('settings');

      if (!empty($settings['subscribed_message'])) {
        $subscribed_message = $settings['subscribed_message'];
      }

      // Clear all messages.
      // @todo use dependencys injection.
      \Drupal::messenger()->deleteAll();

      // Validation.
      $is_valid = $this->validateFormCallback($form_state, $settings);

      if ($is_valid) {
        $connections = $this->settings->get('connections');
        $values = $form_state->getValues();
        $show_contact_lists = !empty($settings['show_contact_lists']);
        $contact_list_ids = [];
        $connection_contact_lists = [];

        if ($values['subscription'] == 'dynamic') {
          $entity = \Drupal::routeMatch()->getParameter('node');

          // Check if field settings has a contact list id already.
          $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', $entity->gettype());

          foreach ($field_definitions as $field_name => $field_definition) {
            if (!empty($field_definition->getTargetBundle())) {
              if ($field_definition->getType() == 'constant_contact_mailout') {
                if ($field_definition->getSetting('contact_list_creation') == $values['subscription']) {
                  $value = $entity->get($field_name)->getValue();
                  $contact_list_id = $value[0]['contact_list_id'];

                  if (!empty($contact_list_id)) {
                    $contact_list_ids = [$contact_list_id];
                  }

                  break;
                }
              }
            }
          }

          // Create list if no contact list id is stored.
          if (empty($contact_list_ids)) {
            $contact_list_prefix = $field_definition->getSetting('contact_list_prefix');

            // Need to create using the node's name.
            $title = $entity->getTitle();

            if (!empty($contact_list_prefix)) {
              $title = $contact_list_prefix . $title;
            }

            if (!empty($title)) {
              // Get connection.
              $connection = current($connections);

              // Find contact list by title.
              $contact_list = $this->api->findByNameContactLists($connection, $title);

              // There is no contact list found.
              if (empty($contact_list)) {
                // Create the contact list using the title.
                $contact_list_data = [
                  'name' => $title,
                ];

                $contact_list_response = $this->api->createContactList($connection, $contact_list_data);

                if (!empty($contact_list_response) && !empty($contact_list_response['list_id'])) {
                  $contact_list_id = $connection['id'] . ':' . $contact_list_response['list_id'];

                  // Save the contact list id to node.
                  $entity->set($field_name, ['contact_list_id' => $contact_list_id])->save();

                  $contact_list_ids = [$contact_list_id];

                  $message = $this->t('The contact list @name has been created.', [
                    '@name' => $contact_list_response['name'],
                  ]);

                  $this->logger->notice($message);
                }
                else {
                  $errors = $this->api->processErrorResponse($contact_list_response);

                  $message = $this->t('Constant Contact: @errors', [
                    '@errors' => implode('. ', $errors),
                  ]);

                  $this->logger->error($message);
                }
              }
              else {
                $contact_list_id = $connection['id'] . ':' . $contact_list['list_id'];

                // Save the contact list id to node.
                $entity->set($field_name, ['contact_list_id' => $contact_list_id])->save();

                $contact_list_ids = [$contact_list_id];
              }
            }
          }
        }
        else {
          if (!empty($show_contact_lists)) {
            foreach ($values as $key => $value) {
              if (preg_match('/^contact_list_ids-/', $key)) {
                $contact_list_ids = array_merge($contact_list_ids, array_filter($value));
              }
            }
          }
          else {
            // Get list from configuration.
            // Should this be split?
            $contact_list_ids = $settings['contact_list_ids'];
          }
        }

        if (!empty($contact_list_ids)) {
          foreach ($contact_list_ids as $contact_list_id) {
            [$connection_id, $contact_list_id] = explode(':', $contact_list_id);
            $connection_contact_lists[$connection_id][] = $contact_list_id;
          }
        }

        if (!empty($connection_contact_lists)) {
          $results = [];

          foreach ($connection_contact_lists as $connection_id => $contact_list_ids) {
            $connection = $connections[$connection_id];

            if (!empty($connection) && !empty($contact_list_ids)) {
              $results[] = $this->api->subscribe($connection, $contact_list_ids, $values);
            }
          }

          // Subscribing is a single action to the visitor even when it spans
          // several connections, so report one outcome for the whole
          // submission rather than one message per connection. The failures
          // are logged per connection by the API service.
          $subscribed_lists = [];
          $failed_lists = [];

          foreach (array_filter($results) as $result) {
            if (!empty($result['success'])) {
              $subscribed_lists = array_merge($subscribed_lists, $result['contact_list_names']);
            }
            else {
              $failed_lists = array_merge($failed_lists, $result['contact_list_names']);
            }
          }

          $subscribed_lists = array_unique($subscribed_lists);
          $failed_lists = array_unique($failed_lists);

          if (empty($subscribed_message) && !empty($results)) {
            // The API error is not repeated to the visitor. It describes the
            // account rather than anything they can act on, and the most
            // common one, a temporary block after repeated signups, reads as
            // "Email address is invalid". The API service logs the detail.
            $arguments = [
              '@email_address' => $values['email'],
              '@subscribed_lists' => implode(', ', $subscribed_lists),
              '@failed_lists' => implode(', ', $failed_lists),
            ];

            if (empty($failed_lists)) {
              $subscribed_message = $this->t('The @email_address email is now subscribed.', $arguments);
            }
            elseif (empty($subscribed_lists)) {
              // Nothing succeeded, so naming the lists adds nothing.
              $subscribed_message = $this->t('Could not subscribe @email_address. Please try again later.', $arguments);
            }
            else {
              $subscribed_message = $this->t('The @email_address email was only subscribed to @subscribed_lists. Could not subscribe to @failed_lists. Please try again later.', $arguments);
            }

            $subscribed_message = trim((string) $subscribed_message);
          }

          $ajaxResponse->addCommand(
            new InvokeCommand(NULL, 'submitSubscribeBlockFormCallback', [$subscribed_message])
          );
        }
      }
      else {
        $ajaxResponse->addCommand(
          new InvokeCommand(NULL, 'validateSubscribeBlockFormCallback')
        );
      }

      return $ajaxResponse;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateEmail($email) {
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return $email;
    }

    return FALSE;
  }

}
