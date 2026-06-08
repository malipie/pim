<?php

declare(strict_types=1);

namespace App\Tests\Api\Channel;

use App\Channel\Domain\Entity\ChannelPublicationProfile;
use App\Channel\Domain\Repository\ChannelPublicationProfileRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * #1232 — ChannelPublicationProfile entity integration:
 * on ChannelCreated, default publish-all profiles are auto-created
 * for each ObjectType belonging to the same tenant.
 */
final class ChannelPublicationProfileApiTest extends ChannelApiTestCase
{
    #[Test]
    public function creatingChannelProvisionesDefaultProfilesForAllObjectTypes(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/channels', [
            'json' => [
                'code' => 'pub_profile_test',
                'name' => 'Test',
            ],
        ]);
        self::assertSame(201, $response->getStatusCode());
        $channelId = $response->toArray()['id'];
        self::assertIsString($channelId);

        $repo = self::getContainer()->get(ChannelPublicationProfileRepositoryInterface::class);
        self::assertInstanceOf(ChannelPublicationProfileRepositoryInterface::class, $repo);

        $em = $this->em();
        $tenant = $em->getRepository(\App\Shared\Domain\Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        self::assertNotNull($tenant);
        $profiles = $repo->findForChannel(\Symfony\Component\Uid\Uuid::fromString($channelId), $tenant);

        self::assertNotEmpty($profiles, 'At least one default profile must be created per ObjectType.');

        foreach ($profiles as $profile) {
            self::assertInstanceOf(ChannelPublicationProfile::class, $profile);
            self::assertTrue($profile->isDefault(), 'Auto-created profile must be marked as default.');
            self::assertTrue($profile->isPublishAll(), 'Auto-created profile must be publish-all (null codes).');
        }
    }
}
