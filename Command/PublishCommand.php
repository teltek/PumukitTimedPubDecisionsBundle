<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Event\MultimediaObjectEvent;
use Pumukit\SchemaBundle\Event\SchemaEvents;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PublishCommand extends Command
{
    private $dm;
    private $eventDispatcher;

    public function __construct(DocumentManager $documentManager, EventDispatcherInterface $eventDispatcher)
    {
        $this->dm = $documentManager;
        $this->eventDispatcher = $eventDispatcher;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('timedpubdecisions:publish:objects')
            ->setDescription(
                'Publish multimedia objects with a temporal decision'
            )
            ->setHelp(
                <<<'EOT'

The <info>timedpubdecisions:publish:objects</info> publish multimedia objects with a temporal decision.

To use in a crontab:

  <info>crontab:*/5 * * * * apache /usr/bin/php /var/www/pumukit/bin/console timedpubdecisions:publish:objects</info>


EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $timedCode = 'PUDERADIO';
        $this->updateMultimediaObjects($output, $timedCode);

        $timedCode = 'PUDETV';
        $this->updateMultimediaObjects($output, $timedCode);

        return 0;
    }

    protected function updateMultimediaObjects(OutputInterface $output, $timedCode)
    {
        $repo = $this->dm->getRepository(MultimediaObject::class);
        $status = [MultimediaObject::STATUS_BLOCKED, MultimediaObject::STATUS_HIDDEN];

        $tagCodes = [$timedCode];
        $mms = $repo->createQueryBuilder()
            ->field('status')->in($status)
            ->field('tags.cod')->in($tagCodes)
            ->field('properties.temporized_from_'.$timedCode)->lte(date('Y-m-d\TH:i'))
            ->getQuery()->execute()
        ;

        if (0 === $mms->count()) {
            $output->writeln(sprintf('No multimedia objects to update'));

            return;
        }

        foreach ($mms as $mm) {
            $mm->setStatus(MultimediaObject::STATUS_PUBLISHED);
            $this->dm->flush();

            $event = new MultimediaObjectEvent($mm);
            $this->eventDispatcher->dispatch($event, SchemaEvents::MULTIMEDIAOBJECT_UPDATE);
            $output->writeln(sprintf('Updated '.$timedCode.' mm: %s', $mm->getId()));
        }
    }
}
