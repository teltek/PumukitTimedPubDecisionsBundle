<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\Tag;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'timedpubdecisions:init:tags', description: 'Load Timed publication decisions tag data fixture to your database')]
class InitTagsCommand extends Command
{
    public function __construct(private readonly DocumentManager $dm, private readonly array $locales)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter to execute this action')->setHelp(
            <<<'EOT'
Command to load a controlled Timed publication decisions tags data into a database.

This command will create two new tags ( PUDERADIO and PUDETV )

The --force parameter has to be used to drop old created tag PUDERADIO and PUDETV from the database.

Ever drops old tags.

EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parentTag = $this->dm->getRepository(Tag::class)->findOneBy(['cod' => 'PUBDECISIONS']);
        if (!$parentTag) {
            throw new \Exception(
                ' Nothing done - There is no tag in the database with code PUBDECISIONS to be the parent tag'
            );
        }

        $allowDelete = $this->checkTags($output);

        if ($allowDelete) {
            $this->removeTags($output);
            $this->addTags($parentTag, $output);
        }

        return Command::SUCCESS;
    }

    private function checkTags($output)
    {
        $foundTags = $this->dm->getRepository(Tag::class)->findBy(
            ['cod' => ['$in' => ['PUDERADIO', 'PUDETV']]]
        );

        if ($foundTags) {
            $output->writeln('<comment> There are tags with cod PUDERADIO or PUDETV</comment>');

            foreach ($foundTags as $tag) {
                if (0 < $tag->getNumberMultimediaObjects()) {
                    $output->writeln('<info> '.$tag->getCod().' has '.$tag->getNumberMultimediaObjects().' multimedia objects associated</info>');

                    return false;
                }
            }
        }

        return true;
    }

    private function removeTags($output)
    {
        $pudeRadio = $this->dm->getRepository(Tag::class)->findOneBy(
            ['cod' => 'PUDERADIO']
        );

        if ($pudeRadio) {
            $this->dm->remove($pudeRadio);
            $this->dm->flush();
            $output->writeln('<info> Removing '.$pudeRadio->getCod().'</info>');
        }

        $pudeTV = $this->dm->getRepository(Tag::class)->findOneBy(
            ['cod' => 'PUDETV']
        );

        if ($pudeTV) {
            $this->dm->remove($pudeTV);
            $this->dm->flush();
            $output->writeln('<info> Removing '.$pudeTV->getCod().'</info>');
        }
    }

    private function addTags($parent, $output)
    {
        $radioTag = $this->createTagWithCode('PUDERADIO', 'Destacados Radio', $parent, false);
        $output->writeln('Tag persisted - new id: '.$radioTag->getId().' cod: '.$radioTag->getCod());

        $tvTag = $this->createTagWithCode('PUDETV', 'Destacados TV', $parent, false);
        $output->writeln('Tag persisted - new id: '.$tvTag->getId().' cod: '.$tvTag->getCod());
    }

    private function createTagWithCode($code, $title, $parentTag, $metatag = false)
    {
        $tag = new Tag();
        $tag->setCod($code);
        $tag->setMetatag($metatag);
        $tag->setDisplay(true);

        foreach ($this->locales as $language) {
            $tag->setTitle($title, $language);
        }

        $tag->setParent($parentTag);
        $tag->setProperty('route', 'pumukit_timed_pub_decisions_index');
        $this->dm->persist($tag);
        $this->dm->flush();

        return $tag;
    }
}
