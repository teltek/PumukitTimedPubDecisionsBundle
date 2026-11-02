<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Tests;

use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\TimedPubDecisionsBundle\Services\TemporizedDecisionService;

abstract class TimedPubDecisionsTestCase extends PumukitTestCase
{
    protected Series $series;

    public function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    protected function createFixtures(): void
    {
        $role = new Role();
        $role->setCod('owner');
        $this->dm->persist($role);

        $root = $this->tag('ROOT', 'ROOT|');
        $decisions = $this->tag('PUBDECISIONS', 'ROOT|PUBDECISIONS|', $root);
        $this->tag('PUDETV', 'ROOT|PUBDECISIONS|PUDETV|', $decisions, TemporizedDecisionService::ROUTE);
        $this->tag('PUDERADIO', 'ROOT|PUBDECISIONS|PUDERADIO|', $decisions, TemporizedDecisionService::ROUTE);
        $this->tag('PUDENEW', 'ROOT|PUBDECISIONS|PUDENEW|', $decisions);
        $channels = $this->tag('PUBCHANNELS', 'ROOT|PUBCHANNELS|', $root);
        $this->tag('PUCHWEBTV', 'ROOT|PUBCHANNELS|PUCHWEBTV|', $channels, TemporizedDecisionService::ROUTE);
        $this->dm->flush();

        $this->series = self::getContainer()->get(FactoryService::class)->createSeries();
    }

    protected function video(int $status, array $tags = [], array $properties = []): MultimediaObject
    {
        $multimediaObject = self::getContainer()->get(FactoryService::class)->createMultimediaObject($this->series);
        $multimediaObject->setStatus($status);
        foreach ($tags as $code) {
            $multimediaObject->addTag($this->dm->getRepository(Tag::class)->findOneBy(['cod' => $code]));
        }
        foreach ($properties as $key => $value) {
            $multimediaObject->setProperty($key, $value);
        }
        $this->dm->flush();

        return $multimediaObject;
    }

    protected function temporized(int $status, string $code, string $from, string $to, array $extra = []): MultimediaObject
    {
        return $this->video($status, [$code], [
            'temporized_'.$code => '1',
            'temporized_from_'.$code => $from,
            'temporized_to_'.$code => $to,
        ] + $extra);
    }

    protected function reload(MultimediaObject $multimediaObject): MultimediaObject
    {
        $this->dm->clear();

        return $this->dm->getRepository(MultimediaObject::class)->find($multimediaObject->getId());
    }

    private function tag(string $cod, string $path, ?Tag $parent = null, ?string $route = null): Tag
    {
        $tag = new Tag();
        $tag->setCod($cod);
        $tag->setPath($path);
        $tag->setTitle($cod, 'en');
        $tag->setDisplay(true);
        if ($parent) {
            $tag->setParent($parent);
        }
        if ($route) {
            $tag->setProperty('route', $route);
        }
        $this->dm->persist($tag);

        return $tag;
    }
}
