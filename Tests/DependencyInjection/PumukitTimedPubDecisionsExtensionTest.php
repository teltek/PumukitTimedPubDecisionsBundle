<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\TimedPubDecisionsBundle\Command\PublishCommand;
use Pumukit\TimedPubDecisionsBundle\Controller\AdminController;
use Pumukit\TimedPubDecisionsBundle\DependencyInjection\PumukitTimedPubDecisionsExtension;
use Pumukit\TimedPubDecisionsBundle\EventListener\BackofficeListener;
use Pumukit\TimedPubDecisionsBundle\Routing\RouteLoader;
use Pumukit\TimedPubDecisionsBundle\Services\MenuService;
use Pumukit\TimedPubDecisionsBundle\Services\TemporizedDecisionService;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
class PumukitTimedPubDecisionsExtensionTest extends TestCase
{
    public function testTheDisabledBundleOnlyRegistersTheRouteLoader(): void
    {
        $container = $this->load(['enable' => false]);

        $this->assertSame([RouteLoader::class], $this->timedPubDecisionsServices($container));
        $this->assertSame('%pumukit_timed_pub_decisions.enable%', $container->getDefinition(RouteLoader::class)->getArgument('$enabled'));
        $this->assertTrue($container->getDefinition(RouteLoader::class)->hasTag('routing.loader'));
        $this->assertSame([], $container->getParameter('pumukitschema.external_permissions'));
    }

    public function testTheEnabledBundleRegistersItsServicesAndThePermission(): void
    {
        $container = $this->load([]);

        foreach ([AdminController::class, PublishCommand::class, TemporizedDecisionService::class, BackofficeListener::class, MenuService::class, RouteLoader::class] as $id) {
            $this->assertTrue($container->hasDefinition($id), $id);
        }
        $this->assertSame([['role' => 'ROLE_ACCESS_TIMEDPUBDECISIONS_TIMELINE', 'description' => 'Access timedpubdecisions timeline']], $container->getParameter('pumukitschema.external_permissions'));
    }

    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('pumukitschema.external_permissions', []);
        new PumukitTimedPubDecisionsExtension()->load([$config], $container);

        return $container;
    }

    private function timedPubDecisionsServices(ContainerBuilder $container): array
    {
        return array_values(array_filter(array_keys($container->getDefinitions()), static fn (string $id): bool => str_starts_with($id, 'Pumukit\TimedPubDecisionsBundle\\')));
    }
}
