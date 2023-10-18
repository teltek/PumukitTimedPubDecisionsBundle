<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Controller;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    /**
     * @Route("/pubchannel/option/{id}/{pub}", name="pumukit_timed_pub_decisions_index")
     *
     * @ParamConverter("multimediaObject", options={"id" = "id"})
     *
     * @param mixed $pub
     */
    public function optionsPubAction(MultimediaObject $multimediaObject, $pub): Response
    {
        $hasTag = $multimediaObject->containsTagWithCod($pub);

        return $this->render(
            '@PumukitTimedPubDecisions/Admin/optionsPub.html.twig',
            [
                'tag' => $pub,
                'multimediaObject' => $multimediaObject,
                'hasTag' => $hasTag]
        );
    }
}
