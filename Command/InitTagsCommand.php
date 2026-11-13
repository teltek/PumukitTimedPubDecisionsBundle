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
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AsCommand(name: 'timedpubdecisions:init:tags', description: 'Create the PUDERADIO and PUDETV tags if they do not exist')]
#[AutoconfigureTag('pumukit.setup')]
class InitTagsCommand extends Command
{
    private const TAGS = ['PUDERADIO' => 'Destacados Radio', 'PUDETV' => 'Destacados TV'];

    public function __construct(private readonly DocumentManager $dm, private readonly array $locales)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Delete and create again the tags when no multimedia object uses them')->setHelp(
            <<<'EOT'
Creates the PUDERADIO and PUDETV tags under PUBDECISIONS when they do not exist.

With --force, the existing tags are deleted and created again, only if no multimedia object uses them.
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repository = $this->dm->getRepository(Tag::class);
        $parentTag = $repository->findOneBy(['cod' => 'PUBDECISIONS']);
        if (!$parentTag instanceof Tag) {
            $output->writeln('<error>There is no tag PUBDECISIONS to be the parent tag: run pumukit:init:repo first.</error>');

            return Command::FAILURE;
        }

        if ($input->getOption('force')) {
            foreach (array_keys(self::TAGS) as $code) {
                $tag = $repository->findOneBy(['cod' => $code]);
                if ($tag instanceof Tag && 0 < $tag->getNumberMultimediaObjects()) {
                    $output->writeln(sprintf('<error>%s has %d multimedia objects: nothing is deleted.</error>', $code, $tag->getNumberMultimediaObjects()));

                    return Command::FAILURE;
                }
            }
            foreach (array_keys(self::TAGS) as $code) {
                if ($tag = $repository->findOneBy(['cod' => $code])) {
                    $this->dm->remove($tag);
                    $output->writeln(sprintf('The tag %s is deleted.', $code));
                }
            }
            $this->dm->flush();
        }

        foreach (self::TAGS as $code => $title) {
            if ($repository->findOneBy(['cod' => $code])) {
                $output->writeln(sprintf('The tag %s already exists.', $code));

                continue;
            }

            $tag = new Tag();
            $tag->setCod($code);
            $tag->setMetatag(false);
            $tag->setDisplay(true);
            foreach ($this->locales as $locale) {
                $tag->setTitle($title, $locale);
            }
            $tag->setParent($parentTag);
            $tag->setProperty('route', 'pumukit_timed_pub_decisions_index');
            $this->dm->persist($tag);
            $this->dm->flush();
            $output->writeln(sprintf('The tag %s is created.', $code));
        }

        return Command::SUCCESS;
    }
}
