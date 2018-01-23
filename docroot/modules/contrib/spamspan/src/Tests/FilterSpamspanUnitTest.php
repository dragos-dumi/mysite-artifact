<?php


namespace Drupal\spamspan\Tests;

use Drupal\filter\FilterPluginCollection;
use Drupal\simpletest\KernelTestBase;

/**
 * Tests spamspan filter.
 *
 * @group spamspan
 */
class FilterSpamspanUnitTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('system', 'filter', 'spamspan');

  /**
   * @var \Drupal\filter\Plugin\FilterInterface[]
   */
  protected $filters;

  protected $spamspanFilter;

  protected $spamspanFilterForm;

  protected $spamspanFilterAtDot;

  protected $base64Image;

  protected function setUp() {
    parent::setUp();
    $this->installConfig(array('system'));

    $manager = $this->container->get('plugin.manager.filter');
    $bag = new FilterPluginCollection($manager, array());
    $this->filters = $bag->getAll();
    $this->spamspanFilter = $this->filters['filter_spamspan'];

    // spamspan filter that is set to use contact form
    $configuration = $manager->getDefinition('filter_spamspan');
    $configuration['settings'] = array('spamspan_use_form' => 1) + $configuration['settings'];
    $this->spamspanFilterForm = $manager->createInstance('filter_spamspan', $configuration);

    // spamspan filter that is set to use graphic at and dot enabled
    $configuration['settings'] = array('spamspan_use_form' => 0, 'spamspan_use_graphic' => 1, 'spamspan_dot_enable' => 1) + $configuration['settings'];
    $this->spamspanFilterAtDot = $manager->createInstance('filter_spamspan', $configuration);

    // read the test image from the file provided
    $this->base64Image = file_get_contents(drupal_get_path('module', 'spamspan') .'/src/Tests/base64image.txt');

  }

  // wrapper functions that conveniently wraps some text around main test subject and then asserts
  private function wrappedAssert($filter, $input, $shouldbe, $prefix = '', $suffix = '', $message = '') {
    $output = $filter->process($prefix . $input . $suffix, 'und')->getProcessedText();

    if (empty($message)) {
      $this->assertIdentical($prefix . $shouldbe . $suffix, $output);
    }
    else {
      $this->assertIdentical($prefix . $shouldbe . $suffix, $output, $message);
    }
  }

  private function variatedAssert($filter, $input, $shouldbe) {
    // Test for bare email;
    $this->wrappedAssert($filter, $input, $shouldbe);
    // Test for email with text at the start
    $this->wrappedAssert($filter, $input, $shouldbe, 'some text at the start ');
    // Test for email with text at the end
    $this->wrappedAssert($filter, $input, $shouldbe, '', ' some text at the end');
    // Test for email with text at the start and end
    $this->wrappedAssert($filter, $input, $shouldbe, 'some text at the start ', ' some text at the end');
    // Test for email with tags at the start and end
    $this->wrappedAssert($filter, $input, $shouldbe, '<p>', '</p>');
    // Test for email with trailing commas
    $this->wrappedAssert($filter, $input, $shouldbe, 'some text at the start ', ', next clause.');
    // Test for email with trailing full stop
    $this->wrappedAssert($filter, $input, $shouldbe, 'some text at the start ', '. next sentence.');
    // Test for email with preceding tag, and no closing tag
    $this->wrappedAssert($filter, $input, $shouldbe, '<dt>');
    // Test for brackets
    $this->wrappedAssert($filter, $input, $shouldbe, '(', ')');
    // Test for angular brackets
    $this->wrappedAssert($filter, $input, $shouldbe, '<', '>');
    // Test for newlines
    $this->wrappedAssert($filter, $input, $shouldbe, "\n", "\n");
    // Test for spaces
    $this->wrappedAssert($filter, $input, $shouldbe, ' ', ' ');
    // Test base64image
    $this->wrappedAssert($filter, $input, $shouldbe, $this->base64Image, $this->base64Image, 'Base64 encoded images were handled correctly.');
  }


  /**
   * Tests the align filter.
   */
  function testSpamSpanFilter() {

    // test that strings without emails a passed unchanged
    $noemails = array(
      'no email here',
      'oneword',
      '',
      'notan@email',
      'notan@email either',
      'some text and notan.email@something here',
    );

    foreach ($noemails as $input) {
      $this->variatedAssert($this->spamspanFilter, $input, $input);
    }

    // a list of addresses, together with what they should look like
    $emails = array(
      'user@example.com' =>
        '<span class="spamspan"><span class="u">user</span> [at] <span class="d">example.com</span></span>',

      'user@example.co.uk' =>
        '<span class="spamspan"><span class="u">user</span> [at] <span class="d">example.co.uk</span></span>',

      'user@example.somenewlongtld' =>
        '<span class="spamspan"><span class="u">user</span> [at] <span class="d">example.somenewlongtld</span></span>',

      'user.user@example.com' =>
        '<span class="spamspan"><span class="u">user.user</span> [at] <span class="d">example.com</span></span>',

      'user\'user@example.com' =>
        '<span class="spamspan"><span class="u">user\'user</span> [at] <span class="d">example.com</span></span>',

      'user-user@example.com' =>
        '<span class="spamspan"><span class="u">user-user</span> [at] <span class="d">example.com</span></span>',

      'user_user@example.com' =>
        '<span class="spamspan"><span class="u">user_user</span> [at] <span class="d">example.com</span></span>',

      'user+user@example.com' =>
        '<span class="spamspan"><span class="u">user+user</span> [at] <span class="d">example.com</span></span>',

      '<a href="mailto:email@example.com"></a>' =>
        '<span class="spamspan"><span class="u">email</span> [at] <span class="d">example.com</span></span>',

      '<a href="mailto:email@example.com"><img src="/core/misc/favicon.ico"></a>' =>
        '<span class="spamspan"><span class="u">email</span> [at] <span class="d">example.com</span><span class="t"> (<img src="/core/misc/favicon.ico">)</span></span>',

      '<a href="mailto:email@example.com?subject=Hi there!&body=Dear Sir">some text</a>' =>
        '<span class="spamspan"><span class="u">email</span> [at] <span class="d">example.com</span><span class="h"> (subject: Hi%20there%21, body: Dear%20Sir) </span><span class="t"> (some text)</span></span>',

      '<a href="mailto:email@example.com">The email@example.com should not show and neither email2@example.me</a>' =>
        '<span class="spamspan"><span class="u">email</span> [at] <span class="d">example.com</span><span class="t"> (The  should not show and neither )</span></span>',

      '<a class="someclass" href="mailto:email@example.com" id="someid"></a>' =>
        '<span class="spamspan"><span class="u">email</span> [at] <span class="d">example.com</span><span class="e"><!--class="someclass" id="someid"--></span></span>',

      '<a href="mailto:email@example.com?subject=Message%20Subject%2C%20nasty%20%22%20%3Cchars%3F%3E&amp;body=%22This%20is%20a%20message%20body!%20%3C%20%3E%20%22%3F%0A%0A!%22%C2%A3%24%25%5E%26*()%3A%40~%3B%23%3C%3E%3F%2C.%2F%20%5B%5D%20%7B%7D%20-%3D%20_%2B">some text</a>' =>
        '<span class="spamspan"><span class="u">email</span> [at] <span class="d">example.com</span><span class="h"> (subject: Message%20Subject%2C%20nasty%20%22%20%3Cchars%3F%3E, body: %22This%20is%20a%20message%20body!%20%3C%20%3E%20%22%3F%0A%0A!%22%C2%A3%24%25%5E%26*()%3A%40~%3B%23%3C%3E%3F%2C.%2F%20%5B%5D%20%7B%7D%20-%3D%20_%2B) </span><span class="t"> (some text)</span></span>',

      '<a href="mailto:email@example.com?subject=Hi there!&body=Dear\'Sir">some text</a>' =>
        '<span class="spamspan"><span class="u">email</span> [at] <span class="d">example.com</span><span class="h"> (subject: Hi%20there%21, body: Dear%27Sir) </span><span class="t"> (some text)</span></span>',

    );

    foreach ($emails as $input => $shouldbe) {
      $this->variatedAssert($this->spamspanFilter, $input, $shouldbe);
    }

    $basepath = base_path();

    // use form tests
    $emails = array(
      'user@example.com' =>
        '<a href="'. $basepath .'contact?goto=dXNlckBleGFtcGxlLmNvbQ%3D%3D">contact form</a>',

      'user@example.co.uk[mycontactform]' =>
        '<a href="'. $basepath .'mycontactform?goto=dXNlckBleGFtcGxlLmNvLnVr">contact form</a>',

      'user@example.com[http://google.com]' =>
        '<a href="http://google.com?goto=dXNlckBleGFtcGxlLmNvbQ%3D%3D">contact form</a>',

      'user@example.museum[mycontactform|Contact me using this form]' =>
        '<a href="'. $basepath .'mycontactform?goto=dXNlckBleGFtcGxlLm11c2V1bQ%3D%3D">Contact me using this form</a>',
    );

    foreach ($emails as $input => $shouldbe) {
      $this->variatedAssert($this->spamspanFilterForm, $input, $shouldbe);
    }

    // graphical at and [dot]
    $emails = array(
      'user@example.com' =>
        '<span class="spamspan"><span class="u">user</span><img class="spamspan-image" alt="at" src="'. base_path() . drupal_get_path('module', 'spamspan') .'/image.gif" /><span class="d">example<span class="o"> [dot] </span>com</span></span>',
    );

    foreach ($emails as $input => $shouldbe) {
      $this->variatedAssert($this->spamspanFilterAtDot, $input, $shouldbe);
    }

    // Test the spamspan.js being attached
    $attached_library = array(
      'library' => array(
        'spamspan/obfuscate',
      ),
    );
    $output = $this->spamspanFilter->process('email@example.com', 'und');
    $this->assertIdentical($attached_library, $output->getAttachments());
  }

}
