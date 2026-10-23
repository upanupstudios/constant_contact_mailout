<?php

namespace Drupal\constant_contact_mailout\Plugin\Field\FieldType;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormStateInterface;

/**
 * Item list for the 'constant_contact_mailout' field type.
 */
class ConstantContactMailoutFieldItemList extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormSubmit(array $element, array &$form, FormStateInterface $form_state) {
    $default_value = [];

    // Only keep the keys described in the field.value schema. The widget also
    // submits scheduling and contact list values that cannot be stored.
    foreach (parent::defaultValuesFormSubmit($element, $form, $form_state) as $delta => $value) {
      $default_value[$delta] = [
        'contact_list_id' => $value['contact_list_id'] ?? NULL,
        'sendnow' => !empty($value['sendnow']),
      ];
    }

    return $default_value;
  }

  /**
   * {@inheritdoc}
   */
  public static function processDefaultValue($default_value, FieldableEntityInterface $entity, FieldDefinitionInterface $definition) {
    $default_value = parent::processDefaultValue($default_value, $entity, $definition);

    // The "send now" default only pre-checks the widget checkbox. It must not
    // become an item value, or entities created in code (e.g. Feeds) would
    // send a mailout when published.
    foreach ($default_value as &$value) {
      unset($value['sendnow']);
    }

    return $default_value;
  }

}
