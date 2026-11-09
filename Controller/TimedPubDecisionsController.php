<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Controller;

use Doctrine\Bundle\MongoDBBundle\Attribute\MapDocument;
use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\CoreBundle\Controller\WebTVControllerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\TimedPubDecisionsBundle\Services\TemporizedDecisionService;
use Pumukit\WebTVBundle\Services\BreadcrumbsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TimedPubDecisionsController extends AbstractController implements WebTVControllerInterface
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly TemporizedDecisionService $temporizedDecisionService,
        private readonly BreadcrumbsService $breadcrumbsService,
        private readonly bool $scrollListByTag,
        private readonly int $columnsObjsByTag,
    ) {}

    #[Route('/destacados/menu/', name: 'pumukit_timed_pub_decisions_menu')]
    public function menuTemporizedAction(): Response
    {
        return $this->render('@PumukitTimedPubDecisions/menu/links.html.twig', ['temporizedChannels' => $this->temporizedDecisionService->tags()]);
    }

    #[Route('/destacados/{tagCod}/', name: 'pumukit_timed_pub_decisions_by_tag')]
    public function temporizedByTagAction(#[MapDocument(mapping: ['tagCod' => 'cod'])] Tag $tag): Response
    {
        if (!$this->temporizedDecisionService->isTemporized($tag->getCod())) {
            throw $this->createNotFoundException('This tag is not a temporized publication decision');
        }

        $multimediaObjects = $this->documentManager->getRepository(MultimediaObject::class)->findBy(
            ['tags.cod' => $tag->getCod()],
            ['record_date' => 'desc']
        );

        $now = new \DateTimeImmutable();
        $mmoGroupBy = [];
        foreach ($multimediaObjects as $multimediaObject) {
            if ($this->temporizedDecisionService->isActive($multimediaObject, $tag->getCod(), $now)) {
                $mmoGroupBy[$multimediaObject->getRecordDate()->format('Y')][] = $multimediaObject;
            }
        }

        ksort($mmoGroupBy);

        $this->updateBreadcrumbs($tag->getTitle(), 'pumukit_timed_pub_decisions_by_tag', ['tagCod' => $tag->getCod()]);
        $title = $tag->getTitle();

        return $this->render('@PumukitTimedPubDecisions/TimedPubDecisions/temporizedByTag.html.twig', [
            'title' => $title,
            'multimediaObjects' => $mmoGroupBy,
            'tag' => $tag,
            'scroll_list' => $this->scrollListByTag,
            'type' => 'multimediaobject',
            'scroll_list_path' => 'pumukit_webtv_bytag_objects_pager',
            'scroll_element_key' => 'tagCod',
            'scroll_element_value' => $tag->getCod(),
            'objectByCol' => $this->columnsObjsByTag,
            'show_info' => true,
            'show_description' => false,
        ]);
    }

    private function updateBreadcrumbs(string $title, string $routeName, array $routeParameters = []): void
    {
        $this->breadcrumbsService->add($title, $routeName, $routeParameters);
    }
}
