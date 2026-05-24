<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform;

use App\Catalog\Domain\Entity\CatalogObject;
use ArrayObject;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * MODR-10 (#932) follow-up — surfaces the Doctrine `@Version` field on
 * GET `/api/{products|categories|assets}/{id}` responses.
 *
 * ApiPlatform 4's PropertyInfo metadata factory silently skips
 * properties mapped with Doctrine `version="true"` from its
 * normalization metadata, so the integer counter that drives the
 * optimistic-lock guard never reaches the client unless we splice it
 * in ourselves. This normalizer wraps the default chain and appends
 * `version` to every `CatalogObject` response.
 *
 * Frontend (RelationInlineEditPanel) already prefers the lighter
 * `/api/objects/summaries` batch endpoint for fetching `version`; this
 * normalizer is the canonical path — anyone reading
 * `/api/products/{id}` directly gets the same field.
 */
#[AutoconfigureTag('serializer.normalizer', ['priority' => -50])]
final class CatalogObjectVersionNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string SENTINEL = '__catalog_object_version_normalizer_already_called';

    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): ArrayObject|array|string|int|float|bool|null {
        $context[self::SENTINEL] = true;
        $payload = $this->normalizer->normalize($data, $format, $context);

        if (!\is_array($payload) || !$data instanceof CatalogObject) {
            return $payload;
        }

        $payload['version'] = $data->getVersion();

        return $payload;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (true === ($context[self::SENTINEL] ?? false)) {
            return false;
        }

        return $data instanceof CatalogObject;
    }

    /**
     * @return array<string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [CatalogObject::class => false];
    }
}
