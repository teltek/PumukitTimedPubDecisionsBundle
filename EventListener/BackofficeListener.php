<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\EventListener;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\NewAdminBundle\Event\PublicationSubmitEvent;
use Pumukit\SchemaBundle\Document\Tag;

class BackofficeListener
{
    public function __construct(private readonly DocumentManager $documentManager) {}

    public function onPublicationSubmit(PublicationSubmitEvent $event): void
    {
        $dm = $this->documentManager;
        $multimediaObject = $event->getMultimediaObject();
        $request = $event->getRequest();

        $pubDecisions = $request->request->all('pub_decisions');
        if (!$pubDecisions) {
            return;
        }

        $tags = $dm->getRepository(Tag::class)->findBy(['properties.route' => ['$exists' => true]]);
        foreach ($tags as $tag) {
            $key = $tag->getCod();
            if (array_key_exists($key, $pubDecisions)) {
                $optionsTemporized = $request->request->get('optionsTemporized_'.$key);
                if ('1' === $optionsTemporized) {
                    $temporizedFrom = $request->request->all('temporized_from_'.$key);
                    $temporizedTo = $request->request->all('temporized_to_'.$key);
                    $multimediaObject->setProperty('temporized_'.$key, $optionsTemporized);
                    $multimediaObject->setProperty('temporized_from_'.$key, $temporizedFrom[$key] ?? null);
                    $multimediaObject->setProperty('temporized_to_'.$key, $temporizedTo[$key] ?? null);
                    $dm->flush();
                } elseif ('-1' === $optionsTemporized) {
                    $multimediaObject->removeProperty('temporized_'.$key);
                    $multimediaObject->removeProperty('temporized_from_'.$key);
                    $multimediaObject->removeProperty('temporized_to_'.$key);
                }
            } else {
                $multimediaObject->removeProperty('temporized_'.$key);
                $multimediaObject->removeProperty('temporized_from_'.$key);
                $multimediaObject->removeProperty('temporized_to_'.$key);
            }
        }
    }
}
