<?php

namespace Pumukit\TimedPubDecisionsBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Pumukit\SchemaBundle\Document\MultimediaObject;

class PublishCommand extends ContainerAwareCommand
{
    private $dm = null;

    protected function configure()
    {
        $this->setName('timedpubdecisions:publish:objects')
            ->setDescription(
                'Publish multimedia objects with a temporal decision')
            ->setHelp(
                <<<'EOT'

The <info>timedpubdecisions:publish:objects</info> publish multimedia objects with a temporal decision.

To use in a crontab:

  <info>crontab:*/5 * * * * apache /usr/bin/php /var/www/pumukit/app/console timedpubdecisions:publish:objects</info>


EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();

        $timedCode = 'PUDERADIO';
        $this->updateMultimediaObjects($output, $timedCode);

        $timedCode = 'PUDETV';
        $this->updateMultimediaObjects($output, $timedCode);
    }

    protected function updateMultimediaObjects(OutputInterface $output, $timedCode)
    {
        $repo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $status = array(MultimediaObject::STATUS_BLOCKED, MultimediaObject::STATUS_HIDDEN);

        $tagCodes = array($timedCode);
        $mms = $repo->createQueryBuilder()
             ->field('status')->in($status)
             ->field('tags.cod')->in($tagCodes)
             ->field('properties.temporized_from_'.$timedCode)->lte(date('Y-m-d\TH:i'))
             ->getQuery()->execute();

        if (0 != $mms->count()) {
            foreach ($mms as $mm) {
                $mm->setStatus(MultimediaObject::STATUS_PUBLISHED);
                $output->writeln(sprintf('Updated '.$timedCode.' mm: %s', $mm->getId()));
            }

            $this->dm->flush();
        } else {
            $output->writeln(sprintf('No multimedia objects to update'));
        }

        return $mms;
    }
}
