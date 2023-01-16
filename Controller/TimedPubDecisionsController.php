<?php

namespace Pumukit\TimedPubDecisionsBundle\Controller;

use Pumukit\CoreBundle\Controller\WebTVControllerInterface;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class TimedPubDecisionsController extends Controller implements WebTVControllerInterface
{
    private $temporizedChannels = ['PUDETV', 'PUDERADIO'];

    /**
     * @Route("/destacados/menu/", name="pumukit_timed_pub_decisions_menu")
     * @Template("PumukitTimedPubDecisionsBundle:menu:links.html.twig")
     *
     * @param Request $request
     *
     * @return array
     */
    public function menuTemporizedAction(Request $request)
    {
        $dm = $this->container->get('doctrine_mongodb')->getManager();
        $tags = [];
        foreach ($this->temporizedChannels as $channel) {
            $tags[] = $dm->getRepository('PumukitSchemaBundle:Tag')->findOneBy(['cod' => $channel]);
        }

        return ['temporizedChannels' => $tags];
    }

    /**
     * @Route("/destacados/{tagCod}/", name="pumukit_timed_pub_decisions_by_tag")
     * @ParamConverter("tag", class="PumukitSchemaBundle:Tag", options={"mapping": {"tagCod": "cod"}})
     * @Template()
     *
     * @param Tag     $tag
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return array
     */
    public function temporizedByTagAction(Tag $tag, Request $request)
    {
        [$scrollList, $numberCols, $limit] = $this->getParametersByTag();

        $multimediaObjectRepository = $this->get('doctrine_mongodb.odm.document_manager')->getRepository(MultimediaObject::class);

        $breadCrumbOptions = ['tagCod' => $tag->getCod()];
        if ($request->get('useTagAsGeneral')) {
            $objects = $multimediaObjectRepository->createBuilderWithGeneralTag($tag, ['record_date' => -1]);
            $title = $this->get('translator')->trans('General %title%', ['%title%' => $tag->getTitle()]);
            $breadCrumbOptions['useTagAsGeneral'] = true;
        } else {
            $objects = $multimediaObjectRepository->createBuilderWithTag($tag, ['record_date' => -1]);
            $title = $tag->getTitle();
        }
        $this->updateBreadcrumbs($title, 'pumukit_webtv_bytag_multimediaobjects', ['tagCod' => $tag->getCod(), 'useTagAsGeneral' => true]);

        $pager = $this->createPager($objects, $request->query->get('page', 1), $limit);

        $title = $this->get('translator')->trans($tag->getTitle());

        return [
            'title' => $title,
            'objects' => $pager,
            'tag' => $tag,
            'scroll_list' => $scrollList,
            'type' => 'multimediaobject',
            'scroll_list_path' => 'pumukit_webtv_bytag_objects_pager',
            'scroll_element_key' => 'tagCod',
            'scroll_element_value' => $tag->getCod(),
            'objectByCol' => $numberCols,
            'show_info' => true,
            'show_description' => true,
        ];
    }

    /**
     * @param       $title
     * @param       $routeName
     * @param array $routeParameters
     */
    private function updateBreadcrumbs($title, $routeName, array $routeParameters = [])
    {
        $breadcrumbs = $this->get('pumukit_web_tv.breadcrumbs');
        $breadcrumbs->add($title, $routeName, $routeParameters);
    }

    protected function getParametersByTag()
    {
        return [
            $this->container->getParameter('scroll_list_bytag'),
            $this->container->getParameter('columns_objs_bytag'),
            $this->container->getParameter('limit_objs_bytag'),
        ];
    }

    /**
     * @param array $objects
     * @param int   $page
     * @param int   $limit
     *
     * @throws \Exception
     *
     * @return mixed|Pagerfanta
     */
    private function createPager($objects, $page, $limit = 10)
    {
        return $this->get('pumukit_web_tv.pagination_service')->createDoctrineODMMongoDBAdapter($objects, $page, $limit);
    }
}
