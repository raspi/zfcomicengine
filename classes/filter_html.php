<?php
require_once 'htmlpurifier/HTMLPurifier.auto.php';

class Zend_Filter_PurifyHTML implements Zend_Filter_Interface
{
  /**
   * @return void
   */
  public function __construct()
  {
  }

  /**
   * @param  string $value
   * @return string
   */
  public function filter($value)
  {
    $config = HTMLPurifier_Config::createDefault();

    $config->set('Core', 'Encoding', 'UTF-8');
    $config->set('HTML', 'Doctype', 'XHTML 1.1');
    $config->set('Cache', 'SerializerPath', '/tmp');

    $purifier = new HTMLPurifier($config);

    $pure_html = $purifier->purify($value);

    // Remove first <br /> tags
    $pure_html = preg_replace("@^(?:<br />)+@i", '', $pure_html);

    // Remove last <br /> tags
    $pure_html = preg_replace("@(?:<br />)+$@i", '', $pure_html);
    
    return $pure_html;
  }
}
