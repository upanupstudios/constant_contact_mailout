<?php

namespace Drupal\constant_contact_mailout\Form;

use Drupal\block\Entity\Block;
use Drupal\constant_contact_mailout\ApiService;
use Drupal\constant_contact_mailout\Utility\TextHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannel;
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
   * @var Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Config service.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(ApiService $api, ConfigFactoryInterface $config_factory, LoggerChannel $logger) {
    $this->api = $api;
    $this->configFactory = $config_factory;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('constant_contact_mailout.api'),
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
  public function buildForm(array $form, FormStateInterface $form_state, $machine_name = NULL, $configuration = NULL) {
    $form['machine_name'] = [
      '#type' => 'hidden',
      '#value' => $machine_name,
    ];

    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['first-name'],
        'novalidate' => 'novalidate',
      ],
    ];

    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['last-name'],
        'novalidate' => 'novalidate',
      ],
    ];

    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['email'],
        'novalidate' => 'novalidate',
      ],
    ];

    $form['confirm_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirm Email'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['confirm-email'],
        'novalidate' => 'novalidate',
      ],
    ];

    $connections = $this->configFactory->get('constant_contact_mailout.settings')->get('connections');
    $contact_lists = [];
    $label = !empty($configuration['group_contact_lists_label']) ? $configuration['group_contact_lists_label'] : $this->t('General');

    if (!empty($configuration['subscription']) && $configuration['subscription'] == 'select') {
      foreach ($connections as $connection) {
        if (!empty($connection['lists'])) {
          foreach ($connection['lists'] as $list) {
            $id = $connection['id'] . ':' . $list['list_id'];

            if (in_array($id, $configuration['contact_list_ids'])) {
              if (!empty($configuration['group_contact_lists']) && !empty($configuration['group_contact_lists_delimiter']) && strpos($list['name'], $configuration['group_contact_lists_delimiter']) !== FALSE) {
                [$group, $name] = explode($configuration['group_contact_lists_delimiter'], $list['name']);

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
        // Sort group.
        // @todo Custom order groups?
        ksort($contact_lists);

        foreach ($contact_lists as $group => $list) {
          // Sort lists.
          // @todo Move other to bottom of list?
          asort($list);

          $id = TextHelper::textToMachineName($group);

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

    $form['#attached']['library'][] = 'constant_contact_mailout/constant_contact_mailout_subscribe_block_form';

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#id' => 'subscribesubmit',
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
        '#attributes' => [
          'class' => ['subscribe-submit'],
        ],
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
  public function validateFormCallback(FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $contact_list_ids = [];

    foreach ($values as $key => $value) {
      if (preg_match('/^contact_list_ids-/', $key)) {
        $contact_list_ids = array_merge($contact_list_ids, array_filter($value));
      }
    }

    $first_name_valid = !empty($values['first_name']);
    $last_name_valid = !empty($values['last_name']);
    $email_valid = !empty($values['email']) && !empty($values['confirm_email']) && $values['email'] === $values['confirm_email'];
    $contact_list_ids_valid = !empty($contact_list_ids);

    return $first_name_valid && $last_name_valid && $email_valid && $contact_list_ids_valid;
  }

  /**
   * {@inheritdoc}
   */
  public function submitFormCallback(array &$form, FormStateInterface $form_state) {
    $ajaxResponse = new AjaxResponse();

    // Retrieve block settings for default success message.
    $machine_name = $form_state->getValue('machine_name');
    $block = Block::load($machine_name);
    $success_message = NULL;
    $messages = [];

    if (!empty($block)) {
      $settings = $block->get('settings');

      if (!empty($settings['success_message'])) {
        $success_message = $settings['success_message'];
      }
    }

    // Validation.
    $is_valid = $this->validateFormCallback($form_state);

    if ($is_valid) {
      $connections = $this->configFactory->get('constant_contact_mailout.settings')->get('connections');
      $values = $form_state->getValues();
      $contact_list_ids = [];
      $connection_contact_lists = [];

      foreach ($values as $key => $value) {
        if (preg_match('/^contact_list_ids-/', $key)) {
          $contact_list_ids = array_merge($contact_list_ids, array_filter($value));
        }
      }

      if (!empty($contact_list_ids)) {
        foreach ($contact_list_ids as $contact_list_id) {
          [$connection_id, $contact_list_id] = explode(':', $contact_list_id);
          $connection_contact_lists[$connection_id][] = $contact_list_id;
        }
      }

      if (!empty($connection_contact_lists)) {
        foreach ($connection_contact_lists as $connection_id => $contact_list_ids) {
          $connection = $connections[$connection_id];

          if (!empty($connection) && !empty($contact_list_ids)) {
            $messages[] = $this->api->subscribe($connection, $contact_list_ids, $values);

            // @todo How to handle error?
          }
        }

        // Make messages unique.
        $messages = array_filter($messages);

        // @todo Once someone subscribe, the lists in connections should be refreshed.
        if (!empty($messages)) {
          if (empty($success_message)) {
            $success_message = implode('. ', $messages) . '.';
          }
        }
        else {
          // @todo type of message?
          $success_message = $this->t('Unable to subscribe.');
        }

        $ajaxResponse->addCommand(
          new InvokeCommand(NULL, 'submitSubscribeBlockFormCallback', [$success_message])
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
