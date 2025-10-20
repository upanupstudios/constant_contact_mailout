<?php

namespace Drupal\constant_contact_mailout\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Configure Constant Contact Mailout settings.
 */
class AdminSettingsForm extends ConfigFormBase {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Messenger service.
   *
   * @var Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The config instance.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * Constructs a new AdminSettingsForm object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request stack object.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Logger\LoggerChannel $logger
   *   The logger channel factory.
   */
  public function __construct(Request $request, MessengerInterface $messenger, LoggerChannel $logger, ConfigFactoryInterface $config_factory) {
    $this->request = $request;
    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->configFactory = $config_factory;

    // Get settings.
    $this->settings = $this->configFactory->get('constant_contact_mailout.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('messenger'),
      $container->get('logger.factory')->get('constant_contact_mailout'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'constant_contact_mailout_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['constant_contact_mailout.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $connections = $this->settings->get('connections');
    $debug_render_template = $this->settings->get('debug_render_template');
    $debug_sendto_contact_list = $this->settings->get('debug_sendto_contact_list');
    $default_base_url = $this->settings->get('default_base_url');

    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#open' => (!empty($default_base_url)),
    ];

    $form['settings']['default_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default base URL'),
      '#default_value' => !empty($default_base_url) ? $default_base_url : NULL,
    ];

    $form['debug'] = [
      '#type' => 'details',
      '#title' => $this->t('Debug'),
      '#open' => (!empty($debug_render_template) || !empty($debug_sendto_contact_list)),
    ];
    $form['debug']['debug_render_template'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render email template on screen when publishing a content node'),
      '#default_value' => $debug_render_template,
    ];

    $options[0] = $this->t('- Use field settings -');

    if (!empty($connections)) {
      foreach ($connections as $connection_id => $connection) {
        if (!empty($connection['lists'])) {
          foreach ($connection['lists'] as $list) {
            $id = $connection['id'] . ':' . $list['list_id'];
            $options[$connection['name']][$id] = $list['name'];
          }

          asort($options[$connection['name']]);
        }
      }

      ksort($options);
    }

    $form['debug']['debug_sendto_contact_list'] = [
      '#type' => 'select',
      '#title' => $this->t('Send to contact list'),
      '#description' => $this->t('Send all mailings to the selected contact list for testing.'),
      '#options' => $options,
      '#default_value' => $debug_sendto_contact_list,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save settings.
    $this->config('constant_contact_mailout.settings')
      ->set('debug_render_template', $form_state->getValue('debug_render_template'))
      ->set('debug_sendto_contact_list', $form_state->getValue('debug_sendto_contact_list'))
      ->set('default_base_url', $form_state->getValue('default_base_url'))
      ->save();

    return parent::submitForm($form, $form_state);
  }

}
