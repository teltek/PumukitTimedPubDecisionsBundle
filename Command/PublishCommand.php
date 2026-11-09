<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Command;

use Pumukit\TimedPubDecisionsBundle\Services\TemporizedDecisionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'timedpubdecisions:publish:objects', description: 'Publish multimedia objects with a temporal decision')]
class PublishCommand extends Command
{
    public function __construct(
        private readonly TemporizedDecisionService $temporizedDecisionService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the multimedia objects that would be published without changing them')
            ->setHelp(
                <<<'EOT'
                    The <info>timedpubdecisions:publish:objects</info> command publishes the blocked or hidden multimedia
                    objects whose temporized decision has started. Each start date is applied once: if the video is
                    unpublished afterwards, it is not published again until the start date is changed.

                    To use in a crontab:

                      <info>*/5 * * * * /usr/local/bin/php /srv/pumukit/bin/console timedpubdecisions:publish:objects --env=prod</info>
                    EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $published = $this->temporizedDecisionService->publishDue(new \DateTimeImmutable(), $dryRun);

        foreach ($published as $multimediaObject) {
            $output->writeln(sprintf('%s %s', $dryRun ? 'Would publish' : 'Published', $multimediaObject->getId()));
        }
        $output->writeln(sprintf('%d multimedia objects %s', count($published), $dryRun ? 'would be published' : 'published'));

        return Command::SUCCESS;
    }
}
