<?php
require_once 'htmlpurifier/HTMLPurifier.auto.php';

/**
 * - Parses non-wanted HTML tags
 * - Wordwraps too long sentences
 * - Adds a href tags to URLs
 */
function linkify ($str, $wordwrap = 80)
{
  // Line breaks to space
  $str = preg_replace("@\r\n|\n\r|\n|\r@i", ' ', $str);

  // Remove too many spaces to one
  $str = preg_replace("/\s+/", ' ', $str);
  // Add http:// to words starting with "www."
  $str = preg_replace("@(?!\w+?://)(www\.[^\s]+)@i", "http://$1", $str);


  $htmlent = new Zend_Filter_HtmlEntities(ENT_COMPAT, 'UTF-8');
  // Convert to HTML
  $str = $htmlent->filter($str);


  $purifierconfig = HTMLPurifier_Config::createDefault();

  $purifierconfig->set('Core', 'Encoding', 'UTF-8');
  $purifierconfig->set('HTML', 'Doctype', 'XHTML 1.1');
  $purifierconfig->set('Cache', 'SerializerPath', '/tmp');
  $purifierconfig->set('AutoFormat', 'Linkify', true);
  $purifierconfig->set('AutoFormat', 'DisplayLinkURI', true);
  $purifierconfig->set('AutoFormat', 'RemoveEmpty', true);

  $purifier = new HTMLPurifier($purifierconfig);

  $str = $purifier->purify($str);
  
  $words = explode(' ', $str);
  
  for ($i=0; $i < count($words); $i++)
  {
    $word = $words[$i];

    // Too long word
    if (strlen($word) >= $wordwrap)
    {
      $chars = str_split($word);
      
      $add_space = true;
      $counter = 0;

      for ($c=0; $c < count($chars); $c++)
      {
        // HTML tag begins
        if ($chars[$c] == '<')
        {
          $add_space = false;
          $counter = 0;
          continue;
        }

        // HTML tag ends
        if ($chars[$c] == '>')
        {
          $add_space = true;
        }

        if ($add_space)
        {
          $counter++;

          // Too long! Add space
          if ($counter == $wordwrap)
          {
            $chars[$c] = $chars[$c] . ' ';
            $counter = 0;
          }

        }

      } // /for
      
      // Array to string
      $words[$i] = implode('', $chars);

    } // /if

  } // /for
  
  // Words array to string
  $str = implode(' ', $words);

  return $str;
}