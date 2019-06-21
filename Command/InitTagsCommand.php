<?php

namespace Pumukit\TimedPubDecisionsBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Pumukit\SchemaBundle\Document\Tag;

class InitTagsCommand extends ContainerAwareCommand
{
    private $dm = null;
    private $languages = null;

    protected function configure()
    {
        $this->setName('timedpubdecisions:init:tags')->setDescription(
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        $this->languages = $this->getContainer()->getParameter('pumukit.locales');

        $parentTag = $this->dm->getRepository('PumukitSchemaBundle:Tag')->findOneBy(array('cod' => 'PUBDECISIONS'));
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

    private function checkTags($output)
    {
        $foundTags = $this->dm->getRepository('PumukitSchemaBundle:Tag')->findBy(
            array('cod' => array('$in' => array('PUDERADIO', 'PUDETV')))
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
        $pudeRadio = $this->dm->getRepository('PumukitSchemaBundle:Tag')->findOneBy(
            array('cod' => 'PUDERADIO')
        );

        if ($pudeRadio) {
            $this->dm->remove($pudeRadio);
            $this->dm->flush();
            $output->writeln('<info> Removing '.$pudeRadio->getCod().'</info>');
        }

        $pudeTV = $this->dm->getRepository('PumukitSchemaBundle:Tag')->findOneBy(
            array('cod' => 'PUDETV')
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

        foreach ($this->languages as $language) {
            $tag->setTitle($title, $language);
        }

        $tag->setParent($parentTag);
        $tag->setProperty('route', 'pumukit_timed_pub_decisions_index');
        $this->dm->persist($tag);
        $this->dm->flush();

        return $tag;
    }
}
