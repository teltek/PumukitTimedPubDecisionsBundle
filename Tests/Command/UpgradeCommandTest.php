<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Tests\Command;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\TimedPubDecisionsBundle\Services\TemporizedDecisionService;
use Pumukit\TimedPubDecisionsBundle\Tests\TimedPubDecisionsTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @coversNothing
 */
class UpgradeCommandTest extends TimedPubDecisionsTestCase
{
    public function testTheStartedDecisionsAreMarkedSoTheyAreNotPublishedAfterUpgrading(): void
    {
        $unpublishedByHand = $this->temporized(MultimediaObject::STATUS_BLOCKED, 'PUDETV', '2020-01-01T10:00:00', '2020-02-01 10:00');
        $future = $this->temporized(MultimediaObject::STATUS_BLOCKED, 'PUDETV', '2099-01-01T10:00', '2099-02-01T10:00');

        $tester = $this->tester();
        $tester->execute(['--dry-run' => true]);
        $this->assertStringContainsString('1 of 2 temporized multimedia objects would be marked', $tester->getDisplay());
        $this->assertNull($this->reload($unpublishedByHand)->getProperty('temporized_published_PUDETV'));

        $this->tester()->execute([]);

        $unpublishedByHand = $this->reload($unpublishedByHand);
        $this->assertNotNull($unpublishedByHand->getProperty('temporized_published_PUDETV'));
        $this->assertSame('2020-01-01T10:00', $unpublishedByHand->getProperty('temporized_from_PUDETV'));
        $this->assertSame('2020-02-01T10:00', $unpublishedByHand->getProperty('temporized_to_PUDETV'));
        $this->assertNull($this->reload($future)->getProperty('temporized_published_PUDETV'));
        $this->assertSame([], self::getContainer()->get(TemporizedDecisionService::class)->publishDue(new \DateTimeImmutable()));
    }

    public function testThePublishCommandDryRunListsTheVideos(): void
    {
        $video = $this->temporized(MultimediaObject::STATUS_BLOCKED, 'PUDETV', '2020-01-01T10:00', '2099-02-01T10:00');
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('timedpubdecisions:publish:objects'));

        $tester->execute(['--dry-run' => true]);

        $this->assertStringContainsString('Would publish '.$video->getId(), $tester->getDisplay());
        $this->assertSame(MultimediaObject::STATUS_BLOCKED, $this->reload($video)->getStatus());
    }

    private function tester(): CommandTester
    {
        return new CommandTester((new Application(self::$kernel))->find('timedpubdecisions:upgrade'));
    }
}
