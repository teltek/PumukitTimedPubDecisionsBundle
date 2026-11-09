<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Pumukit\TimedPubDecisionsBundle\Routing\RouteLoader;

/**
 * @internal
 *
 * @coversNothing
 */
class RouteLoaderTest extends TestCase
{
    public function testNoRouteIsLoadedWhenItIsDisabled(): void
    {
        $this->assertCount(0, new RouteLoader(false)->load('@PumukitTimedPubDecisionsBundle/Controller/', RouteLoader::TYPE));
    }

    public function testItOnlySupportsItsType(): void
    {
        $loader = new RouteLoader(true);

        $this->assertTrue($loader->supports('@PumukitTimedPubDecisionsBundle/Controller/', RouteLoader::TYPE));
        $this->assertFalse($loader->supports('@PumukitTimedPubDecisionsBundle/Controller/', 'attribute'));
    }
}
