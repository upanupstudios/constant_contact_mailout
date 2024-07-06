<?php

namespace Drupal\constant_contact_mailout\Form;

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
   * Constructs a new AdminSettingsForm object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request stack object.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Logger\LoggerChannel $logger
   *   The logger channel factory.
   */
  public function __construct(Request $request, MessengerInterface $messenger, LoggerChannel $logger) {
    $this->request = $request;
    $this->messenger = $messenger;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('messenger'),
      $container->get('logger.factory')->get('constant_contact_mailout')
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
    $settings = $this->config('constant_contact_mailout.settings');

    $connections = $settings->get('connections');
    $debug_render_template = $settings->get('debug_render_template');
    $debug_sendto_contact_list = $settings->get('debug_sendto_contact_list');

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
    $settings = $this->config('constant_contact_mailout.settings');

    $debug_render_template = $form_state->getValue('debug_render_template');

    $settings->set('debug_render_template', $debug_render_template)
      ->save();

    $debug_sendto_contact_list = $form_state->getValue('debug_sendto_contact_list');

    $settings->set('debug_sendto_contact_list', $debug_sendto_contact_list)
      ->save();

    $this->messenger->addStatus($this->t('The settings has been saved.'));

    return parent::submitForm($form, $form_state);
  }

}
