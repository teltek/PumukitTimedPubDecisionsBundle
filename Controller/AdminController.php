<?php

namespace Pumukit\TimedPubDecisionsBundle\Controller;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class AdminController extends Controller
{
    /**
     * @Route("/pubchannel/option/{id}/{pub}", name="pumukit_timed_pub_decisions_index")
     * @ParamConverter("multimediaObject", options={"id" = "id"})
     * @Template()
     *
     * @param MultimediaObject $multimediaObject
     * @param string           $pub
     * @param Request          $request
     *
     * @return array
     */
    public function optionsPubAction(MultimediaObject $multimediaObject, $pub, Request $request)
    {
        $hasTag = $multimediaObject->containsTagWithCod($pub);

        return ['tag' => $pub, 'multimediaObject' => $multimediaObject, 'hasTag' => $hasTag];
    }
}
