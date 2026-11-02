<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Tests\Services;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\TimedPubDecisionsBundle\Services\TemporizedDecisionService;
use Pumukit\TimedPubDecisionsBundle\Tests\TimedPubDecisionsTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class TemporizedDecisionServiceTest extends TimedPubDecisionsTestCase
{
    private TemporizedDecisionService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = self::getContainer()->get(TemporizedDecisionService::class);
    }

    public function testOnlyThePublicationDecisionsWithTheRouteAreTemporized(): void
    {
        $codes = $this->service->tagCodes();
        sort($codes);

        $this->assertSame(['PUDERADIO', 'PUDETV'], $codes);
    }

    public function testTheDatesAreNormalized(): void
    {
        $this->assertSame('2026-11-02T18:30', TemporizedDecisionService::normalize('2026-11-02T18:30'));
        $this->assertSame('2026-11-02T18:30', TemporizedDecisionService::normalize('2026-11-02T18:30:45'));
        $this->assertSame('2026-11-02T18:30', TemporizedDecisionService::normalize('2026-11-02 18:30'));
        $this->assertNull(TemporizedDecisionService::normalize('2026-02-30T18:30'));
        $this->assertNull(TemporizedDecisionService::normalize('tomorrow'));
        $this->assertNull(TemporizedDecisionService::normalize(''));
        $this->assertNull(TemporizedDecisionService::normalize(null));
    }

    public function testADecisionStartedIsPublishedOnce(): void
    {
        $due = $this->temporized(MultimediaObject::STATUS_BLOCKED, 'PUDETV', '2026-11-02T10:00', '2026-11-09T10:00');
        $hidden = $this->temporized(MultimediaObject::STATUS_HIDDEN, 'PUDERADIO', '2026-11-02T11:59', '2026-11-03T10:00');
        $future = $this->temporized(MultimediaObject::STATUS_BLOCKED, 'PUDETV', '2026-11-02T12:01', '2026-11-09T10:00');

        $published = $this->service->publishDue($this->at('2026-11-02T12:00'));

        $this->assertEqualsCanonicalizing([$due->getId(), $hidden->getId()], array_map(static fn ($mm) => $mm->getId(), $published));
        $this->assertSame(MultimediaObject::STATUS_PUBLISHED, $this->reload($due)->getStatus());
        $this->assertSame('2026-11-02T12:00', $this->reload($due)->getProperty('temporized_published_PUDETV'));
        $this->assertSame(MultimediaObject::STATUS_PUBLISHED, $this->reload($hidden)->getStatus());
        $this->assertSame(MultimediaObject::STATUS_BLOCKED, $this->reload($future)->getStatus());
    }

    public function testAVideoUnpublishedAfterItsStartIsNotPublishedAgain(): void
    {
        $video = $this->temporized(MultimediaObject::STATUS_BLOCKED, 'PUDETV', '2026-11-02T10:00', '2026-11-09T10:00');
        $this->service->publishDue($this->at('2026-11-02T12:00'));

        $video = $this->reload($video);
        $video->setStatus(MultimediaObject::STATUS_BLOCKED);
        $this->dm->flush();

        $this->assertSame([], $this->service->publishDue($this->at('2026-11-02T12:05')));
        $this->assertSame(MultimediaObject::STATUS_BLOCKED, $this->reload($video)->getStatus());
    }

    public function testAVideoPublishedByHandBeforeItsStartIsNotPublishedAgainAfterUnpublishingIt(): void
    {
        $video = $this->temporized(MultimediaObject::STATUS_PUBLISHED, 'PUDETV', '2026-11-02T10:00', '2026-11-09T10:00');

        $this->assertSame([], $this->service->publishDue($this->at('2026-11-02T12:00')));
        $this->assertSame('2026-11-02T12:00', $this->reload($video)->getProperty('temporized_published_PUDETV'));

        $video = $this->reload($video);
        $video->setStatus(MultimediaObject::STATUS_HIDDEN);
        $this->dm->flush();

        $this->assertSame([], $this->service->publishDue($this->at('2026-11-02T12:05')));
    }

    public function testBothStartedDecisionsOfAVideoAreMarked(): void
    {
        $video = $this->video(MultimediaObject::STATUS_BLOCKED, ['PUDETV', 'PUDERADIO'], [
            'temporized_PUDETV' => '1', 'temporized_from_PUDETV' => '2026-11-02T10:00', 'temporized_to_PUDETV' => '2026-11-09T10:00',
            'temporized_PUDERADIO' => '1', 'temporized_from_PUDERADIO' => '2026-11-02T11:00', 'temporized_to_PUDERADIO' => '2026-11-09T10:00',
        ]);

        $this->assertCount(1, $this->service->publishDue($this->at('2026-11-02T12:00')));

        $video = $this->reload($video);
        $this->assertNotNull($video->getProperty('temporized_published_PUDETV'));
        $this->assertNotNull($video->getProperty('temporized_published_PUDERADIO'));
    }

    public function testChangingTheStartAllowsANewPublication(): void
    {
        $video = $this->temporized(MultimediaObject::STATUS_BLOCKED, 'PUDETV', '2026-11-02T10:00', '2026-11-09T10:00');
        $this->service->publishDue($this->at('2026-11-02T12:00'));
        $video = $this->reload($video);
        $video->setStatus(MultimediaObject::STATUS_BLOCKED);

        $this->service->applyDecision($video, 'PUDETV', '1', '2026-11-02T10:00', '2026-11-10T10:00');
        $this->assertNotNull($video->getProperty('temporized_published_PUDETV'));

        $this->service->applyDecision($video, 'PUDETV', '1', '2026-11-05T10:00', '2026-11-10T10:00');
        $this->assertNull($video->getProperty('temporized_published_PUDETV'));
        $this->dm->flush();

        $this->assertSame([], $this->service->publishDue($this->at('2026-11-04T10:00')));
        $this->assertCount(1, $this->service->publishDue($this->at('2026-11-05T10:00')));
    }

    public function testTheDatesOfADecisionAreValidated(): void
    {
        $video = $this->video(MultimediaObject::STATUS_BLOCKED, ['PUDETV']);

        foreach ([['', '2026-11-10T10:00'], ['2026-11-02T10:00', 'tomorrow'], ['2026-11-10T10:00', '2026-11-02T10:00']] as [$from, $to]) {
            try {
                $this->service->applyDecision($video, 'PUDETV', '1', $from, $to);
                $this->fail(sprintf('%s - %s should not be accepted', $from, $to));
            } catch (\InvalidArgumentException) {
                $this->assertNull($video->getProperty('temporized_PUDETV'));
            }
        }

        $this->service->applyDecision($video, 'PUDETV', '1', '2026-11-02T10:00:00', '2026-11-02T10:00');
        $this->assertSame('2026-11-02T10:00', $video->getProperty('temporized_from_PUDETV'));
    }

    public function testAPermanentDecisionRemovesTheTemporization(): void
    {
        $video = $this->temporized(MultimediaObject::STATUS_BLOCKED, 'PUDETV', '2026-11-02T10:00', '2026-11-09T10:00', ['temporized_published_PUDETV' => '2026-11-02T10:00']);

        $this->service->applyDecision($video, 'PUDETV', '-1', null, null);

        foreach (['temporized_', 'temporized_from_', 'temporized_to_', 'temporized_published_'] as $prefix) {
            $this->assertNull($video->getProperty($prefix.'PUDETV'));
        }
    }

    public function testOnlyTemporizedDecisionsWithTheirTagAndAPublishableStatusArePublished(): void
    {
        $this->video(MultimediaObject::STATUS_BLOCKED, ['PUDENEW'], ['temporized_PUDENEW' => '1', 'temporized_from_PUDENEW' => '2026-11-02T10:00']);
        $this->video(MultimediaObject::STATUS_BLOCKED, [], ['temporized_PUDETV' => '1', 'temporized_from_PUDETV' => '2026-11-02T10:00']);
        $this->temporized(MultimediaObject::STATUS_NEW, 'PUDETV', '2026-11-02T10:00', '2026-11-09T10:00');
        $this->video(MultimediaObject::STATUS_BLOCKED, ['PUDETV'], ['temporized_from_PUDETV' => '2026-11-02T10:00']);

        $this->assertSame([], $this->service->publishDue($this->at('2026-11-02T12:00')));
    }

    public function testTheDryRunChangesNothing(): void
    {
        $video = $this->temporized(MultimediaObject::STATUS_BLOCKED, 'PUDETV', '2026-11-02T10:00', '2026-11-09T10:00');

        $this->assertCount(1, $this->service->publishDue($this->at('2026-11-02T12:00'), true));

        $video = $this->reload($video);
        $this->assertSame(MultimediaObject::STATUS_BLOCKED, $video->getStatus());
        $this->assertNull($video->getProperty('temporized_published_PUDETV'));
    }

    public function testADecisionIsActiveBetweenItsDates(): void
    {
        $video = $this->temporized(MultimediaObject::STATUS_PUBLISHED, 'PUDETV', '2026-11-02T10:00', '2026-11-09T10:00');
        $permanent = $this->video(MultimediaObject::STATUS_PUBLISHED, ['PUDETV']);

        $this->assertFalse($this->service->isActive($video, 'PUDETV', $this->at('2026-11-02T09:59')));
        $this->assertTrue($this->service->isActive($video, 'PUDETV', $this->at('2026-11-02T10:00')));
        $this->assertTrue($this->service->isActive($video, 'PUDETV', $this->at('2026-11-09T10:00')));
        $this->assertFalse($this->service->isActive($video, 'PUDETV', $this->at('2026-11-09T10:01')));
        $this->assertTrue($this->service->isActive($permanent, 'PUDETV', $this->at('2030-01-01T00:00')));
    }

    private function at(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }
}
