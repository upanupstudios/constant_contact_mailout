<?php

namespace Drupal\constant_contact_mailout\Plugin\Field\FieldType;

use Drupal\constant_contact_mailout\Utility\TextHelper;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Url;
use Drupal\field\FieldConfigInterface;

/**
 * Plugin implementation of the 'constant_contact_mailout' field type.
 *
 * @FieldType(
 *   id = "constant_contact_mailout",
 *   label = @Translation("Constant Contact mailout"),
 *   description = @Translation("Allows an entity to send an email to subscribers through Constant Contact API."),
 *   default_widget = "constant_contact_mailout_default"
 * )
 */
class ConstantContactMailoutFieldItem extends FieldItemBase {

  /**
   * Toggle for mailout.
   *
   * @var bool
   */
  protected $mailout = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'subject' => '@title',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'contact_list_creation' => 'select',
      'connection_id' => NULL,
      'contact_list_prefix' => NULL,
      'contact_list_ids' => [],
      'contact_list_select' => FALSE,
      'vocabularies' => NULL,
      'terms' => [],
      'sendnow_label' => 'Send mailout to subscribers now',
      'sendnow_select_contact_lists' => FALSE,
      'scheduled_mailout' => FALSE,
      'scheduled_mailout_label' => 'Schedule mailout to subscribers later',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'contact_list_id' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['contact_list_id'] = DataDefinition::create('string')
      ->setLabel(t('Contact List ID'))
      ->setDescription(t('Constant Contact Contact List ID'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);

    $element['subject'] = [
      '#type' => 'textfield',
      '#title' => t('Email subject'),
      '#description' => t('Use @type or @title field names for replacement'),
      '#default_value' => $this->getSetting('subject'),
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);

    $settings = \Drupal::config('constant_contact_mailout.settings');
    $connections = $settings->get('connections');

    $contact_lists = [];

    if (!empty($connections)) {
      foreach ($connections as $connection) {
        $active_connections[$connection['id']] = $connection['name'];

        if (!empty($connection['lists'])) {
          foreach ($connection['lists'] as $list) {
            $id = $connection['id'] . ':' . $list['list_id'];

            $contact_lists[$connection['name']][$id] = $this->t('@name (@membership_count subscribers)', [
              '@name' => $list['name'],
              '@membership_count' => $list['membership_count'],
            ]);
          }
        }
        else {
          $message = $this->t('Constant Contact: Contact lists does not exists.');

          \Drupal::messenger()->addMessage($message, 'error', FALSE);
        }
      }

      $element['contact_list_creation'] = [
        '#title' => $this->t('Contact Lists'),
        '#type' => 'radios',
        '#default_value' => $this->getSetting('contact_list_creation'),
        '#options' => [
          'dynamic' => $this->t('Dynamically create contact list to send mailouts to contact lists associated with single node of this content type.'),
          'select' => $this->t('Send mailout to the selected contact lists for all nodes of this content type.'),
          'taxonomy' => $this->t('Send mailout to contact lists mapped from taxonomy terms of this content type.'),
        ],
        '#attributes' => [
          'class' => [
            'contact-list-creation-radios',
          ],
        ],
        '#attached' => [
          'library' => [
            'constant_contact_mailout/constant_contact_mailout_fieldsettings',
          ],
        ],
        '#element_validate' => [[get_class($this), 'creationDynamicValidate']],
      ];

      // Dynamic Contact Lists.
      $element['contact_list_creation_dynamic'] = [
        '#type' => 'details',
        '#title' => $this->t('Dynamic Contact List Creation'),
        '#open' => TRUE,
        '#attributes' => [
          'class' => [
            'contact-list-creation-dynamic-details',
          ],
        ],
      ];

      $element['contact_list_creation_dynamic']['connection_id'] = [
        '#title' => 'Connection',
        '#type' => 'select',
        '#options' => $active_connections,
        '#default_value' => $this->getSetting('connection_id'),
      ];

      $element['contact_list_creation_dynamic']['contact_list_prefix'] = [
        '#title' => 'Contact List Prefix',
        '#type' => 'textfield',
        '#description' => $this->t('Add a prefix to contact list name for organization. Leave blank for no prefix.'),
        '#default_value' => $this->getSetting('contact_list_prefix'),
      ];

      // Select Contact Lists.
      $element['contact_list_creation_selected'] = [
        '#type' => 'details',
        '#title' => 'Selected Contact Lists',
        '#open' => TRUE,
        '#attributes' => [
          'class' => [
            'contact-list-creation-select-details',
          ],
        ],
      ];

      if (!empty($contact_lists)) {
        foreach ($contact_lists as $name => $lists) {
          // Sort by value.
          uasort($lists, function ($a, $b) {
            return strcmp($a->__toString(), $b->__toString());
          });

          $id = TextHelper::textToMachineName($name);

          $element['contact_list_creation_selected']['contact_list_ids'][$id] = [
            '#title' => $name,
            '#type' => 'checkboxes',
            '#multiple' => TRUE,
            '#options' => $lists,
            '#default_value' => $this->getSetting('contact_list_ids'),
          ];
        }
      }

      $element['contact_list_creation_selected']['contact_list_select'] = [
        '#title' => 'Select contact lists before sending',
        '#type' => 'checkbox',
        '#default_value' => $this->getSetting('contact_list_select'),
      ];

      // Taxonomy Contact Lists.
      $entityManager = \Drupal::service('entity_field.manager');

      $field = $form_state->getFormObject()->getEntity();
      $content_type = $field->getTargetBundle();
      $entity_type_id = $field->getTargetEntityTypeId();

      $vocabularies = [];
      $taxonomies = [];

      if (!empty($content_type)) {
        $definitions = $entityManager->getFieldDefinitions($entity_type_id, $content_type);

        if (!empty($definitions)) {
          foreach ($definitions as $definition) {
            if ($definition instanceof FieldConfigInterface) {
              if ($definition->getType() == 'entity_reference') {
                $settings = $definition->getSettings();

                if ($settings['target_type'] == 'taxonomy_term') {
                  // Get the first target handler.
                  $taget_bundle = reset($settings['handler_settings']['target_bundles']);
                  $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($taget_bundle);

                  $vocabularies[$taget_bundle] = $vocabulary->label();
                }
              }
            }
          }

          if (!empty($vocabularies)) {
            foreach ($vocabularies as $target_bundle => $label) {
              $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($target_bundle);

              if (!empty($terms)) {
                foreach ($terms as $term) {
                  $taxonomies[$target_bundle][$term->tid] = $term->name;
                }
              }
            }
          }
        }
      }

      $element['contact_list_creation_taxonomy'] = [
        '#type' => 'details',
        '#title' => 'Taxonomy Term Contact Lists',
        '#open' => TRUE,
        '#attributes' => [
          'class' => [
            'contact-list-creation-taxonomy-details',
          ],
        ],
      ];

      // What if there are more than one vocabularies or list?
      if (!empty($vocabularies)) {
        $terms = $this->getSetting('terms');

        $element['contact_list_creation_taxonomy']['vocabularies'] = [
          '#title' => $this->t('Vocabularies'),
          '#type' => 'checkboxes',
          '#options' => $vocabularies,
          '#default_value' => $this->getSetting('vocabularies'),
          '#description' => $this->t('Select vocabularies to map.'),
          '#attributes' => [
            'class' => [
              'contact-list-creation-taxonomy-vocabularies',
            ],
          ],
        ];

        // For each Vocabulary term, can we hide/show a sub details?
        foreach ($vocabularies as $vid => $vocabulary) {
          $element['contact_list_creation_taxonomy'][$vid] = [
            '#type' => 'details',
            '#title' => $vocabulary,
            '#attributes' => [
              'class' => [
                'contact-list-creation-taxonomy-' . $vid . '-details',
              ],
            ],
            '#description' => $this->t('Map each term below to a contact list.'),
          ];

          $terms = $this->getSetting('terms');

          if (!empty($taxonomies[$target_bundle])) {
            foreach ($taxonomies[$target_bundle] as $tid => $name) {
              $element['contact_list_creation_taxonomy'][$vid][$tid] = [
                '#type' => 'details',
                '#title' => $name,
                '#attributes' => [
                  'class' => [
                    'contact-list-creation-taxonomy-' . $tid . '-details',
                  ],
                ],
              ];

              if (!empty($contact_lists)) {
                foreach ($contact_lists as $name => $lists) {
                  // Sort by value.
                  uasort($lists, function ($a, $b) {
                    return strcmp($a->__toString(), $b->__toString());
                  });

                  $id = TextHelper::textToMachineName($name);

                  $selected_options = [];

                  if (!empty($terms[$tid]) && !empty($terms[$tid][$id])) {
                    $selected_options = $terms[$tid][$id];
                  }

                  // @todo , if multiple connections, we need to add multiple lists and then combine all in validate if many
                  $element['contact_list_creation_taxonomy'][$vid][$tid][$id] = [
                    '#title' => $name,
                    '#type' => 'checkboxes',
                    '#multiple' => TRUE,
                    '#options' => $lists,
                    '#default_value' => $selected_options,
                  ];

                }
              }
            }
          }
        }
      }
      else {
        $element['contact_list_creation_taxonomy']['message'] = [
          '#markup' => $this->t('Add fields with reference to a taxonomy.'),
        ];
      }

      $element['sendnow_label'] = [
        '#title' => 'Send now label',
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => $this->getSetting('sendnow_label'),
      ];

      $element['scheduled_mailout'] = [
        '#title' => $this->t('Enable scheduled mailout'),
        '#type' => 'checkbox',
        '#default_value' => $this->getSetting('scheduled_mailout'),
        '#attributes' => [
          'class' => [
            'scheduled-mailout-checkbox',
          ],
        ],
      ];

      $element['scheduled_mailout_label'] = [
        '#title' => 'Scheduled mailout Label',
        '#type' => 'textfield',
        '#default_value' => $this->getSetting('scheduled_mailout_label'),
      ];
    }
    else {
      $url = Url::fromRoute('constant_contact_mailout.connections');

      $message = $this->t('There are no Constant Contact connections! Create @constant_contact_connections first.', [
        '@constant_contact_connections' => Link::fromTextAndUrl($this->t('connections'), $url)->toString(),
      ]);

      \Drupal::messenger()->addError($message);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function creationDynamicValidate(array $element, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $settings = $values['settings'];

    if ($settings['contact_list_creation'] == 'dynamic') {
      $form_state->setValue(['settings', 'connection_id'], $settings['contact_list_creation_dynamic']['connection_id']);
      $form_state->setValue(['settings', 'contact_list_prefix'], $settings['contact_list_creation_dynamic']['contact_list_prefix']);
    }
    else {
      $form_state->setValue(['settings', 'connection_id'], NULL);
      $form_state->setValue(['settings', 'contact_list_prefix'], NULL);
    }

    if ($settings['contact_list_creation'] == 'select') {
      $contact_list_ids = [];

      if (!empty($settings['contact_list_creation_selected']['contact_list_ids'])) {
        foreach ($settings['contact_list_creation_selected']['contact_list_ids'] as $list_ids) {
          $contact_list_ids = array_merge($contact_list_ids, array_filter($list_ids));
        }
      }

      $form_state->setValue(['settings', 'contact_list_ids'], $contact_list_ids);
      $form_state->setValue(['settings', 'contact_list_select'], $settings['contact_list_creation_selected']['contact_list_select']);
    }
    else {
      $form_state->setValue(['settings', 'contact_list_ids'], []);
      $form_state->setValue(['settings', 'contact_list_select'], FALSE);
    }

    if ($settings['contact_list_creation'] == 'taxonomy') {
      $vocabularies = array_filter($settings['contact_list_creation_taxonomy']['vocabularies']);
      $terms = [];

      if (!empty($vocabularies)) {
        unset($settings['contact_list_creation_taxonomy']['vocabularies']);
      }

      if (!empty($settings['contact_list_creation_taxonomy'])) {
        foreach ($settings['contact_list_creation_taxonomy'] as $vid => $vocabulary) {
          if (!empty($vocabulary)) {
            foreach ($vocabulary as $tid => $vocabulary_terms) {
              if (!empty($vocabulary_terms)) {
                foreach ($vocabulary_terms as $connection_id => $contact_lists) {
                  $contact_lists = array_filter($contact_lists);

                  if (!empty($contact_lists)) {
                    $terms[$tid][$connection_id] = $contact_lists;
                  }
                }
              }
            }
          }
        }
      }

      $form_state->setValue(['settings', 'vocabularies'], $vocabularies);
      $form_state->setValue(['settings', 'terms'], $terms);
    }
    else {
      $form_state->setValue(['settings', 'vocabularies'], NULL);
      $form_state->setValue(['settings', 'terms'], []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();

    // Get settings.
    $settings = \Drupal::config('constant_contact_mailout.settings');
    $connections = $settings->get('connections');

    $contact_list_creation = $this->getSetting('contact_list_creation');
    $connection_id = $this->getSetting('connection_id');
    $contact_list_prefix = $this->getSetting('contact_list_prefix');

    // Dynamic - Create contact list using node title.
    if ($contact_list_creation == 'dynamic') {
      if (!empty($connections) && !empty($connections[$connection_id])) {
        // Get Constant Contact API.
        $api = \Drupal::service('constant_contact_mailout.api');

        $connection = $connections[$connection_id];

        // @todo Refresh access_token?
        // @todo Resave connection contact lists
        // Get entity.
        $entity = $this->getEntity();

        // Get entity title.
        $title = $entity->getTitle();

        if (!empty($contact_list_prefix)) {
          $title = $contact_list_prefix . $title;
        }

        if (!empty($title)) {
          // Find contact list by title.
          $contact_list = $api->findByNameContactLists($connection, $title);

          // There is no contact list stored.
          if (empty($this->contact_list_id)) {
            // There is no contact list created.
            if (empty($contact_list)) {
              // Create the contact list using the title.
              $contact_list_data = [
                'name' => $title,
              ];

              $contact_list_response = $api->addToContactLists($connection, $contact_list_data);

              if (!empty($contact_list_response) && !empty($contact_list_response['list_id'])) {
                // Save the contact list ID.
                $this->contact_list_id = $connection_id . ':' . $contact_list_response['list_id'];

                $message = $this->t('The contact list @name has been created.', [
                  '@name' => $contact_list_response['name'],
                ]);

                \Drupal::logger('constant_contact_mailout')->notice($message);
                \Drupal::messenger()->addMessage($message, 'status', FALSE);
              }
              else {
                $errors = $api->processErrorResponse($contact_list_response);

                $message = $this->t('Constant Contact: @errors', [
                  '@errors' => implode('. ', $errors),
                ]);

                \Drupal::logger('constant_contact_mailout')->error($message);
                \Drupal::messenger()->addMessage($message, 'error', FALSE);
              }
            }
            else {
              if (!empty($contact_list['list_id'])) {
                $this->contact_list_id = $connection_id . ':' . $contact_list['list_id'];
              }
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    parent::postSave($update);

    $contact_list_creation = $this->getSetting('contact_list_creation');

    // Get entity.
    $entity = $this->getEntity();
    $isPublished = $entity->isPublished();

    if ($isPublished && (!empty($this->sendnow) || !empty($this->sendlater))) {
      // Get settings.
      $settings = \Drupal::config('constant_contact_mailout.settings');
      $connections = $settings->get('connections');

      if (!empty($connections)) {
        $debug_render_template = $settings->get('debug_render_template');
        $debug_sendto_contact_list = $settings->get('debug_sendto_contact_list');

        $template = $this->prepareTemplate($entity);

        if (!empty($debug_render_template)) {
          // Output template render on screen.
          echo $template;

          // Die to show template.
          // @todo Need a better way to debug the template for development?
          die();
        }
        else {
          $connection = NULL;

          if (!empty($debug_sendto_contact_list)) {
            [$connection_id, $contact_list_id] = explode(':', $debug_sendto_contact_list);

            if (!empty($connections[$connection_id])) {
              $connection = $connections[$connection_id];
            }

            $contact_list_ids = [$contact_list_id];

            if (!empty($connection)) {
              $this->sendMailout($connection, $contact_list_ids, $entity, $template);
            }
          }
          else {
            if ($contact_list_creation == 'dynamic') {
              if (!empty($this->contact_list_id)) {
                [$connection_id, $contact_list_id] = explode(':', $this->contact_list_id);

                if (!empty($connections[$connection_id])) {
                  $connection = $connections[$connection_id];
                }

                $contact_list_ids = [$contact_list_id];

                if (!empty($connection)) {
                  $this->sendMailout($connection, $contact_list_ids, $entity, $template);
                }
              }
              else {
                $message = $this->t('Constant Contant: Invalid contact list.');

                \Drupal::logger('constant_contact_mailout')->error($message);
                \Drupal::messenger()->addMessage($message, 'error', FALSE);
              }
            }
            elseif ($contact_list_creation == 'select') {
              $contact_list_select = $this->getSetting('contact_list_select');
              $connection_contact_lists = [];

              if (empty($contact_list_select)) {
                // Get contact list ids from settings.
                $contact_list_ids = $this->getSetting('contact_list_ids');

                // Remove empty contact list ids.
                $contact_list_ids = array_filter($contact_list_ids);

                foreach ($contact_list_ids as $contact_list_id) {
                  [$connection_id, $contact_list_id] = explode(':', $contact_list_id);
                  $connection_contact_lists[$connection_id][] = $contact_list_id;
                }
              }
              else {
                // Get contact list ids from selection.
                if (!empty($this->values['contact_list_ids'])) {
                  foreach ($this->values['contact_list_ids'] as $connection_id => $contact_list_ids) {
                    foreach ($contact_list_ids as $contact_list_id) {
                      [$connection_id, $contact_list_id] = explode(':', $contact_list_id);
                      $connection_contact_lists[$connection_id][] = $contact_list_id;
                    }
                  }
                }
              }

              if (!empty($connection_contact_lists)) {
                foreach ($connection_contact_lists as $connection_id => $contact_list_ids) {
                  if (!empty($connections[$connection_id])) {
                    $connection = $connections[$connection_id];
                  }

                  if (!empty($connection) && !empty($contact_list_ids)) {
                    $this->sendMailout($connection, $contact_list_ids, $entity, $template);
                  }
                }
              }
            }
            elseif ($contact_list_creation == 'taxonomy') {
              $terms = $this->getSetting('terms');
              $connection_contact_lists = [];

              if (!empty($terms)) {
                foreach ($terms as $term_id => $contact_lists) {
                  foreach ($contact_lists as $connection_id => $contact_list_ids) {
                    foreach ($contact_list_ids as $contact_list_id) {
                      [$connection_id, $contact_list_id] = explode(':', $contact_list_id);
                      $connection_contact_lists[$connection_id][] = $contact_list_id;
                    }
                  }
                }
              }

              if (!empty($connection_contact_lists)) {
                foreach ($connection_contact_lists as $connection_id => $contact_list_ids) {
                  if (!empty($connections[$connection_id])) {
                    $connection = $connections[$connection_id];
                  }
                  $contact_list_ids = array_unique($contact_list_ids);

                  if (!empty($connection) && !empty($contact_list_ids)) {
                    $this->sendMailout($connection, $contact_list_ids, $entity, $template);
                  }
                }
              }
            }
          }
        }
      }
      else {
        $url = Url::fromRoute('constant_contact_mailout.connections');

        $message = $this->t('Contant Contact: There are no connections! Add @constant_contact_connections.', [
          '@constant_contact_connections' => Link::fromTextAndUrl($this->t('connections'), $url)->toString(),
        ]);

        \Drupal::messenger()->addError($message);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prepareContent($content) {
    if (is_string($content)) {
      // Get current schema and host.
      $scheme_and_host = \Drupal::request()->getSchemeAndHttpHost();

      // Rewrite relative urls with current scheme and domain in links.
      preg_match_all('#<a\s.*?(?:href=[\'"](.*?)[\'"]).*?>#is', $content, $matches);

      if (!empty($matches)) {
        // Filter and make unique.
        $matches[1] = array_filter(array_unique($matches[1]));

        foreach ($matches[1] as $match) {
          if (preg_match("/^\//", $match)) {
            $content = str_replace($match, $scheme_and_host . $match, $content);
          }
        }
      }

      // Rewrite relative url with current scheme and domain in images.
      preg_match_all('#<img\s.*?(?:src=[\'"](.*?)[\'"]).*?/>#is', $content, $matches);

      if (!empty($matches)) {
        // Filter and make unique.
        $matches[1] = array_filter(array_unique($matches[1]));

        foreach ($matches[1] as $match) {
          if (preg_match("/^\//", $match)) {
            $content = str_replace($match, $scheme_and_host . $match, $content);
          }
        }
      }
    }

    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareTemplate($entity) {
    // Get the default theme and change it back to active rendering.
    $config = \Drupal::config('system.theme');
    $default_theme = \Drupal::service('theme.initialization')->getActiveThemeByName($config->get('default'));
    $active_theme = \Drupal::theme()->getActiveTheme();
    $theme_name = $default_theme->getName();

    \Drupal::theme()->setActiveTheme($default_theme);

    // Get date fields and correct the time.
    $fields = $entity->getFields();
    $timezone = date_default_timezone_get();

    if (!empty($fields)) {
      foreach ($fields as $field) {
        $field_type = $field->getFieldDefinition()->getType();

        if (in_array($field_type, ['date', 'datetime', 'date_recur'])) {
          $datetime_type = $field->getFieldDefinition()->getSetting('datetime_type');

          if ($datetime_type == 'datetime') {
            $field_name = $field->getName();

            $value = new \DateTime($entity->$field_name->value, new \DateTimeZone('UTC'));
            $value->setTimezone(new \DateTimeZone($timezone));
            $entity->$field_name->value = sprintf("%sT%s", $value->format('Y-m-d'), $value->format('H:i:s'));

            $end_value = new \DateTime($entity->$field_name->end_value, new \DateTimeZone('UTC'));
            $end_value->setTimezone(new \DateTimeZone($timezone));
            $entity->$field_name->end_value = sprintf("%sT%s", $end_value->format('Y-m-d'), $end_value->format('H:i:s'));
          }
        }
      }
    }

    // Prepare body content.
    if (!empty($entity->body)) {
      $entity->body->value = $this->prepareContent($entity->body->value);
    }

    // Get theme variables (settings)
    $theme_settings = \Drupal::config($theme_name . '.settings')->get();

    // Render template.
    $template = [
      '#theme' => 'constant_contact_mailout',
      '#entity' => $entity,
      '#theme_settings' => $theme_settings,
    ];

    $render = \Drupal::service('renderer')->render($template);

    // Restore active theme.
    \Drupal::theme()->setActiveTheme($active_theme);

    return $render;
  }

  /**
   * {@inheritdoc}
   */
  public function sendMailout($connection, $contact_list_ids, $entity, $template) {
    // Get settings.
    $settings = \Drupal::config('constant_contact_mailout.settings');
    $connections = $settings->get('connections');

    // Get entity properties.
    $title = $entity->getTitle();
    $type = $entity->type->entity->label();

    // @todo Refesh access_token
    // Get email subject.
    $subject = $this->getSetting('subject');

    if (empty($subject)) {
      // Set default subject if empty (string)
      $subject = $this->t('@type: @title')->render();
    }

    $subject = $this->t($subject, [
      '@type' => $type,
      '@title' => $title,
    ]);

    // Decode subject to convert to special characters.
    $subject = htmlspecialchars_decode($subject);

    // Sender settings.
    $sender_from_name = $connection['sender_from_name'];
    $sender_from_email = $connection['sender_from_email'];
    $sender_replyto_email = $connection['sender_replyto_email'];

    if (empty($sender_replyto_email)) {
      $sender_replyto_email = $sender_from_email;
    }

    // Get Constant Contact API.
    $api = \Drupal::service('constant_contact_mailout.api');

    // Create date.
    $datetime = new \DateTime('now');

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
          'html_content' => $template,
        ],
      ],
    ];

    // Create an email campaign and campaign activities.
    // Returns primary email and email campaign activity id.
    $email_campaign_response = $api->createEmailCampaigns($connection, $email_campaign_data);

    if (!empty($email_campaign_response) && !empty($email_campaign_response['campaign_id'])) {
      // Get email campaign data.
      $email_campaign_acitivity_data = $email_campaign_data['email_campaign_activities'][0];

      // Update the email campaign activity and add recipients.
      $campaign_activity_id = $email_campaign_response['campaign_activities'][0]['campaign_activity_id'];

      // Add campaign activity id, role and contact list ids.
      $email_campaign_acitivity_data['campaign_activity_id'] = $campaign_activity_id;
      $email_campaign_acitivity_data['role'] = $email_campaign_response['campaign_activities'][0]['role'];
      $email_campaign_acitivity_data['contact_list_ids'] = array_values($contact_list_ids);

      $email_campaign_activity_response = $api->updateEmailCampaignActivities($connection, $campaign_activity_id, $email_campaign_acitivity_data);

      if (!empty($email_campaign_activity_response) && !empty($email_campaign_activity_response['campaign_activity_id'])) {
        $schedule_data = NULL;

        // Schedule the email campaign activity.
        if (!empty($this->sendnow)) {
          $schedule_data = [
            'scheduled_date' => 0,
          ];

          $message = 'The "@subject" has been sent now to @contact_list_names contact lists.';
        }
        elseif (!empty($this->sendlater) && !empty($this->sendlater_datetime)) {
          $datetime = new \DateTime($this->sendlater_datetime['object']->__toString());

          $schedule_data = [
            'scheduled_date' => $datetime->format('Y-m-dTH:i:sZ'),
          ];

          $message = 'The "@subject" will be sent on @date to @contact_list_names contact lists.';
        }

        $email_campaign_activity_schedule_response = $api->scheduleEmailCampaignActivities($connection, $campaign_activity_id, $schedule_data);

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

          $message = $this->t($message, [
            '@subject' => $subject,
            '@date' => $datetime->format('F j, Y g:ia'),
            '@contact_list_names' => implode(', ', $contact_list_names),
          ]);

          // @todo Get total number of subscribers.
          \Drupal::logger('constant_contact_mailout')->notice($message);
          \Drupal::messenger()->addStatus($message);
        }
        else {
          $errors = $api->processErrorResponse($email_campaign_activity_schedule_response);

          $message = t('Constant Contact: @errors', [
            '@errors' => implode('. ', $errors),
          ]);

          \Drupal::logger('constant_contact_mailout')->error($message);
          \Drupal::messenger()->addError($message);
        }
      }
      else {
        $errors = $api->processErrorResponse($email_campaign_activity_response);

        $message = t('Constant Contact: @errors', [
          '@errors' => implode('. ', $errors),
        ]);

        \Drupal::logger('constant_contact_mailout')->error($message);
        \Drupal::messenger()->addError($message);
      }
    }
    else {
      $errors = $api->processErrorResponse($email_campaign_response);

      $message = t('Constant Contact: @errors', [
        '@errors' => implode('. ', $errors),
      ]);

      \Drupal::logger('constant_contact_mailout')->error($message);
      \Drupal::messenger()->addError($message);
    }
  }

}
