<?php
/**
 * Base class for ALL forms
 */
class comicForm extends Zend_Form
{
  /**
   * Add asterisk prefix to required fields?
   * @var bool
   */
  public $add_asterisk = true;

  public function init()
  {
    // Enable Dojo
    Zend_Dojo::enableForm($this);

    // Dojo-enable all sub forms:
    foreach ($this->getSubForms() as $subForm)
    {
      Zend_Dojo::enableForm($subForm);
    }
    
    // XHTML 1.1
    // @FIXME
    // Dijit editor doesn't like this for some reason??
    //$this->setAttrib('name', 'zfceform');

  } // /function

  /**
   * add new display group (fieldset)
   */
  public function addDisplayGroup(array $elements, $name, $options = null)
  {
    parent::addDisplayGroup($elements, $name, $options);
  } // /function

  /**
   * add new form element (input, textarea, ..)
   */
  public function addElement($element, $name = null, $options = null)
  {
    parent::addElement($element, $name, $options);

    if ($element instanceof Zend_Form_Element)
    {
      // Add asterisk prefix
      if ($element->isRequired() && $this->add_asterisk)
      {
        $element->setLabel("* " . $element->getLabel());
      }
    }

    if ($element instanceof Zend_Form_Element_Submit)
    {
      $submit_decorator = array(
        array('ViewHelper'),
        array('Description'),
        array('HtmlTag',
          array('tag' => 'dd', 'class' => 'form-dd-submit')
        )
      );

      $element->setDecorators($submit_decorator);

    }

    if ($element instanceof Zend_Form_Element_Textarea)
    {
      $textarea_decorator = array(
        array('ViewHelper'),
        array('Description'),
        array('HtmlTag',
          array('tag' => 'dd', 'class' => 'form-dd-textarea')
        )
      );

      $element->setDecorators($textarea_decorator);

    }

  } // /function

} // /class
