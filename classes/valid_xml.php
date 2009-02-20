<?php
/**
 * Validate XML
 */
class CRM_Validate_XML extends Zend_Validate_Abstract
{
  const NOT_VALID_XML = 'notValidXML';  

  protected $_messageTemplates = array(
    self::NOT_VALID_XML => 'Malformed XML'  
  );
  
  public function isValid($value)
  {
    $this->_setValue($value);

    $isValid = true;

    if (!empty($value))
    {
      $dom = new DOMDocument('1.0', 'UTF-8');

      if ($dom->loadXML('<div>' . $value . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR) === false)
      {
        $this->_error(self::NOT_VALID_XML);
        $isValid = false;
      }
    }

    return $isValid;
  }
} // /class
