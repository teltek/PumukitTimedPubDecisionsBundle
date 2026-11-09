<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Controller;

use Pumukit\NewAdminBundle\Controller\NewAdminControllerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\TimedPubDecisionsBundle\Services\TemporizedDecisionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ACCESS_MULTIMEDIA_SERIES')]
class AdminController extends AbstractController implements NewAdminControllerInterface
{
    public function __construct(
        private readonly TemporizedDecisionService $temporizedDecisionService,
    ) {}

    #[Route('/admin/timedpubdecisions/option/{id}/{pub}', name: TemporizedDecisionService::ROUTE, methods: ['GET'])]
    public function optionsPubAction(MultimediaObject $multimediaObject, string $pub): Response
    {
        if (!$this->temporizedDecisionService->isTemporized($pub)) {
            throw $this->createNotFoundException();
        }

        return $this->render('@PumukitTimedPubDecisions/Admin/optionsPub.html.twig', [
            'tag' => $pub,
            'multimediaObject' => $multimediaObject,
            'hasTag' => $multimediaObject->containsTagWithCod($pub),
        ]);
    }
}
