<?php
namespace Ideasoft\HttpBatchBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Ideasoft\HttpBatchBundle\Annotation\BatchRequest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class DefaultController extends Controller
{
    /**
     * @Route("/batch", name="http_batch")
     * @Method({"POST"})
     * @BatchRequest
     */
    public function indexAction(Request $request)
    {
    }
}
