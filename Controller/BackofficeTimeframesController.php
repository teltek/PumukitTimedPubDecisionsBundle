<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Controller;

use Pumukit\NewAdminBundle\Controller\NewAdminControllerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class BackofficeTimeframesController extends AbstractController implements NewAdminControllerInterface
{
    public static $tags = ['PUDERADIO', 'PUDETV'];

    public static $colors = [
        'PUDERADIO' => '#0000FF',
        'PUDETV' => '#50AA50',
    ];

    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * @Route("/admin/timeframes")
     * @Route("/admin/timeframes/default", name="pumukit_newadmin_timeframes_index_default")
     */
    public function indexAction(Request $request): Response
    {
        $session = $request->getSession();

        if ($request->query->has('status')) {
            if ('' === trim($request->query->get('status'))) {
                $session->remove('pumukit_timed_pub_decisions.status');
            } else {
                $session->set('pumukit_timed_pub_decisions.status', $request->query->get('status'));
            }
        }

        if ($request->query->has('tags')) {
            if ('' === trim($request->query->get('tags'))) {
                $session->remove('pumukit_timed_pub_decisions.tags');
            } else {
                $session->set('pumukit_timed_pub_decisions.tags', $request->query->get('tags'));
            }
        }

        return $this->render('@PumukitTimedPubDecisions/BackofficeTimeframes/index.html.twig', [
            'colors' => self::$colors,
        ]);
    }

    /**
     * @Route("/admin/timeframes/series/timeline.xml", name="pumukit_newadmin_timeframes_xml")
     */
    public function seriesTimelineAction(Request $request): Response
    {
        $session = $request->getSession();

        $twoMonthsBefore = date('Y-m-d H:i:s', strtotime('-2 month'));
        $twoMonthsAfter = date('Y-m-d H:i:s', strtotime('+2 month'));
        $twoHoursBefore = date('Y-m-d H:i:s', strtotime('-2 hour'));
        $twoHoursAfter = date('Y-m-d H:i:s', strtotime('+2 hour'));

        $status = [MultimediaObject::STATUS_PUBLISHED, MultimediaObject::STATUS_BLOCKED, MultimediaObject::STATUS_HIDDEN];
        if ('0' == $session->get('pumukit_timed_pub_decisions.status')) {
            $status = [MultimediaObject::STATUS_PUBLISHED];
        } elseif ('1' == $session->get('pumukit_timed_pub_decisions.status')) {
            $status = [MultimediaObject::STATUS_BLOCKED, MultimediaObject::STATUS_HIDDEN];
        }

        if ($session->has('pumukit_timed_pub_decisions.tags')) {
            $targetTags = (array) $session->get('pumukit_timed_pub_decisions.tags');
        } else {
            $targetTags = self::$tags;
        }

        $qb = $this->get('doctrine_mongodb')
            ->getManager()
            ->getRepository(MultimediaObject::class)
            ->createQueryBuilder()
        ;

        $qb->field('status')->in($status)->field('tags.cod')->in($targetTags);

        if ('-1' != $session->get('pumukit_timed_pub_decisions.status')) {
            $qb->addAnd(
                $qb->expr()->field('tags.cod')->equals('PUCHWEBTV')
            );

            $qb->addOr(
                [
                    'tracks' => [
                        '$elemMatch' => [
                            'tags' => 'display',
                            'hide' => false,
                        ],
                    ],
                ],
                [
                    'properties.externalplayer' => [
                        '$exists' => true,
                        '$ne' => '',
                    ],
                ]
            );
        }
        $mms = $qb->getQuery()->execute();

        $XML = new \SimpleXMLElement('<data></data>');
        $XML->addAttribute('wiki-url', $request->getUri());
        $XML->addAttribute('wiki-section', 'Pumukit time-line Feed');

        foreach ($mms as $mm) {
            foreach ($targetTags as $tag) {
                if (!$mm->containsTagWithCod($tag)) {
                    continue;
                }

                $XMLMms = $XML->addChild('event', htmlspecialchars($mm->getTitle()));
                $XMLMms->addAttribute('durationEvent', 'true');

                if ($mm->getProperty('temporized_'.$tag)) {
                    $start = date('Y-m-d H:i:s', strtotime($mm->getProperty('temporized_from_'.$tag)));
                    $end = date('Y-m-d H:i:s', strtotime($mm->getProperty('temporized_to_'.$tag)));
                    $XMLMms->addAttribute('start', $start);
                    $XMLMms->addAttribute('end', $end);
                } else {
                    $XMLMms->addAttribute('start', $twoMonthsBefore);
                    $XMLMms->addAttribute('end', $twoMonthsAfter);
                    $XMLMms->addAttribute('latestStart', $twoHoursBefore);
                    $XMLMms->addAttribute('earliestEnd', $twoHoursAfter);
                }

                $XMLMms->addAttribute('color', self::$colors[$tag] ?? '#666666');
                $XMLMms->addAttribute('textColor', '#000000');
                $XMLMms->addAttribute('title', $mm->getTitle());
                $XMLMms->addAttribute('link', $this->router->generate('pumukitnewadmin_mms_shortener', ['id' => $mm->getId()], 1));
            }
        }

        return new Response($XML->asXML(), 200, ['Content-Type' => 'text/xml']);
    }
}
