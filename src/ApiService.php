<?php

namespace Drupal\constant_contact_mailout;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Upanupstudios\ConstantContact\Php\Client\Config;
use Upanupstudios\ConstantContact\Php\Client\ConstantContact;

/**
 * Defines a service provider for Constant Contact.
 */
class ApiService implements ContainerInjectionInterface {

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger channel instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * The config instance.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * Constructs a new ApiService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory interface.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory interface.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('constant_contact_mailout');

    // Get settings.
    $this->settings = $this->configFactory->get('constant_contact_mailout.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationUrl($api_key, $redirect_url, $scope, $state) {
    $httpClient = new Client();
    $constantContact = new ConstantContact($httpClient);

    return $constantContact->getAuthorizationUrl($api_key, $redirect_url, $scope, $state);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken($redirect_uri, $api_key, $secret, $code) {
    $httpClient = new Client();
    $constantContact = new ConstantContact($httpClient);

    return $constantContact->getAccessToken($redirect_uri, $api_key, $secret, $code);
  }

  /**
   * {@inheritdoc}
   */
  public function getRefreshToken($refreshToken, $api_key, $secret) {
    $httpClient = new Client();
    $constantContact = new ConstantContact($httpClient);

    return $constantContact->getRefreshToken($refreshToken, $api_key, $secret);
  }

  /**
   * {@inheritdoc}
   */
  public function refreshAccessToken($connection) {
    if (time() >= abs($connection['expires'])) {
      // Get connections.
      $connections = $this->settings->get('connections');

      // Refresh token.
      $response = $this->getRefreshToken($connection['refresh_token'], $connection['api_key'], $connection['secret']);

      $connection['access_token'] = $response['access_token'];
      $connection['refresh_token'] = $response['refresh_token'];
      $connection['expires'] = time() + abs($response['expires_in']);

      // Get all contact lists.
      // @todo There's a 1000 retrieve list limit, which we likely won't go over?
      $contact_lists = $this->getAllContactLists($connection, [
        'include_count' => 'true',
        'status' => 'active',
        'include_membership_count' => 'all',
      ]);

      if (!empty($contact_lists['lists'])) {
        $connection['lists'] = $contact_lists['lists'];
      }

      $connections[$connection['id']] = $connection;

      // Save connections.
      $settings = $this->configFactory->getEditable('constant_contact_mailout.settings');
      $settings->set('connections', $connections)
        ->save();
    }

    return $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllContactLists($connection, $params = []) {
    // Refresh access token.
    $connection = $this->refreshAccessToken($connection);

    $config = new Config($connection['access_token']);
    $httpClient = new Client();
    $constantContact = new ConstantContact($httpClient, $config);

    return $constantContact->contactLists()->getAll($params);
  }

  /**
   * {@inheritdoc}
   */
  public function findByNameContactLists($connection, $title) {
    // Refresh access token.
    $connection = $this->refreshAccessToken($connection);

    $config = new Config($connection['access_token']);
    $httpClient = new Client();
    $constantContact = new ConstantContact($httpClient, $config);

    return $constantContact->contactLists()->findByName($title);
  }

  /**
   * {@inheritdoc}
   */
  public function addToContactLists($connection, $contact_list_data) {
    // Refresh access token.
    $connection = $this->refreshAccessToken($connection);

    $config = new Config($connection['access_token']);
    $httpClient = new Client();
    $constantContact = new ConstantContact($httpClient, $config);

    return $constantContact->contactLists()->add($contact_list_data);

  }

  /**
   * {@inheritdoc}
   */
  public function createEmailCampaigns($connection, $email_campaign_data) {
    // Refresh access token.
    $connection = $this->refreshAccessToken($connection);

    $config = new Config($connection['access_token']);

    $httpClient = new Client();
    $constantContact = new ConstantContact($httpClient, $config);

    return $constantContact->emailCampaigns()->create($email_campaign_data);
  }

  /**
   * {@inheritdoc}
   */
  public function updateEmailCampaignActivities($connection, $campaign_activity_id, $email_campaign_acitivity_data) {
    // Refresh access token.
    $connection = $this->refreshAccessToken($connection);

    $config = new Config($connection['access_token']);

    // @todo Need to do some checking with refresh token and expiry.
    $httpClient = new Client();
    $constantContact = new ConstantContact($httpClient, $config);

    return $constantContact->emailCampaignActivities()->update($campaign_activity_id, $email_campaign_acitivity_data);
  }

  /**
   * {@inheritdoc}
   */
  public function scheduleEmailCampaignActivities($connection, $campaign_activity_id, $schedule_data) {
    // Refresh access token.
    $connection = $this->refreshAccessToken($connection);

    $config = new Config($connection['access_token']);

    // @todo Need to do some checking with refresh token and expiry.
    $httpClient = new Client();
    $constantContact = new ConstantContact($httpClient, $config);

    return $constantContact->emailCampaignActivities()->schedule($campaign_activity_id, $schedule_data);
  }

  /**
   * {@inheritdoc}
   */
  public function subscribe($connection, $contact_list_ids, $data) {
    // Refresh access token.
    $connection = $this->refreshAccessToken($connection);

    // Contant Contact connection.
    $config = new Config($connection['access_token']);

    $httpClient = new Client();
    $constantContact = new ConstantContact($httpClient, $config);

    $subscribe_data = [
      'email_address' => $data['email'],
      'first_name' => $data['first_name'],
      'last_name' => $data['last_name'],
      'list_memberships' => $contact_list_ids,
    ];

    // @todo Check if we need to refresh?
    $contact_response = $constantContact->contacts()->signup($subscribe_data);

    if (!empty($contact_response) && !empty($contact_response['contact_id'])) {
      // @todo Get contact lists?
      $message = t('The @email_address email is now subscribed', [
        '@email_address' => $data['email'],
      ]);

      $this->logger->notice($message);
    }
    else {
      $errors = $this->processErrorResponse($contact_response);

      $message = t('Constant Contact: Could not subscribe @email_address. @errors', [
        '@email_address' => $data['email'],
        '@errors' => implode('. ', $errors) . '.',
      ]);

      $this->logger->error($message);
    }

    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function processErrorResponse($reponse) {
    $errors = [];

    if (!empty($reponse['error_message'])) {
      $errors[] = $reponse['error_message'];
    }
    else {
      foreach ($reponse as $field_errors) {
        if (!empty($field_errors['error_message'])) {
          $errors[] = $field_errors['error_message'];
        }
      }
    }

    return $errors;
  }

}
