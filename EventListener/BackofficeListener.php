<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\EventListener;

use Pumukit\NewAdminBundle\Event\PublicationSubmitEvent;
use Pumukit\SchemaBundle\Security\Permission;
use Pumukit\TimedPubDecisionsBundle\Services\TemporizedDecisionService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class BackofficeListener
{
    public function __construct(
        private readonly TemporizedDecisionService $temporizedDecisionService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {}

    public function onPublicationSubmit(PublicationSubmitEvent $event): void
    {
        $multimediaObject = $event->getMultimediaObject();
        $parameters = $event->getRequest()->request;
        $checked = $parameters->has('pub_decisions') ? $parameters->all('pub_decisions') : [];

        foreach ($this->temporizedDecisionService->tagCodes() as $code) {
            if ($this->authorizationChecker->isGranted(Permission::getRoleTagDisableForPubChannel($code))) {
                continue;
            }

            if (!array_key_exists($code, $checked)) {
                $this->temporizedDecisionService->clear($multimediaObject, $code);

                continue;
            }

            $option = $parameters->get('optionsTemporized_'.$code);

            try {
                $this->temporizedDecisionService->applyDecision(
                    $multimediaObject,
                    $code,
                    is_string($option) ? $option : null,
                    $parameters->all('temporized_from_'.$code)[$code] ?? null,
                    $parameters->all('temporized_to_'.$code)[$code] ?? null,
                );
            } catch (\InvalidArgumentException $exception) {
                throw new BadRequestHttpException($exception->getMessage(), $exception);
            }
        }
    }
}
