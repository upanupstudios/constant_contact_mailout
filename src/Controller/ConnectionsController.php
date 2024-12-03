<?php

namespace Drupal\constant_contact_mailout\Controller;

use Drupal\constant_contact_mailout\ApiService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The ConnectionsController class.
 */
class ConnectionsController extends ControllerBase {

  /**
   * API service.
   *
   * @var \Drupal\constant_contact_mailout\ApiService
   */
  protected $api;

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * The config instance.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * Constructs a new ProgramsController object.
   *
   * @param \Drupal\constant_contact_mailout\ApiService $api
   *   The api service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory interface.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request stack object.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match.
   */
  public function __construct(ApiService $api, ConfigFactoryInterface $config_factory, Request $request, MessengerInterface $messenger, RouteMatchInterface $current_route_match) {
    $this->api = $api;
    $this->configFactory = $config_factory;
    $this->request = $request;
    $this->messenger = $messenger;
    $this->currentRouteMatch = $current_route_match;

    // Get route name.
    $routeName = $current_route_match->getRouteName();

    // Get the parent route name.
    $this->parentRouteName = implode('.', array_slice(explode('.', $routeName), 0, -1));

    // Get settings.
    $this->settings = $this->configFactory->get('constant_contact_mailout.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('constant_contact_mailout.api'),
      $container->get('config.factory'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('messenger'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function authorize($id = NULL) {
    $connections = $this->settings->get('connections');
    $params = $this->request->query->all();

    if (!empty($params['code']) && !empty($params['state']) && $connections[$params['state']]) {
      $id = $params['state'];
      $connection = $connections[$id];

      // Redirect url.
      $redirect_url = Url::fromRoute('constant_contact_mailout.connections.authorize', [], ['absolute' => TRUE]);

      // Get access token.
      $response = $this->api->getAccessToken($redirect_url->toString(), $connection['api_key'], $connection['secret'], $params['code']);

      if (!empty($response['access_token']) && !empty($response['refresh_token'])) {
        // Save access_token and refresh token.
        $connection['access_token'] = $response['access_token'];
        $connection['refresh_token'] = $response['refresh_token'];
        $connection['expires'] = time() + abs($response['expires_in']);

        // Get all contact lists.
        // @todo There's a 1000 retrieve list limit, which we likely won't go over?
        $contact_lists = $this->api->getAllContactLists($connection, [
          'include_count' => 'true',
          'status' => 'active',
          'include_membership_count' => 'all',
        ]);

        if (!empty($contact_lists['lists'])) {
          $connection['lists'] = $contact_lists['lists'];
        }

        $connections[$id] = $connection;

        // Save connections.
        // @todo CHeck how this is saved... when refreshed, auto refresh it at certain times...
        $settings = $this->configFactory->getEditable('constant_contact_mailout.settings');
        $settings->set('connections', $connections)
          ->save();

        $this->messenger->addStatus($this->t('Your @name application is now connected.', [
          '@name' => $connection['name'],
        ]));
      }
      else {
        if (!empty($response['error_message'])) {
          $this->messenger->addError($this->t('@error_message.', [
            '@error_message' => $response['error_message'],
          ]));
        }
        elseif (!empty($response['error_description'])) {
          $this->messenger->addError($this->t('@error_description', [
            '@error_description' => $response['error_description'],
          ]));
        }
        else {
          $this->messenger->addError($this->t('Something went wrong.'));
        }
      }
    }
    else {
      $this->messenger->addError($this->t('Unable to authorize.'));
    }

    $redirect_url = Url::fromRoute('constant_contact_mailout.connections');
    $response = new RedirectResponse($redirect_url->toString());
    return $response->send();
  }

}
