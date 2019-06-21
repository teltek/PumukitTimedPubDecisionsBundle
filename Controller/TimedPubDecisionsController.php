<?php

namespace Pumukit\TimedPubDecisionsBundle\Controller;

use Pumukit\CoreBundle\Controller\WebTVControllerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Pumukit\SchemaBundle\Document\Tag;

class TimedPubDecisionsController extends Controller implements WebTVControllerInterface
{
    private $temporizedChannels = array('PUDETV', 'PUDERADIO');

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
            $tags[] = $dm->getRepository('PumukitSchemaBundle:Tag')->findOneBy(array('cod' => $channel));
        }

        return array('temporizedChannels' => $tags);
    }

    /**
     * @Route("/destacados/{tagCod}/", name="pumukit_timed_pub_decisions_by_tag")
     * @ParamConverter("tag", class="PumukitSchemaBundle:Tag", options={"mapping": {"tagCod": "cod"}})
     * @Template()
     *
     * @param Tag     $tag
     * @param Request $request
     *
     * @return array
     *
     * @throws \Exception
     */
    public function temporizedByTagAction(Tag $tag, Request $request)
    {
        $translator = $this->get('translator');
        if (!in_array($tag->getCod(), $this->temporizedChannels)) {
            throw new \Exception($translator->trans('This tag is not a temporized publication decision'));
        }

        $numberCols = $this->container->getParameter('columns_objs_bytag');

        $dm = $this->container->get('doctrine_mongodb')->getManager();

        $multimediaObjects = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findBy(array('tags.cod' => $tag->getCod()));
        /*$multimediaObjects= $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->createStandardQueryBuilder()
            ->field('tags.cod')->equals($tag->getCod())
            ->getQuery()
            ->execute();*/
        $mmoGroupBy = array();
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

        $this->updateBreadcrumbs($tag->getTitle(), 'pumukit_timed_pub_decisions_by_tag', array('tagCod' => $tag->getCod()));
        $title = $tag->getTitle();

        return array(
            'title' => $title,
            'multimediaObjects' => $mmoGroupBy,
            'tag' => $tag,
            'number_cols' => $numberCols,
        );
    }

    /**
     * @param       $title
     * @param       $routeName
     * @param array $routeParameters
     */
    private function updateBreadcrumbs($title, $routeName, array $routeParameters = array())
    {
        $breadcrumbs = $this->get('pumukit_web_tv.breadcrumbs');
        $breadcrumbs->add($title, $routeName, $routeParameters);
    }
}
