<?php

namespace Drupal\constant_contact_mailout\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The AdminConnectionsForm class.
 */
class AdminConnectionsForm extends ConfigFormBase {

  /**
   * The route name.
   *
   * @var string
   */
  protected $routeName;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * Constructs a \Drupal\user\RestForm object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match.
   */
  public function __construct(RouteMatchInterface $current_route_match) {
    $this->currentRouteMatch = $current_route_match;

    // Get route name.
    $this->routeName = $current_route_match->getRouteName();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'constant_contact_mailout_admin_connections';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['constant_contact_mailout.connections'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('constant_contact_mailout.settings');
    $connections = $settings->get('connections');

    // @todo Add instructions
    // https://developer.constantcontact.com/api_guide/apps_create.html

    $headers = [
      'name' => $this->t('Name'),
      'api_key' => $this->t('API Key'),
      'authorization' => $this->t('Authorization'),
      'operations' => $this->t('Operations'),
    ];

    $rows = [];

    if (!empty($connections)) {
      // Sort by name.
      $names = [];

      foreach ($connections as $key => $record) {
        $names[$key] = trim($record['name']);
      }

      array_multisort($names, SORT_ASC, $connections);

      foreach ($connections as $connection) {
        $rows[] = [
          'name' => $connection['name'],
          'api_key' => implode(', ', explode(PHP_EOL, $connection['api_key'])),
          'authorization' => !empty($connection['access_token']) ? $this->t('Authorized') : $this->t('Not authorized'),
          'operations' => [
            'data' => [
              '#type' => 'operations',
              '#links' => [
                'edit' => [
                  'title' => $this->t('Edit'),
                  'url' => Url::fromRoute($this->routeName . '.edit', ['id' => $connection['id']]),
                ],
                'delete' => [
                  'title' => $this->t('Delete'),
                  'url' => Url::fromRoute($this->routeName . '.delete', ['id' => $connection['id']]),
                ],
              ],
            ],
          ],
        ];
      }
    }

    $form['header'] = [
      '#markup' => '<h2>Connections</h2>',
    ];
    $form['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No connections'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // No submission.
  }

}
