<?php

namespace Drupal\constant_contact_mailout\Utility;

use Drupal\Core\Language\LanguageInterface;

/**
 * Provides helper to operate on strings.
 */
class TextHelper {

  /**
   * Converts text to mahine name.
   *
   * @param string $string
   *   String to be converted.
   *
   * @return string
   *   String with converted to machine name.
   */
  public static function textToMachineName($string) {
    $transliterated = \Drupal::transliteration()->transliterate($string, LanguageInterface::LANGCODE_DEFAULT, '_');
    $transliterated = mb_strtolower($transliterated);
    $transliterated = preg_replace('@[^a-z0-9_.]+@', '_', $transliterated);

    return $transliterated;
  }

}
