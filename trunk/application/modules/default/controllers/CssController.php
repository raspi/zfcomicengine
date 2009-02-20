<?php
class CssController extends Controller
{
  public function init()
  {
    $this->_helper->layout->disableLayout();

    $response = $this->getResponse();
    $response->setHeader('Content-Type', 'text/css; charset=utf8', true);
  }

  public function indexAction()
  {

  }

} // /class
