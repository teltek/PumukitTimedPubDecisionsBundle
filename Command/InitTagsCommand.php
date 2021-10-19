<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\Tag;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InitTagsCommand extends Command
{
    private $documentManager;
    private $locales;

    public function __construct(DocumentManager $documentManager, array $locales)
    {
        $this->documentManager = $documentManager;
        $this->locales = $locales;
    }

    protected function configure(): void
    {
        $this->setName('pumukit:timed:pub:decisions:init:tags')->setDescription(
            'Load Timed publication decisions tag data fixture to your database'
        )->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter to execute this action')->setHelp(
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
        $parentTag = $this->documentManager->getRepository(Tag::class)->findOneBy(['cod' => 'PUBDECISIONS']);
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

        return 0;
    }

    private function checkTags($output): bool
    {
        $foundTags = $this->documentManager->getRepository('PumukitSchemaBundle:Tag')->findBy([
            'cod' => [
                '$in' => [
                    'PUDERADIO',
                    'PUDETV',
                ],
            ],
        ]);

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

    private function removeTags($output): void
    {
        $pudeRadio = $this->documentManager->getRepository('PumukitSchemaBundle:Tag')->findOneBy([
            'cod' => 'PUDERADIO',
        ]);

        if ($pudeRadio) {
            $this->documentManager->remove($pudeRadio);
            $this->documentManager->flush();
            $output->writeln('<info> Removing '.$pudeRadio->getCod().'</info>');
        }

        $pudeTV = $this->documentManager->getRepository('PumukitSchemaBundle:Tag')->findOneBy(
            ['cod' => 'PUDETV']
        );

        if ($pudeTV) {
            $this->documentManager->remove($pudeTV);
            $this->documentManager->flush();
            $output->writeln('<info> Removing '.$pudeTV->getCod().'</info>');
        }
    }

    private function addTags($parent, $output): void
    {
        $radioTag = $this->createTagWithCode('PUDERADIO', 'Destacados Radio', $parent, false);
        $output->writeln('Tag persisted - new id: '.$radioTag->getId().' cod: '.$radioTag->getCod());

        $tvTag = $this->createTagWithCode('PUDETV', 'Destacados TV', $parent, false);
        $output->writeln('Tag persisted - new id: '.$tvTag->getId().' cod: '.$tvTag->getCod());
    }

    private function createTagWithCode($code, $title, $parentTag, $metatag = false): Tag
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
        $this->documentManager->persist($tag);
        $this->documentManager->flush();

        return $tag;
    }
}
