<?php

namespace Pumukit\TimedPubDecisionsBundle\EventListener;

use Pumukit\NewAdminBundle\Event\PublicationSubmitEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BackofficeListener
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onPublicationSubmit(PublicationSubmitEvent $event)
    {
        $dm = $this->container->get('doctrine_mongodb.odm.document_manager');
        $multimediaObject = $event->getMultimediaObject();
        $request = $event->getRequest();

        if ($request->request->has('pub_decisions')) {
            $pubDecisions = $request->request->get('pub_decisions');

            $tags = $dm->getRepository('PumukitSchemaBundle:Tag')->findBy(['properties.route' => ['$exists' => true]]);
            foreach ($tags as $tag) {
                if (array_key_exists($tag->getCod(), $pubDecisions)) {
                    $key = $tag->getCod();
                    if ($request->request->has('optionsTemporized_'.$key) and ('1' == $request->request->get('optionsTemporized_'.$key))) {
                        $multimediaObject->setProperty('temporized_'.$key, $request->request->get('optionsTemporized_'.$key));
                        $multimediaObject->setProperty('temporized_from_'.$key, $request->request->get('temporized_from_'.$key)[$key]);
                        $multimediaObject->setProperty('temporized_to_'.$key, $request->request->get('temporized_to_'.$key)[$key]);
                        $dm->flush();
                    } elseif ($request->request->has('optionsTemporized_'.$key) and ('-1' == $request->request->get('optionsTemporized_'.$key))) {
                        $multimediaObject->removeProperty('temporized_'.$key);
                        $multimediaObject->removeProperty('temporized_from_'.$key);
                        $multimediaObject->removeProperty('temporized_to_'.$key);
                    }
                } else {
                    $multimediaObject->removeProperty('temporized_'.$tag->getCod());
                    $multimediaObject->removeProperty('temporized_from_'.$tag->getCod());
                    $multimediaObject->removeProperty('temporized_to_'.$tag->getCod());
                }
            }
        }
    }
}
