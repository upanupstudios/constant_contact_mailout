<?php

namespace Drupal\constant_contact_mailout\Form;

use Drupal\constant_contact_mailout\ApiService;
use Drupal\constant_contact_mailout\Utility\TextHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The AdminConnectionForm class.
 */
class AdminConnectionForm extends ConfigFormBase {

  /**
   * API service.
   *
   * @var \Drupal\constant_contact_mailout\ApiService
   */
  protected $api;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * The parent route name.
   *
   * @var string
   */
  protected $parentRouteName;

  /**
   * Constructs a AdminDropinsActivityForm object.
   *
   * @param \Drupal\constant_contact_mailout\ApiService $api
   *   The api service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match.
   */
  public function __construct(ApiService $api, MessengerInterface $messenger, RouteMatchInterface $current_route_match) {
    $this->api = $api;
    $this->messenger = $messenger;
    $this->currentRouteMatch = $current_route_match;

    // Get route name.
    $routeName = $current_route_match->getRouteName();

    // Get the parent route name.
    $this->parentRouteName = implode('.', array_slice(explode('.', $routeName), 0, -1));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('constant_contact_mailout.api'),
      $container->get('messenger'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'constant_contact_mailout_admin_connection_form';
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
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $disabled = FALSE) {
    $connections = $this->config('constant_contact_mailout.settings')->get('connections') ?: [];
    $connection = NULL;

    if (!empty($id)) {
      if (array_key_exists($id, $connections)) {
        $connection = $connections[$id];

        $form['id'] = [
          '#type' => 'hidden',
          '#value' => $id,
        ];
      }
      else {
        $this->messenger->addError($this->t('Unable to edit. Invalid connection.'));
        $url = Url::fromRoute($this->parentRouteName)->toString();
        return new RedirectResponse($url);
      }
    }

    $contant_contact_url = Url::fromUri('https://app.constantcontact.com/pages/dashboard/home', ['attributes' => ['target' => '_blank']]);

    $this->messenger->addWarning($this->t('After authorizing or re-authorizing, logout of @constant_contact to authorize or re-authorize another connection.', [
      '@constant_contact' => Link::fromTextAndUrl($this->t('Constant Contact'), $contant_contact_url)->toString(),
    ]));

    // @todo: Add note to add the following redirect_uri: /admin/config/services/constant_contact_mailout/connections/authorize

    $app_integration_url = Url::fromUri('https://developer.constantcontact.com/api_guide/apps_create.html', ['attributes' => ['target' => '_blank']]);

    $form['api'] = [
      '#type' => 'details',
      '#title' => $this->t('Application API'),
      '#open' => TRUE,
      '#description' => $this->t('Enter the application api information to be used when sending mailings'),
    ];
    $form['api']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => !empty($connection['name']) ? $connection['name'] : NULL,
      '#required' => TRUE,
    ];

    $form['api']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => !empty($connection['api_key']) ? $connection['api_key'] : NULL,
      '#description' => $this->t('The API Key (Client ID) created for this application. Follow how to @app_integration_url to retrieve the API Key.', [
        '@app_integration_url' => Link::fromTextAndUrl($this->t('create an Application Integration'), $app_integration_url)->toString(),
      ]),
      '#required' => TRUE,
    ];

    $form['api']['secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret'),
      '#description' => $this->t('@secret. Follow how to @app_integration_url to retrieve the Secret.', [
        '@secret' => !empty($id) ? $this->t('Re-enter secret to save & re-authorize') : $this->t('The Secret generated for this application'),
        '@app_integration_url' => Link::fromTextAndUrl(t('create an Application Integration'), $app_integration_url)->toString(),
      ]),
      '#required' => TRUE,
    ];

    $account_emails_url = Url::fromUri('https://app.constantcontact.com/pages/myaccount/settings/emails', ['attributes' => ['target' => '_blank']]);

    $form['sender'] = [
      '#type' => 'details',
      '#title' => $this->t('Sender Information'),
      '#open' => TRUE,
      '#description' => $this->t('Enter the sender information to be used when sending mailings'),
    ];
    $form['sender']['sender_from_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From Name'),
      '#default_value' => !empty($connection['sender_from_name']) ? $connection['sender_from_name'] : NULL,
      '#required' => TRUE,
    ];
    $form['sender']['sender_from_email'] = [
      '#type' => 'email',
      '#title' => $this->t('From Email'),
      '#default_value' => !empty($connection['sender_from_email']) ? $connection['sender_from_email'] : NULL,
      '#description' => $this->t('Make sure that the email is valid and is in the list of verified @account_emails_link in Constant Contact.', [
        '@account_emails_link' => Link::fromTextAndUrl($this->t('Account Emails'), $account_emails_url)->toString(),
      ]),
      '#required' => TRUE,
    ];
    $form['sender']['sender_replyto_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Reply-to Email'),
      '#default_value' => !empty($connection['sender_replyto_email']) ? $connection['sender_replyto_email'] : NULL,
      '#description' => $this->t('Leave empty to use from email'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => !empty($id) ? $this->t('Save & Re-Authorize') : $this->t('Save & Authorize'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $connections = $this->config('constant_contact_mailout.settings')->get('connections') ?: [];

    if (!empty($connections)) {
      $id = $form_state->getValue('id');

      if (!empty($id) && !empty($connections[$id])) {
        // Remove from list to be checked.
        unset($connections[$id]);
      }

      $name = $form_state->getValue('name');
      $id = TextHelper::textToMachineName($name);

      // Check if there if the id exists.
      if (array_key_exists($id, $connections)) {
        $form_state->setErrorByName('name', $this->t('Connection name already exists.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $connections = $this->config('constant_contact_mailout.settings')->get('connections') ?: [];

    $id = $form_state->getValue('id');
    $name = trim($form_state->getValue('name'));
    $api_key = trim($form_state->getValue('api_key'));
    $secret = trim($form_state->getValue('secret'));
    $sender_from_name = trim($form_state->getValue('sender_from_name'));
    $sender_from_email = trim($form_state->getValue('sender_from_email'));
    $sender_replyto_email = trim($form_state->getValue('sender_replyto_email'));

    if (!empty($id)) {
      // Remove the edited connection.
      unset($connections[$id]);
    }

    $id = TextHelper::textToMachineName($name);

    // Whether adding or editing, use the $id to create/overwrite the values.
    $connection = [
      'id' => $id,
      'name' => $name,
      'api_key' => $api_key,
      'secret' => $secret,
      'sender_from_name' => $sender_from_name,
      'sender_from_email' => $sender_from_email,
      'sender_replyto_email' => $sender_replyto_email,
    ];

    $connections[$id] = $connection;

    // Sort connections by id.
    ksort($connections);

    // Save connections.
    $config = $this->config('constant_contact_mailout.settings');
    $config->set('connections', $connections)->save();

    // Redirect url.
    $redirect_url = Url::fromRoute('constant_contact_mailout.connections.authorize', [], ['absolute' => TRUE]);

    // Pre-defined scope required by this module.
    $scope = 'contact_data+campaign_data';

    // The offline_access scope is required for returning refresh tokens.
    $scope .= '+offline_access';

    // Add state with connection ID.
    $state = $id;

    $authorization_url = $this->api->getAuthorizationUrl($api_key, $redirect_url->toString(), $scope, $state);
    $response = new TrustedRedirectResponse(Url::fromUri($authorization_url)->toString());
    $form_state->setResponse($response);
  }

}
