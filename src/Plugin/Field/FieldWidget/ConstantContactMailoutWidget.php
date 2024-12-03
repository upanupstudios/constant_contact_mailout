<?php

namespace Drupal\constant_contact_mailout\Plugin\Field\FieldWidget;

use Drupal\constant_contact_mailout\Utility\TextHelper;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'constant_contact_mailout_default' widget.
 *
 * @FieldWidget(
 *   id = "constant_contact_mailout_default",
 *   label = @Translation("Mailout"),
 *   field_types = {
 *     "constant_contact_mailout"
 *   },
 *   settings = {
 *     "placeholder" = "Select Constant Contact contact list(s)."
 *   }
 * )
 */
class ConstantContactMailoutWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Get settings.
    $settings = \Drupal::config('constant_contact_mailout.settings');
    $connections = $settings->get('connections');
    $url = Url::fromRoute('constant_contact_mailout.settings');

    $contact_list_creation = $this->getFieldSetting('contact_list_creation');
    $contact_list_ids = $this->getFieldSetting('contact_list_ids');
    $contact_list_select = $this->getFieldSetting('contact_list_select');
    $node_types = $this->getFieldSetting('node_types');
    $description = NULL;
    $contact_lists = [];
    $contact_list_names = [];

    $debug_render_template = $settings->get('debug_render_template');
    $debug_sendto_contact_list = $settings->get('debug_sendto_contact_list');

    if (!empty($connections)) {
      foreach ($connections as $connection) {
        if (!empty($connection['lists'])) {
          foreach ($connection['lists'] as $list) {
            $id = $connection['id'] . ':' . $list['list_id'];

            $contact_lists[$connection['name']][$id] = $this->t('@name (@membership_count subscribers)', [
              '@name' => $list['name'],
              '@membership_count' => $list['membership_count'],
            ]);

            if (!empty($debug_sendto_contact_list)) {
              if ($debug_sendto_contact_list == $id) {
                $contact_list_names[] = $this->t('@name (@membership_count subscribers)', [
                  '@name' => $list['name'],
                  '@membership_count' => $list['membership_count'],
                ]);
              }
            }
          }
        }
      }

      if (!empty($debug_render_template)) {
        $message = $this->t('Contant Contact: Render email template on screen when publishing a node is enabled when sending a mailout. Change in @constant_contact_settings.', [
          '@constant_contact_settings' => Link::fromTextAndUrl($this->t('Constant Contact settings'), $url)->toString(),
        ]);

        \Drupal::messenger()->addWarning($message);
      }

      if (!empty($debug_sendto_contact_list)) {
        $message = $this->t('Contant Contact: Mailout will be sent to @contact_list_names contact lists. Change in @constant_contact_settings.', [
          '@contact_list_names' => implode(', ', $contact_list_names),
          '@constant_contact_settings' => Link::fromTextAndUrl($this->t('Constant Contact settings'), $url)->toString(),
        ]);

        \Drupal::messenger()->addWarning($message);

        $description = $this->t('Mailout will be sent to @contact_list_names contact lists.', [
          '@contact_list_names' => implode(', ', $contact_list_names),
        ]);
      }
      else {
        if ($contact_list_creation == 'dynamic') {
          $element['contact_list_id'] = [
            '#type' => 'hidden',
            '#value' => $items[$delta]->contact_list_id,
          ];

          if ($items->getEntity()->isNew()) {
            $description = $this->t('The contact list will be dynamically created, if it does not exist. Mailout will be sent to the subscribers for this content.');
          }
          else {
            if (!empty($contact_lists)) {
              foreach ($contact_lists as $name => $lists) {
                foreach ($lists as $contact_list_id => $contact_list_name) {
                  if ($contact_list_id == $items[$delta]->contact_list_id) {
                    $contact_list_names[] = $contact_list_name->__toString();
                  }
                }
              }
            }

            $description = $this->t('Mailout will be sent to the @contact_list_names contact list.', [
              '@contact_list_names' => implode(', ', $contact_list_names),
            ]);
          }
        }
        elseif ($contact_list_creation == 'select') {
          if (empty($contact_list_select)) {
            if (!empty($contact_lists)) {
              foreach ($contact_lists as $name => $lists) {
                foreach ($lists as $contact_list_id => $contact_list_name) {
                  if (array_key_exists($contact_list_id, $contact_list_ids)) {
                    $contact_list_names[] = $contact_list_name->__toString();
                  }
                }
              }
            }
          }

          if (!empty($contact_list_names)) {
            $description = $this->t('Mailout will be sent to the @contact_list_names contact list(s).', [
              '@contact_list_names' => implode(', ', $contact_list_names),
            ]);
          }
          else {
            $description = $this->t('Mailout will be sent to selected lists below.');
          }
        }
        elseif ($contact_list_creation == 'taxonomy') {
          $description = $this->t('Mailout will be sent to the contact lists mapped in the taxonomy term.');
        }
        elseif ($contact_list_creation == 'reference') {
          if (!empty($node_types)) {
            $entity = $items->getEntity();
            $entity_type_id = $entity->getEntityType()->id();
            $content_type = $entity->getType();
            $definitions = $entity->getFieldDefinitions($entity_type_id, $content_type);

            if (!empty($definitions)) {
              foreach ($definitions as $definition) {
                if ($definition instanceof FieldConfigInterface) {
                  if ($definition->getType() == 'entity_reference') {
                    $settings = $definition->getSettings();

                    if ($settings['target_type'] == 'node') {
                      $target_bundle = reset($settings['handler_settings']['target_bundles']);

                      if (in_array($target_bundle, $node_types)) {
                        // Get field machine name.
                        $field_name = $definition->getName();

                        // Get value.
                        $value = $entity->$field_name->getValue();
                        $value = reset($value);

                        if (!empty($value['target_id'])) {
                          // Load entity.
                          $_entity = \Drupal::entityTypeManager()->getStorage($settings['target_type'])->load($value['target_id']);

                          $_definitions = $_entity->getFieldDefinitions();

                          if (!empty($_definitions)) {
                            foreach ($_definitions as $_definition) {
                              if ($_definition instanceof FieldConfigInterface) {
                                $fieldType = $_definition->getFieldStorageDefinition()->getType();

                                if ($fieldType == 'constant_contact_mailout') {
                                  $settings = $_definition->getSettings();

                                  // Must be set as dynamic, what if select?
                                  if (!empty($settings['contact_list_creation']) && $settings['contact_list_creation'] == 'dynamic') {
                                    $field_name = $_definition->getName();
                                    $value = $_entity->$field_name->getValue();
                                    $value = reset($value);

                                    if (!empty($value['contact_list_id'])) {
                                      if (!empty($contact_lists)) {
                                        foreach ($contact_lists as $name => $lists) {
                                          foreach ($lists as $contact_list_id => $contact_list_name) {
                                            if ($value['contact_list_id'] == $contact_list_id) {
                                              $contact_list_names[] = $contact_list_name->__toString();
                                            }
                                          }
                                        }
                                      }
                                    }
                                  }
                                }
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }

          if (!empty($contact_list_names)) {
            $description = $this->t('Mailout will be sent to the @contact_list_names contact list(s).', [
              '@contact_list_names' => implode(', ', $contact_list_names),
            ]);
          }
          else {
            // @todo if dynamic add
            $description = $this->t('The contact list will be dynamically created, if it does not exist. Mailout will be sent to the subscribers of the entity referenced from this content.');
          }
        }
      }

      if ($this->getFieldSetting('sendnow_description')) {
        $description = $this->getFieldSetting('sendnow_description');
      }

      $element['sendnow'] = [
        '#title' => $this->getFieldSetting('sendnow_label'),
        '#description' => $description,
        '#type' => 'checkbox',
        '#attributes' => [
          'class' => [
            'sendnow-checkbox',
          ],
        ],
        '#attached' => [
          'library' => [
            'constant_contact_mailout/constant_contact_mailout_widget',
          ],
        ],
      ];

      if ($contact_list_creation == 'select' && !empty($contact_list_select)) {
        if (!empty($contact_lists)) {
          foreach ($contact_lists as $name => $lists) {
            $options = [];

            // Filter options to selected contact lists.
            foreach ($lists as $contact_list_id => $contact_list_name) {
              if (array_key_exists($contact_list_id, $contact_list_ids)) {
                $options[$contact_list_id] = $contact_list_name->__toString();
              }
            }

            if (!empty($options)) {
              $id = TextHelper::textToMachineName($name);

              $element['contact_list_ids'][$id] = [
                '#title' => $name,
                '#type' => 'checkboxes',
                '#options' => $options,
                '#attributes' => [
                  'class' => [
                    'sendnow-contact-lists',
                  ],
                ],
              ];
            }
          }
        }
      }

      if (!empty($this->getFieldSetting('scheduled_mailout'))) {
        $element['sendlater'] = [
          '#title' => $this->getFieldSetting('scheduled_mailout_label'),
          '#type' => 'checkbox',
          '#attributes' => [
            'class' => [
              'sendlater-checkbox',
            ],
          ],
        ];

        $element['sendlater_datetime'] = [
          '#title' => $this->t('Schedule date &amp; time'),
          '#type' => 'datetime',
          '#attributes' => [
            'class' => [
              'sendlater-datetime',
            ],
          ],
          '#element_validate' => [
            [static::class, 'validate'],
          ],
        ];
      }
    }
    else {
      $url = Url::fromRoute('constant_contact_mailout.connections');

      $message = $this->t('Constant Contact: There are no connections. Add @constant_contact_connections.', [
        '@constant_contact_connections' => Link::fromTextAndUrl($this->t('connections'), $url)->toString(),
      ]);

      \Drupal::messenger()->addMessage($message, 'warning', FALSE);
    }

    return $element;
  }

  /**
   * Validate the datetime field.
   */
  public static function validate($element, FormStateInterface $form_state) {
    // Get the field name.
    $field_name = current($element['#parents']);

    // Get value.
    $value = $form_state->getValue($field_name);

    if (!empty($value[0]['sendlater'])) {
      $sendlater_datetime = array_filter($value[0]['sendlater_datetime']);

      if (!empty($sendlater_datetime) && !empty($sendlater_datetime['date']) && !empty($sendlater_datetime['time'])) {
        // Check if it's greater than the date and time now
        // Note: the site's timezone is used to convert the time.
        $now = new DrupalDateTime();
        $datetime = $sendlater_datetime['object'];

        if ($datetime < $now) {
          $form_state->setError($element, $this->t("Schedule must be set in the future."));
        }
      }
      else {
        $form_state->setError($element, $this->t("Enter scheduled date and time."));
      }
    }
  }

}
