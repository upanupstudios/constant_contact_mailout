<?php

namespace Drupal\constant_contact_mailout\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Serves the rendered mailout template when the debug option is enabled.
 *
 * The field item cannot throw an EnforcedResponseException to get the template
 * on screen: it runs inside $entity->save(), and NodeForm::save() wraps that
 * call in a catch (\Exception) block, so the exception is logged as a failed
 * save instead of reaching the kernel. It also aborts the rest of the entity
 * save. The field item stashes the template on the request instead, and this
 * subscriber swaps it in for the response the form would have returned.
 *
 * @see \Drupal\constant_contact_mailout\Plugin\Field\FieldType\ConstantContactMailoutFieldItem::postSave()
 */
class DebugTemplateSubscriber implements EventSubscriberInterface {

  /**
   * Request attribute holding the rendered template.
   */
  const REQUEST_ATTRIBUTE = '_constant_contact_mailout_debug_template';

  /**
   * Replaces the response with the rendered template.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }

    $template = $event->getRequest()->attributes->get(self::REQUEST_ATTRIBUTE);

    if (!empty($template)) {
      $event->setResponse(new Response($template, 200, [
        'Content-Type' => 'text/html; charset=utf-8',
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run late: actions such as core's action_goto_action (used by ECA) set a
    // redirect from a default-priority listener, which would replace this.
    $events[KernelEvents::RESPONSE][] = ['onResponse', -100];

    return $events;
  }

}
