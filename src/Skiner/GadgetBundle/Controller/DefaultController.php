<?php

namespace Skiner\GadgetBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('GadgetBundle:Default:index.html.twig');
    }
}
