<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\NewAdminBundle\Controller\NewAdminControllerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\TimedPubDecisionsBundle\Services\TemporizedDecisionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ACCESS_TIMEDPUBDECISIONS_TIMELINE')]
class BackofficeTimeframesController extends AbstractController implements NewAdminControllerInterface
{
    public const DAYS = 90;
    private const STEP_DAYS = 30;
    private const BATCH_SIZE = 500;
    private const STATUS_FILTERS = [
        'published' => [MultimediaObject::STATUS_PUBLISHED],
        'unpublished' => [MultimediaObject::STATUS_BLOCKED, MultimediaObject::STATUS_HIDDEN],
        'all' => [MultimediaObject::STATUS_PUBLISHED, MultimediaObject::STATUS_BLOCKED, MultimediaObject::STATUS_HIDDEN],
        'processing' => [MultimediaObject::STATUS_PUBLISHED, MultimediaObject::STATUS_BLOCKED, MultimediaObject::STATUS_HIDDEN],
    ];

    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly TemporizedDecisionService $temporizedDecisionService,
        private readonly bool $isEnabled,
    ) {}

    #[Route('/admin/timeframes', methods: ['GET'])]
    #[Route('/admin/timeframes/default', name: 'pumukit_newadmin_timeframes_index_default', methods: ['GET'])]
    public function indexAction(Request $request): Response
    {
        if (!$this->isEnabled) {
            throw $this->createNotFoundException();
        }

        $codes = $this->temporizedDecisionService->tagCodes();
        $tag = $request->query->getString('tag');
        $selectedCodes = in_array($tag, $codes, true) ? [$tag] : $codes;
        $status = $request->query->getString('status', 'all');
        if (!isset(self::STATUS_FILTERS[$status])) {
            $status = 'all';
        }

        $today = new \DateTimeImmutable('today');
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('start')) ?: $today->modify(sprintf('-%d days', self::STEP_DAYS));
        $end = $start->modify(sprintf('+%d days', self::DAYS));

        return $this->render('@PumukitTimedPubDecisions/BackofficeTimeframes/index.html.twig', [
            'codes' => $codes,
            'tag' => $tag,
            'status' => $status,
            'statuses' => array_keys(self::STATUS_FILTERS),
            'start' => $start,
            'end' => $end,
            'today' => $today,
            'previous' => $start->modify(sprintf('-%d days', self::STEP_DAYS)),
            'next' => $start->modify(sprintf('+%d days', self::STEP_DAYS)),
            'months' => $this->months($start, $end),
            'rows' => [] === $selectedCodes ? [] : $this->rows($selectedCodes, $status, $start, $end),
        ]);
    }

    private function rows(array $codes, string $status, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $queryBuilder = $this->documentManager->createQueryBuilder(MultimediaObject::class)
            ->field('status')->in(self::STATUS_FILTERS[$status])
            ->field('tags.cod')->in($codes)
            ->sort('_id', 'asc')
        ;
        if ('processing' !== $status) {
            $queryBuilder->addAnd($queryBuilder->expr()->field('tags.cod')->equals('PUCHWEBTV'));
            $queryBuilder->addOr(
                ['tracks' => ['$elemMatch' => ['tags' => 'display', 'hide' => false]]],
                ['properties.externalplayer' => ['$exists' => true, '$ne' => '']],
            );
        }

        $windowStart = $start->format(TemporizedDecisionService::DATE_FORMAT);
        $windowEnd = $end->format(TemporizedDecisionService::DATE_FORMAT);
        $rows = [];
        $skip = 0;
        do {
            $batch = (clone $queryBuilder)->skip($skip)->limit(self::BATCH_SIZE)->getQuery()->execute()->toArray();
            foreach ($batch as $multimediaObject) {
                foreach ($codes as $code) {
                    if (!$multimediaObject->containsTagWithCod($code)) {
                        continue;
                    }
                    $row = $this->row($multimediaObject, $code);
                    if ($row['permanent'] || ($row['from'] <= $windowEnd && $row['to'] >= $windowStart)) {
                        $rows[] = $row;
                    }
                }
                $this->documentManager->detach($multimediaObject);
            }
            $skip += self::BATCH_SIZE;
        } while (self::BATCH_SIZE === count($batch));

        usort($rows, static fn (array $a, array $b): int => [$a['permanent'], $a['from'], $a['title']] <=> [$b['permanent'], $b['from'], $b['title']]);

        return $rows;
    }

    private function row(MultimediaObject $multimediaObject, string $code): array
    {
        $temporary = TemporizedDecisionService::TEMPORARY === $multimediaObject->getProperty('temporized_'.$code);
        $from = $temporary ? TemporizedDecisionService::normalize($multimediaObject->getProperty('temporized_from_'.$code)) : null;
        $to = $temporary ? TemporizedDecisionService::normalize($multimediaObject->getProperty('temporized_to_'.$code)) : null;

        return [
            'id' => $multimediaObject->getId(),
            'title' => $multimediaObject->getTitle(),
            'status' => $multimediaObject->getStatus(),
            'code' => $code,
            'permanent' => null === $from || null === $to,
            'from' => $from ?? '',
            'to' => $to ?? '',
            'applied' => $multimediaObject->getProperty('temporized_published_'.$code),
        ];
    }

    private function months(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $months = [];
        for ($month = $start->modify('first day of next month'); $month < $end; $month = $month->modify('+1 month')) {
            $months[] = $month;
        }

        return $months;
    }
}
