<?php
class Admin_GuestbookController extends Controller
{
  public function preDispatch()
  {
    if (!$this->_auth->hasIdentity())
    {
      return $this->_redirect('/admin/index/login/');
    }
  } // /function

  public function indexAction()
  {
    $guestbook = new Guestbook();
    $guestbook->cache_result = false;

    $select = $guestbook->select();
    $select->from($guestbook, array('id', 'question', 'answer', 'added'));
    $select->order(array('added DESC', 'id'));
    $result = $guestbook->fetchAll($select);

    $this->view->gb = array();
    if (!is_null($result))
    {
      $this->view->gb = $result->toArray();
    }

  } // /function
  
  public function answerAction()
  {
    require_once 'filter_html.php';

    $guestbook = new Guestbook();
    $guestbook->cache_result = false;
    
    $id = $this->getRequest()->getParam('id', false);

    $select = $guestbook->select();
    $select->from($guestbook, array('name', 'email', 'question', 'answer', 'added'));
    $select->where('id = ?', $id);
    $info = $guestbook->fetchRow($select)->toArray();
    $this->view->gb = $info;

    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . '/admin/guestbook/answer/id/' . $id);

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Answer to guestbook entry'));

    $text = new Zend_Dojo_Form_Element_Editor('text');
    $text->setRequired(true);
    $text->setLabel($this->tr->_('Content'));
    $text->addFilter('StringTrim');
    $text->addFilter(new Zend_Filter_MBStringTrim());
    $text->addFilter(new Zend_Filter_PurifyHTML());
    $text->addValidator('NotEmpty', false);
    $text->addValidator('StringLength', false, array(5, 65535));
    //$text->addValidator(new validate_XML(), false);
    $text->setPlugins(array(
      'undo', 'redo', '|',
      'cut', 'copy', 'paste', '|',
      'removeFormat', 'bold', 'italic', 'underline', '|',
      'insertOrderedList', 'insertUnorderedList', '|',
      'createLink', 'unlink', 'formatBlock'
    ));

    $form->addElement($text);

    $form->addElement($submit);
    
    $form->populate(array(
      'text' => $info['answer']
    ));

    // Form POSTed
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();

        $update = array(
          'answer' => $values['text']
        );

        $this->_db->beginTransaction();

        try
        {
          $guestbook->update($update, $guestbook->getAdapter()->quoteInto('id = ?', $id));

          $this->_db->commit();

          return $this->_helper->redirector->gotoUrl("/admin/guestbook/index/");
        }
        catch (Exception $e)
        {
          $this->_db->rollBack();
          echo $e->getMessage();
          var_dump($e);
          die;
        }

      } // /if
    } // /if

    $this->view->form = $form;

  } // /function

  /**
   * Remove guestbook entry
   */
  public function removeAction()
  {
    $guestbook = new Guestbook();
    $guestbook->cache_result = false;
    
    $id = $this->getRequest()->getParam('id', false);

    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . '/admin/guestbook/remove/id/' . $id);

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Remove entry'));

    $form->addElement($submit);

    // Form POSTed
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {

        $this->_db->beginTransaction();

        try
        {
          $guestbook->delete($guestbook->getAdapter()->quoteInto('id = ?', $id));

          $this->_db->commit();

          return $this->_helper->redirector->gotoUrl("/admin/guestbook/index/");
        }
        catch (Exception $e)
        {
          $this->_db->rollBack();
          echo $e->getMessage();
          var_dump($e);
          die;
        }

      } // /if
    } // /if

    $this->view->form = $form;

  } // /function

} // /class