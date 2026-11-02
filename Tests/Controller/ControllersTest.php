<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\Tests\Controller;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\PermissionProfile;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\TimedPubDecisionsBundle\Tests\TimedPubDecisionsTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @coversNothing
 */
class ControllersTest extends TimedPubDecisionsTestCase
{
    private KernelBrowser $client;

    public function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->dm = self::getContainer()->get('doctrine_mongodb.odm.document_manager');
        $this->clearBBDD();
        $this->createFixtures();
    }

    public function testTheOptionsOfADecisionAreNotPublic(): void
    {
        $video = $this->temporized(MultimediaObject::STATUS_BLOCKED, 'PUDETV', '2026-11-02T10:00', '2026-11-09T10:00');

        $this->client->request('GET', '/admin/timedpubdecisions/option/'.$video->getId().'/PUDETV');
        $this->assertNotSame(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringNotContainsString('2026-11-02T10:00', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/pubchannel/option/'.$video->getId().'/PUDETV');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testTheOptionsOfADecisionAreShownToAnEditor(): void
    {
        $video = $this->temporized(MultimediaObject::STATUS_BLOCKED, 'PUDETV', '2026-11-02T10:00', '2026-11-09T10:00');
        $this->login(['ROLE_ACCESS_MULTIMEDIA_SERIES']);

        $this->client->request('GET', '/admin/timedpubdecisions/option/'.$video->getId().'/PUDETV');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('2026-11-02T10:00', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/admin/timedpubdecisions/option/'.$video->getId().'/PUDENEW');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testTheTimelineNeedsItsPermission(): void
    {
        $this->login(['ROLE_ACCESS_MULTIMEDIA_SERIES']);
        $this->client->request('GET', '/admin/timeframes/default');
        $this->assertResponseStatusCodeSame(403);

        $this->login(['ROLE_ACCESS_TIMEDPUBDECISIONS_TIMELINE'], 'timeline');
        $this->client->request('GET', '/admin/timeframes/default');
        $this->assertResponseIsSuccessful();
    }

    public function testThePortalOnlyListsTemporizedDecisions(): void
    {
        $this->client->request('GET', '/destacados/PUDENEW/');
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/destacados/PUDETV/');
        $this->assertResponseIsSuccessful();
    }

    private function login(array $roles, string $username = 'editor'): void
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username.'@example.com');
        $user->addRole(PermissionProfile::SCOPE_GLOBAL);
        foreach ($roles as $role) {
            $user->addRole($role);
        }
        $this->dm->persist($user);
        $this->dm->flush();

        $this->client->loginUser($user, 'pumukit');
    }
}
