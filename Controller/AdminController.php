<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Controller;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class AdminController extends AbstractController
{
    /**
     * @Route("/pubchannel/option/{id}/{pub}", name="pumukit_timed_pub_decisions_index")
     * @ParamConverter("multimediaObject", class="PumukitSchemaBundle:MultimediaObject", options={"id" = "id"})
     */
    public function optionsPubAction(Request $request, MultimediaObject $multimediaObject, string $pub)
    {
        $hasTag = $multimediaObject->containsTagWithCod($pub);

        return $this->render('@PumukitTimedPubDecisions/Admin/optionsPub.html.twig', [
            'tag' => $pub,
            'multimediaObject' => $multimediaObject,
            'hasTag' => $hasTag,
        ]);
    }
}
