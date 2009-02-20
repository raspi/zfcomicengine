<?php
class Zend_Filter_MBStringTrim implements Zend_Filter_Interface
{
    /**
     * @var string|null
     */
    protected $_charList;

    /**
     * @param  string $charList
     * @return void
     */
    public function __construct($charList = null)
    {
        $this->_charList = $charList;
    }

    /**
     * @return string|null
     */
    public function getCharList()
    {
        return $this->_charList;
    }

    /**
     * @param  string|null $charList
     * @return Zend_Filter_StringTrim Provides a fluent interface
     */
    public function setCharList($charList)
    {
        $this->_charList = $charList;
        return $this;
    }

    /**
     * @param  string $value
     * @return string
     */
    public function filter($value)
    {
        if (null === $this->_charList) {
            return mb_trim((string) $value);
        } else {
            return mb_trim((string) $value, $this->_charList);
        }
    }
}
