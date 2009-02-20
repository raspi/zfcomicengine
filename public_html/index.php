<?php
define('SCRIPT_START_TIME', microtime(true), 0);

ini_set('magic_quotes_gpc', false);
ini_set('magic_quotes_runtime', false);

ini_set('default_charset', 'UTF-8');

// iconv
ini_set('iconv.input_encoding', 'UTF-8');
ini_set('iconv.output_encoding', 'UTF-8');
ini_set('iconv.internal_encoding', 'UTF-8');

function stripinputslashes(&$input)
{
  if (is_array($input))
  {
    foreach ($input as $key => $value)
    {
      switch (gettype($value))
      {
        default: break;
        case 'string':
          $input[$key] = stripinputslashes($value);
        break;
      }
    }
  }
  else
  {
    switch (gettype($input))
    {
      default: break;
      case 'string':
        $input = stripslashes($input);
      break;
    }
  }

  return true;
}

if (version_compare(phpversion(), 6) === -1)
{
  // get_magic_quotes_gpc is enabled, strip stupid slashes off
  if (function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc() === 1)
  {

    array_walk_recursive($_GET, 'stripinputslashes');

    array_walk_recursive($_REQUEST, 'stripinputslashes');

    if (isset($_POST))
    {
      array_walk_recursive($_POST, 'stripinputslashes');
    }

  }
}

// Load application
require_once dirname(__FILE__) . '/../application/bootstrap.php';
