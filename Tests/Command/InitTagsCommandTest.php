<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Tests\Command;

use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Document\Tag;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @coversNothing
 */
class InitTagsCommandTest extends PumukitTestCase
{
    public function testItCreatesTheTagsOnlyOnce(): void
    {
        $this->createTag('PUBDECISIONS');

        $tester = $this->tester();
        $this->assertSame(Command::SUCCESS, $tester->execute([]), $tester->getDisplay());
        $this->assertStringContainsString('The tag PUDERADIO is created.', $tester->getDisplay());
        $this->assertStringContainsString('The tag PUDETV is created.', $tester->getDisplay());

        $tester = $this->tester();
        $this->assertSame(Command::SUCCESS, $tester->execute([]), $tester->getDisplay());
        $this->assertStringContainsString('already exists', $tester->getDisplay());

        $this->dm->clear();
        $this->assertCount(1, $this->dm->getRepository(Tag::class)->findBy(['cod' => 'PUDERADIO']));
        $this->assertCount(1, $this->dm->getRepository(Tag::class)->findBy(['cod' => 'PUDETV']));
    }

    public function testItFailsWithoutTheParentTag(): void
    {
        $tester = $this->tester();

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('run pumukit:init:repo first', $tester->getDisplay());
    }

    public function testForceCreatesAgainTheUnusedTags(): void
    {
        $this->createTag('PUBDECISIONS');
        $this->tester()->execute([]);
        $old = $this->dm->getRepository(Tag::class)->findOneBy(['cod' => 'PUDETV'])->getId();

        $tester = $this->tester();
        $this->assertSame(Command::SUCCESS, $tester->execute(['--force' => true]), $tester->getDisplay());

        $this->dm->clear();
        $this->assertNotSame($old, $this->dm->getRepository(Tag::class)->findOneBy(['cod' => 'PUDETV'])->getId());
        $this->assertCount(1, $this->dm->getRepository(Tag::class)->findBy(['cod' => 'PUDETV']));
    }

    private function tester(): CommandTester
    {
        return new CommandTester((new Application(self::$kernel))->find('timedpubdecisions:init:tags'));
    }

    private function createTag(string $code): Tag
    {
        $tag = new Tag();
        $tag->setCod($code);
        $this->dm->persist($tag);
        $this->dm->flush();

        return $tag;
    }
}
