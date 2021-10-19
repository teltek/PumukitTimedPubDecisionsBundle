<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\CoreBundle\Controller\WebTVControllerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\WebTVBundle\Services\BreadcrumbsService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;

class TimedPubDecisionsController extends AbstractController implements WebTVControllerInterface
{
    private $documentManager;
    private $breadcrumbsService;
    private $translator;
    private $columnsObjsByTag;
    private $temporizedChannels = ['PUDETV', 'PUDERADIO'];

    public function __construct(
        DocumentManager $documentManager,
        BreadcrumbsService $breadcrumbsService,
        TranslatorInterface $translator,
        $columnsObjsByTag
    ) {
        $this->documentManager = $documentManager;
        $this->breadcrumbsService = $breadcrumbsService;
        $this->translator = $translator;
        $this->columnsObjsByTag = $columnsObjsByTag;
    }

    /**
     * @Route("/destacados/menu/", name="pumukit_timed_pub_decisions_menu")
     */
    public function menuTemporizedAction(Request $request)
    {
        $tags = [];
        foreach ($this->temporizedChannels as $channel) {
            $tags[] = $this->documentManager->getRepository(Tag::class)->findOneBy(['cod' => $channel]);
        }

        return $this->render('@PumukitTimedPubDecisions/menu/links.html.twig', ['temporizedChannels' => $tags]);
    }

    /**
     * @Route("/destacados/{tagCod}/", name="pumukit_timed_pub_decisions_by_tag")
     * @ParamConverter("tag", class="PumukitSchemaBundle:Tag", options={"mapping": {"tagCod": "cod"}})
     */
    public function temporizedByTagAction(Request $request, Tag $tag)
    {
        if (!in_array($tag->getCod(), $this->temporizedChannels)) {
            throw new \Exception($this->translator->trans('This tag is not a temporized publication decision'));
        }

        $multimediaObjects = $this->documentManager->getRepository(MultimediaObject::class)->findBy([
            'tags.cod' => $tag->getCod(),
        ]);

        $mmoGroupBy = [];
        foreach ($multimediaObjects as $multimediaObject) {
            $recordDate = $multimediaObject->getRecordDate();
            $year = $recordDate->format('Y');
            if ($multimediaObject->getProperty('temporized_'.$tag->getCod())) {
                $date = date('Y-m-d H:i');
                $from = $multimediaObject->getProperty('temporized_from_'.$tag->getCod());
                $to = $multimediaObject->getProperty('temporized_to_'.$tag->getCod());
                if (strtotime($date) >= strtotime($from) && strtotime($to) >= strtotime($date)) {
                    $mmoGroupBy[$year][] = $multimediaObject;
                }
            } else {
                $mmoGroupBy[$year][] = $multimediaObject;
            }
        }

        ksort($mmoGroupBy);

        $this->updateBreadcrumbs($tag->getTitle(), 'pumukit_timed_pub_decisions_by_tag', ['tagCod' => $tag->getCod()]);
        $title = $tag->getTitle();

        return $this->render('@PumukitTimedPubDecisions/TimedPubDecisions/temporizedByTag.html.twig', [
            'title' => $title,
            'multimediaObjects' => $mmoGroupBy,
            'tag' => $tag,
            'number_cols' => $this->columnsObjsByTag,
        ]);
    }

    private function updateBreadcrumbs(string $title, string $routeName, array $routeParameters = []): void
    {
        $this->breadcrumbsService->add($title, $routeName, $routeParameters);
    }
}
