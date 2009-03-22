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
    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', 'site');
    $this->view->dateformat = $config->dateformat;

    $comics = new VIEW_Comics();
    $comics->cache_result = false;

    $select = $comics->select();
    $select->from($comics, array('id', 'name', 'upublished'));
    $select->order(array('published DESC', 'id DESC'));
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
    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', 'site');
    $this->view->dateformat = $config->dateformat;

    $ID = $this->getRequest()->getParam('id', false);
    $this->view->id = $ID;

    $comics_raw = new Comics();
    $comics_raw->cache_result = false;

    $comics = new VIEW_Comics();
    $comics->cache_result = false;

    $authors = new Authors();
    $authors->cache_result = false;

    $select = $authors->select();
    $select->from($authors, array('id', 'name'));
    $select->order(array('name ASC'));
    $result = $authors->fetchAll($select)->toArray();
    
    $author_list = array();
    for($i=0; $i<count($result); $i++)
    {
      $author_list[$result[$i]['id']] = $result[$i]['name'];
    }

    $select = $comics->select();
    $select->from($comics, array('author', 'aid', 'name', 'idea', 'upublished', 'dates' => 'DATE(published)', 'times' => 'TIME(published)'));
    $select->where('id = ?', $ID);
    $info = $comics->fetchRow($select)->toArray();
    
    $this->view->info = $info;

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

    $pday = new Zend_Dojo_Form_Element_DateTextBox('date');
    $pday->setRequired(true);
    $pday->setLabel($this->tr->_('Day published'));
    $pday->addFilter('StringTrim');
    $pday->setSelector('date');
    $pday->setDatePattern('yyyy-MM-dd');

    $ptime = new Zend_Dojo_Form_Element_TimeTextBox('time');
    $ptime->setRequired(true);
    $ptime->setLabel($this->tr->_('Time published'));
    $ptime->addFilter('StringTrim');
    $ptime->setSelector('time');

    $authorid = new Zend_Form_Element_Select('authorid');
    $authorid->setRequired(true);
    $authorid->setLabel($this->tr->_('Author'));
    $authorid->addMultiOptions($author_list);


    $form->addElement($name);
    $form->addElement($idea);

    $form->addElement($pday);
    $form->addElement($ptime);

    $form->addElement($authorid);

    $form->addElement($submit);
    
    if (!$this->getRequest()->isPost())
    {
      $form->populate(
        array(
          'name' => $info['name'],
          'idea' => $info['idea'],
          'date' => $info['dates'],
          'time' => 'T' . $info['times'],
          'authorid' => $info['aid']
        )
      );
    }

    // POST
    if ($this->getRequest()->isPost())
    {

      if ($form->isValid($_POST))
      {
        $values = $form->getValues();
        
        $pub = new Zend_Date("{$values['date']} {$values['time']}");
      
        $update = array(
          'name' => $values['name'],
          'idea' => $values['idea'],
          'published' => new Zend_Db_Expr("FROM_UNIXTIME(" . $pub->getTimestamp() . ")"),
          'authorid' => $values['authorid']
        );
        
        $this->_db->beginTransaction();

        try
        {
          $comics_raw->update($update, $comics_raw->getAdapter()->quoteInto('id = ?', $ID));

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

    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . '/admin/comic/change-image/id/' . $id);

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('change image'));

    $form->addElement($submit);

    // Form POSTed
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();
      }
    }

    $this->view->form = $form;

  } // /function
  
  /**
   * Comments listing
   */
  public function commentsAction()
  {
    $comments = new Comments();
    $comments->cache_result = false;

    $comics = new Comics();
    $comics->cache_result = false;

    $select = $comments->select();
    $select->from($comments, array('c' => 'COUNT(id)'));
    $comment_count = $comments->fetchRow($select)->toArray();
    $comment_count = (int)$comment_count['c'];

    $this->view->comments = array();
    
    // Comments available
    if($comment_count > 0)
    {
/*
      $comicid = $this->getRequest()->getParam('comicid', false);
    
      $select = $comics->select();
      $select->from($comics, array('id'));
      $select->group(array('id DESC'))
      $cinfo = $comics->fetchAll($select)->toArray();
*/
      $per_page = 100;

      $page_number = (int)$this->getRequest()->getParam('page', 0);
      $this->view->page_number = $page_number;

      $select = $comments->select();
      $select->from($comments, array('id', 'nick', 'comment', 'added', 'rate', 'isstaff', 'comicid', 'ipaddr', 'host', 'useragent', 'country'));
      $select->order(array('id DESC'));
      $select->limitPage(1+$page_number, $per_page);
      $this->view->comments = $comments->fetchAll($select)->toArray();

    } // /if

  } // /function
  
  /**
   * Edit comment
   */
  public function editCommentAction()
  {
    $id = $this->getRequest()->getParam('id', false);

    $comments = new Comments();
    $comments->cache_result = false;

    $select = $comments->select();
    $select->from($comments, array('nick', 'comment', 'added', 'rate', 'isstaff'));
    $select->where('id=?', $id);
    $info = $comments->fetchRow($select)->toArray();

    // Comment form
    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . '/admin/comic/edit-comment/id/' . $id);

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Edit comment'));

    $name = new Zend_Form_Element_Text('name');
    $name->setRequired(true);
    $name->setLabel($this->tr->_('Nick'));
    $name->addFilter('StringTrim');
    $name->addValidator('NotEmpty', true);
    $name->addValidator('StringLength', false, array(3, 20));

    $comment = new Zend_Form_Element_Text('comment');
    $comment->setRequired(true);
    $comment->setLabel($this->tr->_('Comment'));
    $comment->addFilter('StringTrim');
    $comment->addValidator('NotEmpty', true);
    $comment->addValidator('StringLength', false, array(3, 300));

    $rates = array();
    $rates['-'] = '-';
    for($i=1; $i<6; $i++)
    {
      $rates[$i] = $i;
    }

    $rate = new Zend_Form_Element_Select('rate');
    $rate->setRequired(true);
    $rate->setLabel($this->tr->_('Rate comic'));
    $rate->addMultiOptions($rates);

    $cbstaff = new Zend_Form_Element_Checkbox('isstaff');
    $cbstaff->setLabel($this->tr->_('Is staff comment'));

    $form->addElement($name);
    $form->addElement($comment);
    $form->addElement($rate);
    $form->addElement($cbstaff);
    $form->addElement($submit);

    $form->populate(array(
      'name' => $info['nick'],
      'comment' => $info['comment'],
      'rate' => $info['rate'],
      'isstaff' => $info['isstaff']
    ));

    // Form POSTed
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();
        
        $rate = $values['rate'];

        if ($rate == '-' || $values['isstaff'] == '1')
        {
          $rate = new Zend_Db_Expr('NULL');
        }

        $update = array(
          'nick' => $values['name'],
          'comment' => $values['comment'],
          'isstaff' => $isstaff,
          'rate' => $rate
        );

        $this->_db->beginTransaction();

        try
        {
          $comments->update($update, $comments->getAdapter()->quoteInto('id = ?', $id));

          $this->_db->commit();

          return $this->_helper->redirector->gotoUrl("/admin/comic/comments");

        }
        catch (Exception $e)
        {
          $this->_db->rollBack();
          var_dump($e);
          die;
        }

      }
    }

    $this->view->form = $form;

  }

  /**
   * Remove comment
   */
  public function removeCommentAction()
  {
    $comments = new Comments();
    $comments->cache_result = false;

    $id = $this->getRequest()->getParam('id', false);

    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . '/admin/comic/remove-comment/id/' . $id);

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Remove comment'));

    $form->addElement($submit);

    // Form POSTed
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {

        $this->_db->beginTransaction();

        try
        {
          $comments->delete($comments->getAdapter()->quoteInto('id = ?', $id));

          $this->_db->commit();

          return $this->_helper->redirector->gotoUrl("/admin/comic/comments/");
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
   * Comment statistics
   */
  public function commentStatsAction()
  {
    $comments = new Comments();
    $comments->cache_result = false;

    // Comment count by count
    $select = $comments->select();
    $select->from($comments, array('c' => 'COUNT(id)'));
    $this->view->by_count = $comments->fetchRow($select)->toArray();

    // Latest comment by date
    $select = $comments->select();
    $select->from($comments, array('m' => 'MAX(added)'));
    $this->view->latest = $comments->fetchRow($select)->toArray();

    // First comment by date
    $select = $comments->select();
    $select->from($comments, array('m' => 'MIN(added)'));
    $this->view->first = $comments->fetchRow($select)->toArray();


    // Comment count by year
    $select = $comments->select();
    $select->from($comments, array('y' => 'YEAR(added)', 'c' => 'COUNT(YEAR(added))'));
    $select->group(array('YEAR(added)'));
    $select->order(array('c DESC'));
    $this->view->by_year = $comments->fetchAll($select)->toArray();

    // Comment count by country
    $select = $comments->select();
    $select->from($comments, array('country', 'c' => 'COUNT(country)'));
    $select->limit(25);
    $select->group(array('country'));
    $select->order(array('c DESC'));
    $this->view->by_country = $comments->fetchAll($select)->toArray();

    // Comment count by nick
    $select = $comments->select();
    $select->from($comments, array('nick', 'c' => 'COUNT(nick)'));
    $select->limit(100);
    $select->order(array('c DESC'));
    $select->group(array('nick'));
    $this->view->by_nick = $comments->fetchAll($select)->toArray();

    // Comment count by user-agent
    $select = $comments->select();
    $select->from($comments, array('useragent', 'c' => 'COUNT(useragent)'));
    $select->limit(100);
    $select->order(array('c DESC'));
    $select->group(array('useragent'));
    $this->view->by_browser = $comments->fetchAll($select)->toArray();

  } // /function
  
  public function dnsResolveAction()
  {
    $comments = new Comments();
    $comments->cache_result = false;

    $select = $comments->select();
    $select->from($comments, array('ipaddr'));
    $select->group(array('ipaddr'));
    $select->order(array('ipaddr ASC'));
    $select->where("(host = '') OR (host IS NULL)");
    $result = $comments->fetchAll($select);

    if (!is_null($result))
    {
      $result = $result->toArray();
      
      for ($i=0; $i<count($result); $i++)
      {
        $row = $result[$i];
        $host = gethostbyaddr($row['ipaddr']);
        $comments->update(array('host' => $host), $comments->getAdapter()->quoteInto('ipaddr = ?', $row['ipaddr']));

        usleep(10000);
      } // /for
    }

    return $this->_helper->redirector->gotoRoute(array('module' => 'admin', 'controller' => 'comic', 'action' => 'comments'), '', true);
  } // /function

} // /class