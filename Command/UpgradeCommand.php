<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\TimedPubDecisionsBundle\Services\TemporizedDecisionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'timedpubdecisions:upgrade', description: 'Normalize the temporized dates and mark the started decisions as already applied, once after upgrading to 6.0')]
class UpgradeCommand extends Command
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly TemporizedDecisionService $temporizedDecisionService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without saving it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $now = new \DateTimeImmutable();
        $codes = $this->temporizedDecisionService->tagCodes();
        if ([] === $codes) {
            $output->writeln('There are no temporized decisions');

            return Command::SUCCESS;
        }

        $criteria = array_map(static fn (string $code): array => ['properties.temporized_'.$code => TemporizedDecisionService::TEMPORARY], $codes);
        $multimediaObjects = $this->documentManager->getRepository(MultimediaObject::class)->findBy(['$or' => $criteria]);

        $marked = 0;
        foreach ($multimediaObjects as $multimediaObject) {
            foreach ($codes as $code) {
                foreach (['temporized_from_', 'temporized_to_'] as $prefix) {
                    $value = $multimediaObject->getProperty($prefix.$code);
                    if (null === $value) {
                        continue;
                    }
                    $normalized = TemporizedDecisionService::normalize($value);
                    if (null === $normalized) {
                        $output->writeln(sprintf('<error>%s: %s%s "%s" is not a valid date</error>', $multimediaObject->getId(), $prefix, $code, (string) $value));
                    } elseif ($normalized !== $value) {
                        $multimediaObject->setProperty($prefix.$code, $normalized);
                    }
                }
            }

            $before = $multimediaObject->getProperties();
            $this->temporizedDecisionService->markReached($multimediaObject, $now);
            if ($before !== $multimediaObject->getProperties()) {
                ++$marked;
                $output->writeln(sprintf('%s %s', $dryRun ? 'Would mark' : 'Marked', $multimediaObject->getId()));
            }
        }

        if ($dryRun) {
            $this->documentManager->clear();
        } else {
            $this->documentManager->flush();
        }
        $output->writeln(sprintf('%d of %d temporized multimedia objects %s as already applied', $marked, count($multimediaObjects), $dryRun ? 'would be marked' : 'marked'));

        return Command::SUCCESS;
    }
}
