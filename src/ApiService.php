<?php

namespace Drupal\constant_contact_mailout;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Upanupstudios\ConstantContact\Php\Client\Config;
use Upanupstudios\ConstantContact\Php\Client\ConstantContact;

/**
 * The ApiService class.
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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config instance.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * The Constant Contact API instance.
   *
   * @var \Upanupstudios\ConstantContact\Php\Client\ConstantContact
   */
  protected $constantContact;

  /**
   * Constructs a new ApiService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory interface.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory interface.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, MessengerInterface $messenger) {
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('constant_contact_mailout');
    $this->messenger = $messenger;

    // Get settings.
    $this->settings = $this->configFactory->get('constant_contact_mailout.settings');

    // Instanciate constant contact.
    $httpClient = new Client();
    $this->constantContact = new ConstantContact($httpClient);

    // @todo use function to get instance, we have multiple connections
    // therefore we can't pass configs here
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationUrl($api_key, $redirect_url, $scope, $state) {
    $authorizationUrl = $this->constantContact->getAuthorizationUrl($api_key, $redirect_url, $scope, $state);

    return $authorizationUrl;
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
  public function refreshAccessToken($connection, $forceRefresh = FALSE) {
    if ($forceRefresh || time() >= abs($connection['expires'])) {
      // Get connections.
      $connections = $this->settings->get('connections');

      // Refresh token.
      $response = $this->getRefreshToken($connection['refresh_token'], $connection['api_key'], $connection['secret']);

      if (!empty($response) && !empty($response['access_token'])) {
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
    }

    return $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllContactLists($connection, $params = []) {
    // Refresh access token.
    // $connection = $this->refreshAccessToken($connection);
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
    // $connection = $this->refreshAccessToken($connection);
    $config = new Config($connection['access_token']);
    $httpClient = new Client();
    $constantContact = new ConstantContact($httpClient, $config);

    return $constantContact->contactLists()->findByName($title);
  }

  /**
   * {@inheritdoc}
   */
  public function createContactList($connection, $contact_list_data) {
    // Refresh access token.
    // $connection = $this->refreshAccessToken($connection);
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
    // $connection = $this->refreshAccessToken($connection);
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
    // $connection = $this->refreshAccessToken($connection);
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
    // $connection = $this->refreshAccessToken($connection);
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
    // $connection = $this->refreshAccessToken($connection);
    // Contant Contact connection.
    $config = new Config($connection['access_token']);

    $httpClient = new Client();
    $constantContact = new ConstantContact($httpClient, $config);

    // Required data.
    $subscribe_data = [
      'email_address' => $data['email'],
      'list_memberships' => $contact_list_ids,
    ];

    // Optional data.
    if (array_key_exists('first_name', $data)) {
      $subscribe_data['first_name'] = $data['first_name'];
    }

    if (array_key_exists('last_name', $data)) {
      $subscribe_data['last_name'] = $data['last_name'];
    }

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
  public function sendMailout($connection, $contact_list_ids, $subject, $html_content, $schedule_data) {
    // Get connections.
    $connections = $this->settings->get('connections');

    // Decode subject to convert to special characters.
    $subject = htmlspecialchars_decode($subject);

    // Sender settings.
    $sender_from_name = $connection['sender_from_name'];
    $sender_from_email = $connection['sender_from_email'];
    $sender_replyto_email = $connection['sender_replyto_email'];

    if (empty($sender_replyto_email)) {
      $sender_replyto_email = $sender_from_email;
    }

    // Create date.
    $datetime = new DrupalDateTime('now');

    // Prepare email campaign data.
    $email_campaign_data = [
      'name' => $subject . ' @ ' . $datetime->format('Y-m-d H:i:s'),
      'email_campaign_activities' => [
        [
          // Use 5 to create a custom code email.
          'format_type' => 5,
          'from_email' => $sender_from_email,
          'from_name' => $sender_from_name,
          'reply_to_email' => $sender_replyto_email,
          'subject' => $subject,
          'html_content' => $html_content,
        ],
      ],
    ];

    // Create an email campaign and campaign activities.
    // Returns primary email and email campaign activity id.
    $email_campaign_response = $this->createEmailCampaigns($connection, $email_campaign_data);

    if (!empty($email_campaign_response) && !empty($email_campaign_response['campaign_id'])) {
      // Get email campaign data.
      $email_campaign_acitivity_data = $email_campaign_data['email_campaign_activities'][0];

      // Update the email campaign activity and add recipients.
      $campaign_activity_id = $email_campaign_response['campaign_activities'][0]['campaign_activity_id'];

      // Add campaign activity id, role and contact list ids.
      $email_campaign_acitivity_data['campaign_activity_id'] = $campaign_activity_id;
      $email_campaign_acitivity_data['role'] = $email_campaign_response['campaign_activities'][0]['role'];
      $email_campaign_acitivity_data['contact_list_ids'] = array_values($contact_list_ids);

      $email_campaign_activity_response = $this->updateEmailCampaignActivities($connection, $campaign_activity_id, $email_campaign_acitivity_data);

      if (!empty($email_campaign_activity_response) && !empty($email_campaign_activity_response['campaign_activity_id'])) {
        $email_campaign_activity_schedule_response = $this->scheduleEmailCampaignActivities($connection, $campaign_activity_id, $schedule_data);

        if (!empty($email_campaign_activity_schedule_response) && !empty($email_campaign_activity_schedule_response[0]['scheduled_date'])) {
          $contact_list_names = [];

          if (!empty($connections)) {
            foreach ($connections as $connection) {
              if (!empty($connection['lists'])) {
                foreach ($connection['lists'] as $list) {
                  if (in_array($list['list_id'], $contact_list_ids)) {
                    $contact_list_names[$list['list_id']] = $list['name'];
                  }
                }
              }
            }
          }

          if (empty($schedule_data['scheduled_date'])) {
            $message = t('The "@subject" has been sent now to @contact_list_names contact lists.', [
              '@subject' => $subject,
              '@date' => $datetime->format('F j, Y g:ia'),
              '@contact_list_names' => implode(', ', $contact_list_names),
            ]);
          }
          else {
            $scheduled_date = new DrupalDateTime($schedule_data['scheduled_date']);

            $message = t('The "@subject" will be sent on @date to @contact_list_names contact lists.', [
              '@subject' => $subject,
              '@date' => $scheduled_date->format('F j, Y g:ia'),
              '@contact_list_names' => implode(', ', $contact_list_names),
            ]);
          }

          // @todo Get total number of subscribers.
          $this->logger->notice($message);
          $this->messenger->addStatus($message);
        }
        else {
          $errors = $this->processErrorResponse($email_campaign_activity_schedule_response);

          $message = t('@errors', [
            '@errors' => implode('. ', $errors),
          ]);

          $this->logger->error($message);
          $this->messenger->addError($message);
        }
      }
      else {
        $errors = $this->processErrorResponse($email_campaign_activity_response);

        $message = t('@errors', [
          '@errors' => implode('. ', $errors),
        ]);

        $this->logger->error($message);
        $this->messenger->addError($message);
      }
    }
    else {
      $errors = $this->processErrorResponse($email_campaign_response);

      $message = t('@errors', [
        '@errors' => implode('. ', $errors),
      ]);

      $this->logger->error($message);
      $this->messenger->addError($message);
    }
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
