<?php
// Helper for extracting error strings from Zend_Validate_* classes for easier translating 

$zend_library = "./../library";
set_include_path($zend_library);

$path = $zend_library . '/Zend/Validate';
$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

$messages = array();
foreach($objects as $name => $object)
{
  if(strstr($name, ".svn"))
  {
    // Skip SVN directories
    continue;
  }

  require_once($name);

  $class = strtr(preg_replace('#.*(Zend/Validate/.*)\.php$#', '\1', $name), '/', '_');

  if (class_exists($class))
  {
    $reflection = new ReflectionClass($class);
    $consts = array_flip($reflection->getConstants());
    
    if (empty($consts)) 
    {
      continue;
    }

    if ($reflection->hasProperty('_messageTemplates'))
    {
      $props = $reflection->getDefaultProperties();
      $props['_messageVariables']['value'] = true;
      
      foreach($consts as $key => $val)
      {
        if(isset($props['_messageTemplates'][$key]))
        {
          $messages["$class::$val"] = $props['_messageTemplates'][$key];
        }
      }
    }
  }

}

echo "\$form_translate = array (\n";

$str = null;
foreach($messages as $key => $val)
{
  $str .= "  $key => \$tr->_(\"$val\"),\n";
}
$str = substr($str, 0, -2);
echo $str;
echo "\n);";
