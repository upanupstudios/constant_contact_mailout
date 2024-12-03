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
   * @param string $separator
   *   Text to be used as the separator.
   *
   * @return string
   *   String with converted to machine name.
   */
  public static function textToMachineName($string, $separator = '_') {
    $transliterated = \Drupal::transliteration()->transliterate($string, LanguageInterface::LANGCODE_DEFAULT, $separator);
    $transliterated = mb_strtolower($transliterated);
    $transliterated = preg_replace('@[^a-z0-9_.]+@', $separator, $transliterated);

    return $transliterated;
  }

}
