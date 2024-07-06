<?php

namespace Drupal\constant_contact_mailout\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The AdminDeleteConnectionForm class.
 */
class AdminDeleteConnectionForm extends ConfirmFormBase {

  /**
   * The object id.
   *
   * @var string
   */
  public $id;

  /**
   * The connection.
   *
   * @var string
   */
  public $connection;

  /**
   * The parent route name.
   *
   * @var string
   */
  protected $parentRouteName;

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * Constructs a AdminDropinsDeleteActivityForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MessengerInterface $messenger, RouteMatchInterface $current_route_match) {
    $this->configFactory = $config_factory;
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
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'constant_contact_mailout_admin_delete_connection';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to delete %connection?', [
      '%connection' => $this->connection['name'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url($this->parentRouteName);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $this->id = $id;
    $connections = $this->config('constant_contact_mailout.settings')->get('connections') ?: [];

    if (!array_key_exists($this->id, $connections)) {
      $this->messenger->addError($this->t('Unable to delete. Invalid connection.'));
      $url = Url::fromRoute($this->parentRouteName)->toString();
      return new RedirectResponse($url);
    }

    $this->connection = $connections[$this->id];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $connections = $this->config('constant_contact_mailout.settings')->get('connections') ?: [];

    if (!array_key_exists($this->id, $connections)) {
      $this->messenger->addError($this->t('Unable to delete. Invalid connection.'));
      $url = Url::fromRoute($this->parentRouteName)->toString();
      return new RedirectResponse($url);
    }

    if (!empty($this->id)) {
      // Remove the connection.
      unset($connections[$this->id]);
    }

    // Save activities.
    $config = $this->configFactory->getEditable('constant_contact_mailout.settings');
    $config->set('connections', $connections)->save();

    $this->messenger->addStatus($this->t('The connection has been deleted.'));

    $form_state->setRedirect($this->parentRouteName);
  }

}
