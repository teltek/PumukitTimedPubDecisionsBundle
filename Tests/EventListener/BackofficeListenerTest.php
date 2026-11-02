<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Tests\EventListener;

use Pumukit\NewAdminBundle\Event\PublicationSubmitEvent;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\TimedPubDecisionsBundle\EventListener\BackofficeListener;
use Pumukit\TimedPubDecisionsBundle\Services\TemporizedDecisionService;
use Pumukit\TimedPubDecisionsBundle\Tests\TimedPubDecisionsTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 *
 * @coversNothing
 */
class BackofficeListenerTest extends TimedPubDecisionsTestCase
{
    public function testTheTemporaryDatesAreSaved(): void
    {
        $video = $this->video(MultimediaObject::STATUS_BLOCKED, ['PUDETV']);

        $this->submit($video, [
            'pub_decisions' => ['PUDETV' => 'on'],
            'optionsTemporized_PUDETV' => '1',
            'temporized_from_PUDETV' => ['PUDETV' => '2026-11-02T10:00'],
            'temporized_to_PUDETV' => ['PUDETV' => '2026-11-09T10:00'],
        ]);

        $this->assertSame('1', $video->getProperty('temporized_PUDETV'));
        $this->assertSame('2026-11-02T10:00', $video->getProperty('temporized_from_PUDETV'));
        $this->assertSame('2026-11-09T10:00', $video->getProperty('temporized_to_PUDETV'));
    }

    public function testInvalidDatesAreABadRequest(): void
    {
        $video = $this->video(MultimediaObject::STATUS_BLOCKED, ['PUDETV']);

        $this->expectException(BadRequestHttpException::class);

        $this->submit($video, [
            'pub_decisions' => ['PUDETV' => 'on'],
            'optionsTemporized_PUDETV' => '1',
            'temporized_from_PUDETV' => ['PUDETV' => '2026-11-09T10:00'],
            'temporized_to_PUDETV' => ['PUDETV' => '2026-11-02T10:00'],
        ]);
    }

    public function testAnUncheckedDecisionLosesItsTemporization(): void
    {
        $video = $this->temporized(MultimediaObject::STATUS_BLOCKED, 'PUDETV', '2026-11-02T10:00', '2026-11-09T10:00');

        $this->submit($video, ['pub_decisions' => ['PUDERADIO' => 'on']]);

        $this->assertNull($video->getProperty('temporized_PUDETV'));
        $this->assertNull($video->getProperty('temporized_from_PUDETV'));
    }

    public function testADecisionTheUserCannotChangeIsKept(): void
    {
        $video = $this->temporized(MultimediaObject::STATUS_BLOCKED, 'PUDETV', '2026-11-02T10:00', '2026-11-09T10:00');

        $this->submit($video, ['pub_decisions' => []], ['ROLE_TAG_DISABLE_PUDETV']);

        $this->assertSame('2026-11-02T10:00', $video->getProperty('temporized_from_PUDETV'));
    }

    private function submit(MultimediaObject $video, array $parameters, array $roles = []): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturnCallback(static fn (string $role): bool => in_array($role, $roles, true));
        $listener = new BackofficeListener(self::getContainer()->get(TemporizedDecisionService::class), $authorizationChecker);

        $listener->onPublicationSubmit(new PublicationSubmitEvent($video, new Request([], $parameters)));
    }
}
