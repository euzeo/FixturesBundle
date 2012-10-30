<?php

namespace Euzeo\FixturesBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('EuzeoFixturesBundle:Default:index.html.twig', array('name' => $name));
    }
}
