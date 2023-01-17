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
        $translator = $this->get('translator');
        if (!in_array($tag->getCod(), $this->temporizedChannels)) {
            throw new \Exception($translator->trans('This tag is not a temporized publication decision'));
        }

        [$scrollList, $numberCols, $limit] = $this->getParametersByTag();

        $dm = $this->container->get('doctrine_mongodb')->getManager();

        $multimediaObjects = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findBy(['tags.cod' => $tag->getCod()]);

        $mmoGroupBy = [];
        foreach ($multimediaObjects as $multimediaObject) {
            $recordDate = $multimediaObject->getRecordDate();
            $year = $recordDate->format('Y');
            if ($multimediaObject->getProperty('temporized_'.$tag->getCod())) {
                $date = date('Y-m-d H:i');
                $from = $multimediaObject->getProperty('temporized_from_'.$tag->getCod());
                $to = $multimediaObject->getProperty('temporized_to_'.$tag->getCod());
                if (strtotime($date) >= strtotime($from) and strtotime($to) >= strtotime($date)) {
                    $mmoGroupBy[$year][] = $multimediaObject;
                }
            } else {
                $mmoGroupBy[$year][] = $multimediaObject;
            }
        }

        ksort($mmoGroupBy);

        $this->updateBreadcrumbs($tag->getTitle(), 'pumukit_timed_pub_decisions_by_tag', ['tagCod' => $tag->getCod()]);
        $title = $tag->getTitle();

        return [
            'title' => $title,
            'multimediaObjects' => $mmoGroupBy,
            'tag' => $tag,
            'scroll_list' => $scrollList,
            'type' => 'multimediaobject',
            'scroll_list_path' => 'pumukit_webtv_bytag_objects_pager',
            'scroll_element_key' => 'tagCod',
            'scroll_element_value' => $tag->getCod(),
            'objectByCol' => $numberCols,
            'show_info' => true,
            'show_description' => false,
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
}
