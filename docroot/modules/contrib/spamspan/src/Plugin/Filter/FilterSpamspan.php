<?php

/**
 * @file
 * Contains \Drupal\spamspan\Plugin\Filter\FilterSpamSpan.
 *
 * Scan text and replace email addresses with span tags
 *
 * We are aiming to replace emails with code like this:
 *   <span class="spamspan">
 *     <span class="u">user</span>
 *     [at]
 *     <span class="d">example [dot] com</span>
 *     <span class="t">tag contents</span>
 *   </span>
 *
 */

namespace Drupal\spamspan\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\Component\Utility\Xss;
use Drupal\spamspan\Plugin\SpamspanSettingsFormTrait;

/**
 * Provides a filter to obfuscate email addresses.
 *
 * @Filter(
 *   id = "filter_spamspan",
 *   title = @Translation("SpamSpan email address encoding filter"),
 *   description = @Translation("Attempt to hide email addresses from spam-bots."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   settings = {
 *     "spamspan_at" = " [at] ",
 *     "spamspan_use_graphic" = 0,
 *     "spamspan_dot_enable" = 0,
 *     "spamspan_dot" = " [dot] ",
 *     "spamspan_use_form" = 0,
 *     "spamspan_form_pattern" = "<a href=""%url?goto=%email"">%displaytext</a>",
 *     "spamspan_form_default_url" = "contact",
 *     "spamspan_form_default_displaytext" = "contact form"
 *   }
 * )
 */

define('SPAMSPAN_PATTERN_MAIN',
  "([-\.\~\'\!\#\$\%\&\+\/\*\=\?\^\_\`\{\|\}\w\+^@]+)"
  .'@'                # @
  .'((?:'             # Group 2
  .'[-\w]+\.'         # one or more letters or dashes followed by a dot.
  .')+'               # The whole thing one or more times
  .'[A-Z]{2,63}'      # with between 2 and 63 letters at the end (NB new TLDs)
  .')');

define('SPAMSPAN_PATTERN_EMAIL_BARE',
  '!'. SPAMSPAN_PATTERN_MAIN .'!ix'
);
define('SPAMSPAN_PATTERN_EMAIL_WITH_OPTIONS',
  '!'. SPAMSPAN_PATTERN_MAIN .'\[(.*?)\]!ix'
);
define('SPAMSPAN_PATTERN_MAILTO',
  '!<a\s+'                                            # opening <a and spaces
  ."((?:\w+\s*=\s*)(?:\w+|\"[^\"]*\"|'[^']*'))*?"     # any attributes
  .'\s*'                                              # whitespace
  ."href\s*=\s*(['\"])(mailto:"                       # the href attribute
  . SPAMSPAN_PATTERN_MAIN                                # the email address
  ."(?:\?[A-Za-z0-9_= %\.\-\~\_\&;\!\*\(\)\\'#&]*)?)" # an optional ? followed
  # by a query string. NB
  # we allow spaces here,
  # even though strictly
  # they should be URL
  # encoded
  .'\\2'                                              # the relevant quote
  # character
  ."((?:\s+\w+\s*=\s*)(?:\w+|\"[^\"]*\"|'[^']*'))*?"  # any more attributes
  .'>'                                                # end of the first tag
  .'(.*?)'                                            # tag contents.  NB this
  # will not work properly
  # if there is a nested
  # <a>, but this is not
  # valid xhtml anyway.
  .'</a>'                                             # closing tag
  .'!ix'
);

class FilterSpamspan extends FilterBase {

  use SpamspanSettingsFormTrait;

  /**
   * Set up a regex constant to split an email address into name and domain
   * parts. The following pattern is not perfect (who is?), but is intended to
   * intercept things which look like email addresses.  It is not intended to
   * determine if an address is valid.  It will not intercept addresses with
   * quoted local parts.
   *
   * @constant string PATTERN_EMAIL
   */
  const PATTERN_MAIN =
    # Group 1 - Match the name part - dash, dot or special characters.
    SPAMSPAN_PATTERN_MAIN;

  // Top and tail the email regexp it so that it is case insensitive and
  // ignores whitespace.
  const PATTERN_EMAIL_BARE = SPAMSPAN_PATTERN_EMAIL_BARE;

  // options such as subject or body
  // e.g. <a href="mailto:email@example.com?subject=Hi there!&body=Dear Sir">
  const PATTERN_EMAIL_WITH_OPTIONS = SPAMSPAN_PATTERN_EMAIL_WITH_OPTIONS;

  // Next set up a regex for mailto: URLs.
  // - see http://www.faqs.org/rfcs/rfc2368.html
  // This captures the whole mailto: URL into the second group,
  // the name into the third group and the domain into
  // the fourth. The tag contents go into the fifth.

  const PATTERN_MAILTO = SPAMSPAN_PATTERN_MAILTO;

  // these will help us deal with inline images, which if very large
  // break the preg_match and preg_replace
  const PATTERN_IMG_INLINE = '/data\:(?:.+?)base64(?:.+?)["|\']/';
  const PATTERN_IMG_PLACEHOLDER = '__spamspan_img_placeholder__';

  // if text was altered by this filter, set variable below to TRUE
  private $textAltered = FALSE;

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    return $this->t('Each email address will be obfuscated in a human readable fashion or, if JavaScript is enabled, replaced with a spam resistent clickable link. Email addresses will get the default web form unless specified. If replacement text (a persons name) is required a webform is also required. Separate each part with the "|" pipe symbol. Replace spaces in names with "_".');
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {

    // HTML image tags need to be handled separately, as they may contain base64
    // encoded images slowing down the email regex function.
    // Therefore, remove all image contents and add them back later.
    // See https://drupal.org/node/1243042 for details.
    $images = array(array());
    preg_match_all(self::PATTERN_IMG_INLINE, $text, $images);
    $text = preg_replace(self::PATTERN_IMG_INLINE, self::PATTERN_IMG_PLACEHOLDER, $text);

    // Now we can convert all mailto URLs
    $text = preg_replace_callback(self::PATTERN_MAILTO, array($this, 'callbackMailto'), $text);
    // all bare email addresses with optional formatting information
    $text = preg_replace_callback(self::PATTERN_EMAIL_WITH_OPTIONS, array($this, 'callbackEmailAddressesWithOptions'), $text);
    // and finally, all bare email addresses
    $text = preg_replace_callback(self::PATTERN_EMAIL_BARE, array($this, 'callbackBareEmailAddresses'), $text);

    // Revert back to the original image contents.
    foreach ($images[0] as $image) {
      $text = preg_replace('/'. self::PATTERN_IMG_PLACEHOLDER .'/', $image, $text, 1);
    }

    $result = new FilterProcessResult($text);

    if ($this->textAltered) {
      $result->addAttachments(array(
        'library' => array(
          'spamspan/obfuscate',
        ),
      ));

      if ($this->settings['spamspan_use_graphic']) {
        $result->addAttachments(array(
          'library' => array(
            'spamspan/atsign',
          ),
        ));
      }
    }

    return $result;
  }

  /**
   * The callback functions for preg_replace_callback
   *
   * Replace an email addresses which has been found with the appropriate
   * <span> tags
   *
   * @param $matches
   *  An array containing parts of an email address or mailto: URL.
   * @return
   *  The span with which to replace the email address
   */
  public function callbackMailto($matches) {
    // take the mailto: URL in $matches[3] and split the query string
    // into its component parts, putting them in $headers as
    // [0]=>"header=contents" etc.  We cannot use parse_str because
    // the query string might contain dots.

    // Single quote can be encoded as &#039; which breaks parse_url
    // Replace it back to a single quote which is perfectly valid
    $matches[3] = str_replace("&#039;", '\'', $matches[3]);
    $query = parse_url($matches[3], PHP_URL_QUERY);
    $query = str_replace('&amp;', '&', $query);
    $headers = preg_split('/[&;]/', $query);
    // if no matches, $headers[0] will be set to '' so $headers must be reset
    if ($headers[0] == '') {
      $headers = array();
    }

    // take all <a> attributes except the href and put them into custom $vars
    $vars = $attributes = array();
    // before href
    if (!empty($matches[1])) {
      $matches[1] = trim($matches[1]);
      $attributes[] = $matches[1];
    }
    // after href
    if (!empty($matches[6])) {
      $matches[6] = trim($matches[6]);
      $attributes[] = $matches[6];
    }
    if (count($attributes)) {
      $vars['extra_attributes'] = implode(' ', $attributes);
    }

    return $this->output($matches[4], $matches[5], $matches[7], $headers, $vars);

  }

  public function callbackEmailAddressesWithOptions($matches) {
    $vars = array();
    if (!empty($matches[3])) {
      $options = explode('|', $matches[3]);
      if (!empty($options[0])) {
        $custom_form_url = trim($options[0]);
        if (!empty($custom_form_url)) {
          $vars['custom_form_url'] = $custom_form_url;
        }
      }
      if (!empty($options[1])) {
        $custom_displaytext = trim($options[1]);
        if (!empty($custom_displaytext)) {
          $vars['custom_displaytext'] = $custom_displaytext;
        }
      }
    }
    return $this->output($matches[1], $matches[2], '', '', $vars);
  }

  public function callbackBareEmailAddresses($matches) {
    return $this->output($matches[1], $matches[2]);
  }

  /**
   * A helper function for the callbacks
   *
   * Replace an email addresses which has been found with the appropriate
   * <span> tags
   *
   * @param $name
   *  The user name
   * @param $domain
   *  The email domain
   * @param $contents
   *  The contents of any <a> tag
   * @param $headers
   *  The email headers extracted from a mailto: URL
   * @param $vars
   *  Optional parameters to be implemented later. (Used only when spamspan_use_form = true)
   * @return
   *  The span with which to replace the email address
   */
  private function output($name, $domain, $contents = '', $headers = array(), $vars = array()) {
    // processing for forms
    if (!empty($this->settings['spamspan_use_form'])) {
      $email = urlencode(base64_encode($name . '@' . $domain));

      //put in the defaults if nothing set
      if (empty($vars['custom_form_url'])) {
        $vars['custom_form_url'] = $this->settings['spamspan_form_default_url'];
      }
      if (empty($vars['custom_displaytext'])) {
        $vars['custom_displaytext'] = $this->t($this->settings['spamspan_form_default_displaytext']);
      }
      $vars['custom_form_url'] = strip_tags($vars['custom_form_url']);
      $vars['custom_displaytext'] = strip_tags($vars['custom_displaytext']);

      $url_parts = parse_url($vars['custom_form_url']);
      if (!$url_parts) {
        $vars['custom_form_url'] = '';
      }
      else if (empty($url_parts['host'])) {
        $vars['custom_form_url'] = base_path() . trim($vars['custom_form_url'], '/');
      }

      $replace = ['%url' => $vars['custom_form_url'], '%displaytext' => $vars['custom_displaytext'], '%email' => $email];

      $output = strtr($this->settings['spamspan_form_pattern'], $replace);
      return $output;
    }

    $at = $this->settings['spamspan_at'];
    if ($this->settings['spamspan_use_graphic']) {
      $render_at = array('#theme' => 'spamspan_at_sign', '#settings' => $this->settings);
      $at = \Drupal::service('renderer')->renderRoot($render_at);
    }

    if ($this->settings['spamspan_dot_enable']) {
      // Replace .'s in the address with [dot]
      $name = str_replace('.', '<span class="o">' . $this->settings['spamspan_dot'] . '</span>', $name);
      $domain = str_replace('.', '<span class="o">' . $this->settings['spamspan_dot'] . '</span>', $domain);
    }
    $output = '<span class="u">' . $name . '</span>' . $at . '<span class="d">' . $domain . '</span>';

    // if there are headers, include them as eg (subject: xxx, cc: zzz)
    // we replace the = in the headers by ": " to look nicer
    if (count($headers)) {
      foreach ($headers as $key => $header) {
        // check if header is already urlencoded, if not, encode it
        if ($header == rawurldecode($header)) {
          $header = rawurlencode($header);
          // replace the first = sign
          $header = preg_replace('/%3D/', ': ', $header, 1);
        }
        else {
          $header = str_replace('=', ': ', $header);
        }
        $headers[$key] = $header;
      }
      $output .= '<span class="h"> ('. Html::escape(implode(', ', $headers)) .') </span>';
    }

    // If there are tag contents, include them, between round brackets.
    // Remove emails from the tag contents, otherwise the tag contents are themselves
    // converted into a spamspan, with undesirable consequences - see bug #305464.`
    if (!empty($contents)) {
      $contents = preg_replace(self::PATTERN_EMAIL_BARE, '', $contents);

      // remove anything except certain inline elements, just in case.  NB nested
      // <a> elements are illegal. <img> needs to be here to allow for graphic @
      // !-- is allowed because of _filter_spamspan_escape_images
      $contents = Xss::filter($contents, ['em', 'strong', 'cite', 'b', 'i', 'code', 'span', 'img', '!--']);

      if (!empty($contents)) {
        $output .= '<span class="t"> (' . $contents . ')</span>';
      }
    }

    // put in the extra <a> attributes
    // this has to come after the xss filter, since we want comment tags preserved
    if (!empty($vars['extra_attributes'])) {
      $output .= '<span class="e"><!--'. strip_tags($vars['extra_attributes']) .'--></span>';
    }

    $output = '<span class="spamspan">' . $output . '</span>';

    if (!$this->textAltered) {
      $this->textAltered = TRUE;
    }

    return $output;
  }

}
