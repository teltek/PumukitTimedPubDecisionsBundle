<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\NewAdminBundle\Controller\NewAdminControllerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class BackofficeTimeframesController extends AbstractController implements NewAdminControllerInterface
{
    public static $tags = ['PUDERADIO', 'PUDETV'];

    public static $colors = [
        'PUDERADIO' => '#0000FF',
        'PUDETV' => '#50AA50',
    ];
    private $documentManager;

    private $router;

    public function __construct(
        RouterInterface $router,
        DocumentManager $documentManager
    ) {
        $this->documentManager = $documentManager;
        $this->router = $router;
    }

    #[Route('/admin/timeframes')]
    #[Route('/admin/timeframes/default', name: 'pumukit_newadmin_timeframes_index_default')]
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

    #[Route('/admin/timeframes/series/timeline.json', name: 'pumukit_newadmin_timeframes_json')]
    public function seriesTimelineAction(Request $request): JsonResponse
    {
        $session = $request->getSession();

        $twoMonthsBefore = (new \DateTimeImmutable('-2 month'))->format(\DateTimeInterface::ATOM);
        $twoMonthsAfter = (new \DateTimeImmutable('+2 month'))->format(\DateTimeInterface::ATOM);

        $status = [MultimediaObject::STATUS_PUBLISHED, MultimediaObject::STATUS_BLOCKED, MultimediaObject::STATUS_HIDDEN];
        if ('0' === $session->get('pumukit_timed_pub_decisions.status')) {
            $status = [MultimediaObject::STATUS_PUBLISHED];
        } elseif ('1' === $session->get('pumukit_timed_pub_decisions.status')) {
            $status = [MultimediaObject::STATUS_BLOCKED, MultimediaObject::STATUS_HIDDEN];
        }

        if ($session->has('pumukit_timed_pub_decisions.tags')) {
            $targetTags = (array) $session->get('pumukit_timed_pub_decisions.tags');
        } else {
            $targetTags = self::$tags;
        }

        $qb = $this->documentManager->createQueryBuilder(MultimediaObject::class);
        $qb->field('status')->in($status)->field('tags.cod')->in($targetTags);

        if ('-1' !== $session->get('pumukit_timed_pub_decisions.status')) {
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

        $qb->sort('_id', 'asc');

        $events = [];

        $batchSize = 500;
        $skip = 0;

        do {
            $batchQb = clone $qb;

            $batchQb
                ->skip($skip)
                ->limit($batchSize)
            ;

            $mms = $batchQb->getQuery()->execute();
            $processed = 0;

            foreach ($mms as $mm) {
                ++$processed;

                foreach ($targetTags as $tag) {
                    if (!$mm->containsTagWithCod($tag)) {
                        continue;
                    }

                    $permanent = !$mm->getProperty('temporized_'.$tag);
                    if ($permanent) {
                        $start = $twoMonthsBefore;
                        $end = $twoMonthsAfter;
                    } else {
                        $start = (new \DateTimeImmutable($mm->getProperty('temporized_from_'.$tag)))->format(\DateTimeInterface::ATOM);
                        $end = (new \DateTimeImmutable($mm->getProperty('temporized_to_'.$tag)))->format(\DateTimeInterface::ATOM);
                    }

                    $events[] = [
                        'title' => $mm->getTitle(),
                        'start' => $start,
                        'end' => $end,
                        'tag' => $tag,
                        'color' => self::$colors[$tag] ?? '#666666',
                        'permanent' => $permanent,
                        'link' => $this->router->generate('pumukitnewadmin_mms_shortener', ['id' => $mm->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                    ];
                }

                $this->documentManager->detach($mm);
            }

            $skip += $processed;

            unset($mms, $batchQb);

            gc_collect_cycles();
        } while ($processed === $batchSize);

        return new JsonResponse(['events' => $events]);
    }
}
