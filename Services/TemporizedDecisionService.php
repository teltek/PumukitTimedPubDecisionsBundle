<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Event\MultimediaObjectEvent;
use Pumukit\SchemaBundle\Event\SchemaEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class TemporizedDecisionService
{
    public const ROUTE = 'pumukit_timed_pub_decisions_index';
    public const DATE_FORMAT = 'Y-m-d\TH:i';
    public const TEMPORARY = '1';
    public const PERMANENT = '-1';
    private const INPUT_FORMATS = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'];
    private const UNPUBLISHED = [MultimediaObject::STATUS_BLOCKED, MultimediaObject::STATUS_HIDDEN];
    private const CHECKED = [MultimediaObject::STATUS_PUBLISHED, MultimediaObject::STATUS_BLOCKED, MultimediaObject::STATUS_HIDDEN];

    private ?array $tags = null;

    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function tags(): array
    {
        return $this->tags ??= array_values(array_filter(
            $this->documentManager->getRepository(Tag::class)->findBy(['properties.route' => self::ROUTE]),
            static fn (Tag $tag): bool => $tag->isDescendantOfByCod('PUBDECISIONS'),
        ));
    }

    public function tagCodes(): array
    {
        return array_map(static fn (Tag $tag): string => $tag->getCod(), $this->tags());
    }

    public function isTemporized(string $code): bool
    {
        return in_array($code, $this->tagCodes(), true);
    }

    public static function normalize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        foreach (self::INPUT_FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, trim($value));
            if (false !== $date && $date->format($format) === trim($value)) {
                return $date->format(self::DATE_FORMAT);
            }
        }

        return null;
    }

    public function applyDecision(MultimediaObject $multimediaObject, string $code, ?string $option, mixed $from, mixed $to): void
    {
        if (self::TEMPORARY !== $option) {
            if (self::PERMANENT === $option) {
                $this->clear($multimediaObject, $code);
            }

            return;
        }

        $from = self::normalize($from);
        $to = self::normalize($to);
        if (null === $from || null === $to) {
            throw new \InvalidArgumentException(sprintf('The dates of the temporized decision %s are not valid', $code));
        }
        if ($from > $to) {
            throw new \InvalidArgumentException(sprintf('The start of the temporized decision %s is after its end', $code));
        }

        if (self::TEMPORARY !== $multimediaObject->getProperty('temporized_'.$code) || self::normalize($multimediaObject->getProperty('temporized_from_'.$code)) !== $from) {
            $multimediaObject->removeProperty('temporized_published_'.$code);
        }
        $multimediaObject->setProperty('temporized_'.$code, self::TEMPORARY);
        $multimediaObject->setProperty('temporized_from_'.$code, $from);
        $multimediaObject->setProperty('temporized_to_'.$code, $to);
    }

    public function clear(MultimediaObject $multimediaObject, string $code): void
    {
        foreach (['temporized_', 'temporized_from_', 'temporized_to_', 'temporized_published_'] as $prefix) {
            $multimediaObject->removeProperty($prefix.$code);
        }
    }

    public function isActive(MultimediaObject $multimediaObject, string $code, \DateTimeImmutable $now): bool
    {
        if (self::TEMPORARY !== $multimediaObject->getProperty('temporized_'.$code)) {
            return true;
        }

        $current = $now->format(self::DATE_FORMAT);
        $from = self::normalize($multimediaObject->getProperty('temporized_from_'.$code));
        $to = self::normalize($multimediaObject->getProperty('temporized_to_'.$code));

        return null !== $from && null !== $to && $from <= $current && $current <= $to;
    }

    public function due(\DateTimeImmutable $now): array
    {
        $due = [];
        foreach ($this->tagCodes() as $code) {
            $query = $this->documentManager->createQueryBuilder(MultimediaObject::class)
                ->field('status')->in(self::CHECKED)
                ->field('tags.cod')->equals($code)
                ->field('properties.temporized_'.$code)->equals(self::TEMPORARY)
                ->field('properties.temporized_from_'.$code)->lte($now->format(self::DATE_FORMAT))
                ->field('properties.temporized_published_'.$code)->exists(false)
                ->getQuery()
            ;
            foreach ($query->execute() as $multimediaObject) {
                $due[$multimediaObject->getId()] = $multimediaObject;
            }
        }

        return array_values($due);
    }

    public function publishDue(\DateTimeImmutable $now, bool $dryRun = false): array
    {
        $published = [];
        foreach ($this->due($now) as $multimediaObject) {
            $publish = in_array($multimediaObject->getStatus(), self::UNPUBLISHED, true);
            if ($publish) {
                $published[] = $multimediaObject;
            }
            if ($dryRun) {
                continue;
            }

            $this->markReached($multimediaObject, $now);
            if ($publish) {
                $multimediaObject->setStatus(MultimediaObject::STATUS_PUBLISHED);
            }
            $this->documentManager->flush();

            if ($publish) {
                $this->eventDispatcher->dispatch(new MultimediaObjectEvent($multimediaObject), SchemaEvents::MULTIMEDIAOBJECT_UPDATE);
                $this->logger->info('Timed publication: multimedia object published', ['id' => $multimediaObject->getId()]);
            }
        }

        return $published;
    }

    public function markReached(MultimediaObject $multimediaObject, \DateTimeImmutable $now): void
    {
        $current = $now->format(self::DATE_FORMAT);
        foreach ($this->tagCodes() as $code) {
            $from = self::normalize($multimediaObject->getProperty('temporized_from_'.$code));
            if ($multimediaObject->containsTagWithCod($code)
                && self::TEMPORARY === $multimediaObject->getProperty('temporized_'.$code)
                && null !== $from && $from <= $current
                && null === $multimediaObject->getProperty('temporized_published_'.$code)) {
                $multimediaObject->setProperty('temporized_published_'.$code, $current);
            }
        }
    }
}
