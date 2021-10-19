<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\EventListener;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\NewAdminBundle\Event\PublicationSubmitEvent;
use Pumukit\SchemaBundle\Document\Tag;

class BackofficeListener
{
    private $documentManager;

    public function __construct(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
    }

    public function onPublicationSubmit(PublicationSubmitEvent $event): void
    {
        $multimediaObject = $event->getMultimediaObject();
        $request = $event->getRequest();

        if ($request->request->has('pub_decisions')) {
            $pubDecisions = $request->request->get('pub_decisions');

            $tags = $this->documentManager->getRepository(Tag::class)->findBy(['properties.route' => ['$exists' => true]]);
            foreach ($tags as $tag) {
                if (array_key_exists($tag->getCod(), $pubDecisions)) {
                    $key = $tag->getCod();
                    if ($request->request->has('optionsTemporized_'.$key) && ('1' === $request->request->get('optionsTemporized_'.$key))) {
                        $multimediaObject->setProperty('temporized_'.$key, $request->request->get('optionsTemporized_'.$key));
                        $multimediaObject->setProperty('temporized_from_'.$key, $request->request->get('temporized_from_'.$key)[$key]);
                        $multimediaObject->setProperty('temporized_to_'.$key, $request->request->get('temporized_to_'.$key)[$key]);
                        $this->documentManager->flush();
                    } elseif ($request->request->has('optionsTemporized_'.$key) && ('-1' === $request->request->get('optionsTemporized_'.$key))) {
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
