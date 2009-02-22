<?php
class Admin_ComicController extends Controller
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
    $comics = new Comics();
    $comics->cache_result = false;

    $select = $comics->select();
    $select->from($comics, array('id', 'name', 'added'));
    $select->order(array('added DESC', 'id'));
    $result = $comics->fetchAll($select);

    $this->view->comics = array();
    if (!is_null($result))
    {
      $this->view->comics = $result->toArray();
    }

  } // /function

  /**
   * Add new comic
   */
  public function addAction()
  {
    $comic_files = new Comics();
    $comic_files->cache_result = false;

    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAttrib('enctype', Zend_Form::ENCTYPE_MULTIPART);
    $form->setAction($this->_request->getBaseUrl() . "/admin/comic/add/");

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Add comic'));

    $name = new Zend_Form_Element_Text('name');
    $name->setRequired(true);
    $name->setLabel($this->tr->_('Name'));
    $name->addFilter('StringTrim');
    $name->addValidator('StringLength', false, array(2));

    $idea = new Zend_Form_Element_Text('idea');
    $idea->setRequired(false);
    $idea->setLabel($this->tr->_('Idea'));
    $idea->addFilter('StringTrim');

    $file_count = 1;

    $file = new Zend_Form_Element_File('file');
    $file->setRequired(false);
    $file->setLabel($this->tr->_('Upload file:'));
    $file->addValidator('Count', false, array('min' => 1, 'max' => $file_count));
    $file->addValidator('Size', false, 104857600);
    $file->setMultiFile($file_count);

    $form->addElement($name);
    $form->addElement($idea);

    $form->addElement($file);

    $form->addElement($submit);

    // POST
    if ($this->getRequest()->isPost())
    {

      if ($form->isValid($_POST))
      {
        $upload = new Zend_File_Transfer_Adapter_Http();
        $upload->addValidator('IsImage', false, array('image/pjpeg', 'image/jpeg', 'image/png'));
        $upload->addValidator('Size', false, array('min' => 1));
        $upload->addValidator('Count', false, array('min' => 1, 'max' => $file_count));
        $upload->addValidator('FilesSize', false, array('min' => 1));
        $upload->setDestination('/tmp');

        $files = $upload->getFileInfo();

        foreach ($files as $file => $info)
        {
        
          if(!$upload->isUploaded($file))
          {
            $upload->receive($file);
          }

          if ($upload->isValid($file))
          {
            $values = $form->getValues();

            $fpath = $info['destination'] . '/' . $info['name'];
            @chmod($fpath, 0777);

            if (file_exists($fpath))
            {
              $up_mime = $info['type'];

              switch ($up_mime)
              {
                default: break;
                case 'image/pjpeg':
                  $up_mime = 'image/jpeg';
                break;
              }

              $md5 = md5_file($fpath);

              $select = $comic_files->select();
              $select->from($comic_files, array('c' => 'COUNT(id)'));
              $select->where('md5sum = ?', $md5);

              $has_file = $comic_files->fetchRow($select)->toArray();
              $has_file = $has_file['c'] == '1' ? true : false;
              unset($select);

              // No file
              if (!$has_file)
              {
                $basename = basename($info['name']);
                $fextension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

                // No file extension, skip file
                if (empty($fextension))
                {
                  continue;
                }

                list($width, $height, $type, $attr) = getimagesize($fpath);

                // Haetaan tiedoston sisältö
                $contents = file_get_contents($fpath);

                $insert = array(
                  'authorid' => $this->_auth->getIdentity()->id,
                  'name' => $values['name'],
                  'idea' => $values['idea'],
                  'filedata' => $contents,
                  'filemime' => $up_mime,
                  'filesize' => $info['size'],
                  'md5sum' => $md5,
                  'filename' => $info['name'],
                  'added' => new Zend_Db_Expr('NOW()'),
                  'published' => new Zend_Db_Expr('NOW()'),
                  'imgheight' => $height,
                  'imgwidth' => $width
                );

                $this->_db->beginTransaction();

                try
                {
                  $comic_files->insert($insert);

                  $this->_db->commit();
                  
                  return $this->_helper->redirector->gotoUrl("/comic");
                }
                catch (Exception $e)
                {
                  $this->_db->rollBack();
                  var_dump($e);
                  die;
                }

              }

              // Destroy file
              @unlink($fpath);

            }

          }
          else
          {
            var_dump($file);
            var_dump($info);
            die;
          }

        } // /foreach

        //return $this->_helper->redirector->gotoUrl("/admin/comic/");

      } // /Valid

    } // /POST

    $this->view->form = $form;
  } // /function

  /**
   * Edit comic
   */
  public function editAction()
  {
    $ID = $this->getRequest()->getParam('id', false);

    $comics = new Comics();
    $comics->cache_result = false;

    $select = $comics->select();
    $select->from($comics, array('name', 'idea'));
    $select->where('id = ?', $ID);
    $info = $comics->fetchRow($select)->toArray();

    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . "/admin/comic/edit/id/" . $ID);

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Edit comic'));

    $name = new Zend_Form_Element_Text('name');
    $name->setRequired(true);
    $name->setLabel($this->tr->_('Name'));
    $name->addFilter('StringTrim');
    $name->addValidator('StringLength', false, array(2));

    $idea = new Zend_Form_Element_Text('idea');
    $idea->setRequired(false);
    $idea->setLabel($this->tr->_('Idea'));
    $idea->addFilter('StringTrim');


    $form->addElement($name);
    $form->addElement($idea);

    $form->addElement($submit);
    
    $form->populate(
      array(
        'name' => $info['name'],
        'idea' => $info['idea'],
      )
    );

    // POST
    if ($this->getRequest()->isPost())
    {

      if ($form->isValid($_POST))
      {
        $values = $form->getValues();
      
        $update = array(
          'name' => $values['name'],
          'idea' => $values['idea'],
        );

        $this->_db->beginTransaction();

        try
        {
          $comics->update($update, $comics->getAdapter()->quoteInto('id = ?', $ID));

          $this->_db->commit();

          return $this->_helper->redirector->gotoUrl("/admin/comic/");

        }
        catch (Exception $e)
        {
          $this->_db->rollBack();
          echo $e->getMessage();
          var_dump($e);
          die;
        }

      } // /Valid

    } // /POST

    $this->view->form = $form;
  } // /function


  /**
   * Change comic image
   */
  public function changeImageAction()
  {
    $ID = $this->getRequest()->getParam('id', false);
    // @TODO
  } // /function
  
  /**
   * Comments
   */
  public function commentsAction()
  {
  } // /function

} // /class