<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Asset\Domain\Entity\Asset;
use App\Asset\Domain\Entity\AssetVariant;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeOption;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Provenance;
use App\Catalog\Infrastructure\Doctrine\Repository\AttributeRepository;
use App\Catalog\Infrastructure\Doctrine\Repository\CatalogObjectRepository;
use App\Catalog\Infrastructure\Doctrine\Repository\ObjectTypeRepository;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Idempotent demo dataset seeder for ticket #40 (0.3.10).
 *
 * Builds a representative catalog inside one tenant: ~20 attributes
 * covering all 10 AttributeType cases, 100 products, 5 categories with
 * own user-defined attributes (`seo_title`, `seo_description`,
 * `main_image` — proof that ADR-009 makes Category a first-class
 * ObjectType with its own schema, not a Product appendage), and 10 assets.
 *
 * Bulk path: {@see BulkContext} flips ON to bypass the synchronous
 * `AttributesIndexedSyncListener` (#38) — `attributes_indexed` is built
 * inline alongside the canonical `object_values` rows so the cache
 * matches the source of truth without forcing 1000+ listener walks.
 *
 * Idempotent: re-runs are no-ops once a CatalogObject with code
 * `DEMO-100` exists (the last product seeded — its presence proves the
 * full set went through). Foundry / fixtures may run this many times
 * during a `pim:db:reset`; the early-exit keeps it fast.
 */
final readonly class DemoCatalogSeeder
{
    private const string SENTINEL_PRODUCT_CODE = 'DEMO-100';
    private const int PRODUCT_COUNT = 100;

    public function __construct(
        private EntityManagerInterface $em,
        private TenantContext $tenantContext,
        private BulkContext $bulkContext,
        private ObjectTypeRepository $objectTypeRepository,
        private AttributeRepository $attributeRepository,
        private CatalogObjectRepository $catalogObjectRepository,
    ) {
    }

    public function seed(Tenant $tenant): void
    {
        $previous = $this->tenantContext->get();
        $this->tenantContext->set($tenant);
        $this->bulkContext->setBulk(true);

        try {
            if (null !== $this->catalogObjectRepository->findByCode(self::SENTINEL_PRODUCT_CODE, ObjectKind::Product, $tenant)) {
                return;
            }

            $productType = $this->objectTypeRepository->findBuiltInByKind(ObjectKind::Product, $tenant);
            $categoryType = $this->objectTypeRepository->findBuiltInByKind(ObjectKind::Category, $tenant);
            $assetType = $this->objectTypeRepository->findBuiltInByKind(ObjectKind::Asset, $tenant);
            \assert(null !== $productType && null !== $categoryType && null !== $assetType, 'Built-in ObjectTypes must be seeded before DemoCatalogSeeder runs.');

            $attributes = $this->seedAttributes($tenant);
            $this->seedJunctions($productType, $categoryType, $assetType, $attributes);
            $productType->setLabelAttribute($attributes['name']);
            $productType->setImageAttribute($attributes['main_image']);
            $productType->setCompletenessRules(['required' => ['sku', 'name', 'description', 'price']]);
            $categoryType->setLabelAttribute($attributes['name']);
            $categoryType->setImageAttribute($attributes['main_image']);
            $categoryType->setCompletenessRules(['required' => ['name', 'seo_title']]);
            $assetType->setLabelAttribute($attributes['name']);
            $this->em->flush();

            $categories = $this->seedCategories($categoryType, $attributes);
            $this->em->flush();

            $assets = $this->seedAssets($assetType, $tenant, $attributes);
            $this->em->flush();

            $this->seedProducts($productType, $categories, $assets, $attributes);
            $this->em->flush();
        } finally {
            $this->bulkContext->setBulk(false);
            if (null === $previous) {
                $this->tenantContext->clear();
            } else {
                $this->tenantContext->set($previous);
            }
        }
    }

    /**
     * @return array<string, Attribute>
     */
    private function seedAttributes(Tenant $tenant): array
    {
        $defs = self::attributeDefinitions();
        $attributes = [];
        $position = 0;
        foreach ($defs as $code => $def) {
            $existing = $this->attributeRepository->findByCode($code, $tenant);
            if (null !== $existing) {
                $attributes[$code] = $existing;
                continue;
            }
            $attribute = new Attribute($code, $def['label'], $def['type']);
            $attribute->setRequired($def['required'] ?? false);
            $attribute->setLocalizable($def['localizable'] ?? false);
            $attribute->setValidationRules($def['rules'] ?? []);
            $attribute->setPosition($position++);
            $this->em->persist($attribute);
            $attributes[$code] = $attribute;

            foreach ($def['options'] ?? [] as $optPosition => [$optCode, $optLabel]) {
                $option = new AttributeOption($attribute, $optCode, $optLabel, $optPosition);
                $this->em->persist($option);
            }
        }
        $this->em->flush();

        return $attributes;
    }

    /**
     * @param array<string, Attribute> $attributes
     */
    private function seedJunctions(ObjectType $product, ObjectType $category, ObjectType $asset, array $attributes): void
    {
        $productAttrs = ['name', 'sku', 'description', 'short_description', 'brand', 'color', 'size', 'tags', 'price', 'weight', 'height', 'in_stock', 'release_date', 'main_image', 'related_to'];
        $categoryAttrs = ['name', 'seo_title', 'seo_description', 'main_image'];
        $assetAttrs = ['name', 'alt_text', 'caption'];

        $sort = 0;
        foreach ($productAttrs as $code) {
            $required = \in_array($code, ['sku', 'name', 'description', 'price'], true);
            $junction = new ObjectTypeAttribute($product, $attributes[$code], $required, $sort++);
            $this->em->persist($junction);
        }
        $sort = 0;
        foreach ($categoryAttrs as $code) {
            $required = \in_array($code, ['name', 'seo_title'], true);
            $junction = new ObjectTypeAttribute($category, $attributes[$code], $required, $sort++);
            $this->em->persist($junction);
        }
        $sort = 0;
        foreach ($assetAttrs as $code) {
            $required = 'name' === $code;
            $junction = new ObjectTypeAttribute($asset, $attributes[$code], $required, $sort++);
            $this->em->persist($junction);
        }
    }

    /**
     * @param array<string, Attribute> $attributes
     *
     * @return list<CatalogObject>
     */
    private function seedCategories(ObjectType $type, array $attributes): array
    {
        $defs = [
            ['CAT-FOOTWEAR', 'footwear',  'Obuwie',         'Buty męskie i damskie'],
            ['CAT-APPAREL',  'apparel',   'Odzież',         'Ubrania i akcesoria'],
            ['CAT-OUTDOOR',  'outdoor',   'Outdoor',        'Sprzęt turystyczny'],
            ['CAT-RUNNING',  'running',   'Bieganie',       'Sprzęt biegowy'],
            ['CAT-SALE',     'sale',      'Wyprzedaż',      'Promocje sezonowe'],
        ];

        $categories = [];
        foreach ($defs as [$code, $path, $name, $description]) {
            $category = new CatalogObject($type, $code);
            $category->setStatus(CatalogObject::STATUS_PUBLISHED);
            $category->setPath($path);

            $values = [
                ['name', ['value' => $name]],
                ['seo_title', ['value' => $name.' — sklep PIM Demo']],
                ['seo_description', ['value' => $description]],
            ];

            $indexed = [];
            foreach ($values as [$attrCode, $payload]) {
                $value = new ObjectValue($category, $attributes[$attrCode], $payload, Provenance::Import);
                $this->em->persist($value);
                $indexed[$attrCode] = $payload;
            }
            $category->setAttributesIndexed($indexed);
            $category->setCompleteness(['global' => 100]);
            $this->em->persist($category);
            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * @param array<string, Attribute> $attributes
     *
     * @return list<Asset>
     */
    private function seedAssets(ObjectType $type, Tenant $tenant, array $attributes): array
    {
        $assets = [];
        for ($i = 1; $i <= 10; ++$i) {
            $code = \sprintf('ASSET-%03d', $i);
            $catalogAsset = new CatalogObject($type, $code);
            $catalogAsset->setStatus(CatalogObject::STATUS_PUBLISHED);

            $name = \sprintf('Demo image %d', $i);
            $alt = \sprintf('Image alt text #%d', $i);

            $value = new ObjectValue($catalogAsset, $attributes['name'], ['value' => $name], Provenance::Import);
            $this->em->persist($value);
            $valueAlt = new ObjectValue($catalogAsset, $attributes['alt_text'], ['value' => $alt], Provenance::Import);
            $this->em->persist($valueAlt);

            $catalogAsset->setAttributesIndexed([
                'name' => ['value' => $name],
                'alt_text' => ['value' => $alt],
            ]);
            $catalogAsset->setCompleteness(['global' => 100]);
            $this->em->persist($catalogAsset);

            $storagePath = \sprintf(
                '%s/demo/%s.jpg',
                $tenant->getId()->toRfc4122(),
                $code,
            );
            $asset = new Asset(
                code: $code,
                originalFilename: \sprintf('%s.jpg', $code),
                mimeType: 'image/jpeg',
                size: 1024 * 12,
                storagePath: $storagePath,
            );
            $asset->setObject($catalogAsset);
            $variant = new AssetVariant($asset, AssetVariant::CODE_ORIGINAL, $storagePath, 'image/jpeg', 1024 * 12);
            $asset->addVariant($variant);
            $this->em->persist($asset);
            $this->em->persist($variant);
            $assets[] = $asset;
        }

        return $assets;
    }

    /**
     * @param array<string, Attribute> $attributes
     * @param list<CatalogObject>      $categories
     * @param list<Asset>              $assets
     */
    private function seedProducts(ObjectType $type, array $categories, array $assets, array $attributes): void
    {
        $brands = ['Acme', 'Globex', 'Soylent', 'Initech', 'Hooli'];
        $colors = ['red', 'green', 'blue', 'black', 'white'];
        $sizes = ['XS', 'S', 'M', 'L', 'XL'];
        $tagSets = [['new'], ['sale', 'eco'], ['premium'], ['new', 'premium'], ['sale']];
        $currencies = ['PLN', 'EUR', 'USD'];
        $units = ['kg', 'g', 'cm'];

        for ($i = 1; $i <= self::PRODUCT_COUNT; ++$i) {
            $sku = \sprintf('DEMO-%03d', $i);
            $product = new CatalogObject($type, $sku);
            $product->setStatus(0 === $i % 11 ? CatalogObject::STATUS_DRAFT : CatalogObject::STATUS_PUBLISHED);

            $brand = $brands[$i % \count($brands)];
            $color = $colors[$i % \count($colors)];
            $size = $sizes[$i % \count($sizes)];
            $tags = $tagSets[$i % \count($tagSets)];
            $currency = $currencies[$i % \count($currencies)];
            $unit = $units[$i % \count($units)];
            $assetRow = $assets[$i % \count($assets)];
            $category = $categories[$i % \count($categories)];

            $payloads = [
                'sku' => ['value' => $sku],
                'name' => ['value' => \sprintf('Demo product %03d', $i)],
                'description' => ['value' => \sprintf('Long-form description for SKU %s, brand %s.', $sku, $brand)],
                'short_description' => ['value' => \sprintf('Short tagline for %s.', $sku)],
                'brand' => ['value' => $brand],
                'color' => ['option_code' => $color],
                'size' => ['option_code' => $size],
                'tags' => ['option_codes' => $tags],
                'price' => ['amount' => 19.99 + $i, 'currency' => $currency],
                'weight' => ['value' => round(0.2 + ($i * 0.1), 2), 'unit' => $unit],
                'height' => ['value' => 10 + $i],
                'in_stock' => ['value' => 0 !== $i % 7],
                'release_date' => ['value' => \sprintf('2026-%02d-15', 1 + ($i % 12))],
                'main_image' => ['asset_id' => $assetRow->getId()->toRfc4122()],
                'related_to' => ['object_id' => $category->getId()->toRfc4122()],
            ];

            $indexed = [];
            foreach ($payloads as $code => $payload) {
                $value = new ObjectValue($product, $attributes[$code], $payload, Provenance::Import);
                $this->em->persist($value);
                $indexed[$code] = $payload;
            }
            $product->setAttributesIndexed($indexed);
            $product->setCompleteness(['global' => 100]);
            $this->em->persist($product);
        }
    }

    /**
     * @return array<string, array{label: array<string, string>, type: AttributeType, required?: bool, localizable?: bool, rules?: array<string, mixed>, options?: list<array{0: string, 1: array<string, string>}>}>
     */
    private static function attributeDefinitions(): array
    {
        return [
            'name' => [
                'label' => ['pl' => 'Nazwa', 'en' => 'Name'],
                'type' => AttributeType::Text,
                'required' => true,
                'localizable' => true,
                'rules' => ['max_length' => 255],
            ],
            'sku' => [
                'label' => ['pl' => 'SKU', 'en' => 'SKU'],
                'type' => AttributeType::Text,
                'required' => true,
                'rules' => ['pattern' => '/^[A-Z0-9-]+$/'],
            ],
            'description' => [
                'label' => ['pl' => 'Opis', 'en' => 'Description'],
                'type' => AttributeType::Text,
                'localizable' => true,
            ],
            'short_description' => [
                'label' => ['pl' => 'Krótki opis', 'en' => 'Short description'],
                'type' => AttributeType::Text,
                'localizable' => true,
                'rules' => ['max_length' => 280],
            ],
            'brand' => [
                'label' => ['pl' => 'Marka', 'en' => 'Brand'],
                'type' => AttributeType::Text,
            ],
            'color' => [
                'label' => ['pl' => 'Kolor', 'en' => 'Color'],
                'type' => AttributeType::Select,
                'options' => [
                    ['red', ['pl' => 'Czerwony', 'en' => 'Red']],
                    ['green', ['pl' => 'Zielony', 'en' => 'Green']],
                    ['blue', ['pl' => 'Niebieski', 'en' => 'Blue']],
                    ['black', ['pl' => 'Czarny', 'en' => 'Black']],
                    ['white', ['pl' => 'Biały', 'en' => 'White']],
                ],
            ],
            'size' => [
                'label' => ['pl' => 'Rozmiar', 'en' => 'Size'],
                'type' => AttributeType::Select,
                'options' => [
                    ['XS', ['pl' => 'XS', 'en' => 'XS']],
                    ['S', ['pl' => 'S', 'en' => 'S']],
                    ['M', ['pl' => 'M', 'en' => 'M']],
                    ['L', ['pl' => 'L', 'en' => 'L']],
                    ['XL', ['pl' => 'XL', 'en' => 'XL']],
                ],
            ],
            'tags' => [
                'label' => ['pl' => 'Tagi', 'en' => 'Tags'],
                'type' => AttributeType::Multiselect,
                'rules' => ['max_count' => 5],
                'options' => [
                    ['new', ['pl' => 'Nowość', 'en' => 'New']],
                    ['sale', ['pl' => 'Promocja', 'en' => 'Sale']],
                    ['eco', ['pl' => 'Eko', 'en' => 'Eco']],
                    ['premium', ['pl' => 'Premium', 'en' => 'Premium']],
                ],
            ],
            'price' => [
                'label' => ['pl' => 'Cena', 'en' => 'Price'],
                'type' => AttributeType::Price,
                'rules' => ['min_amount' => 0, 'currencies' => ['PLN', 'EUR', 'USD']],
            ],
            'weight' => [
                'label' => ['pl' => 'Waga', 'en' => 'Weight'],
                'type' => AttributeType::Metric,
                'rules' => ['units' => ['kg', 'g', 'cm'], 'min' => 0],
            ],
            'height' => [
                'label' => ['pl' => 'Wysokość', 'en' => 'Height'],
                'type' => AttributeType::Number,
                'rules' => ['min' => 0],
            ],
            'in_stock' => [
                'label' => ['pl' => 'Na stanie', 'en' => 'In stock'],
                'type' => AttributeType::Boolean,
            ],
            'release_date' => [
                'label' => ['pl' => 'Data premiery', 'en' => 'Release date'],
                'type' => AttributeType::Date,
            ],
            'main_image' => [
                'label' => ['pl' => 'Zdjęcie główne', 'en' => 'Main image'],
                'type' => AttributeType::Asset,
            ],
            'related_to' => [
                'label' => ['pl' => 'Powiązane', 'en' => 'Related'],
                'type' => AttributeType::Relation,
            ],
            'seo_title' => [
                'label' => ['pl' => 'SEO tytuł', 'en' => 'SEO title'],
                'type' => AttributeType::Text,
                'localizable' => true,
                'rules' => ['max_length' => 70],
            ],
            'seo_description' => [
                'label' => ['pl' => 'SEO opis', 'en' => 'SEO description'],
                'type' => AttributeType::Text,
                'localizable' => true,
                'rules' => ['max_length' => 160],
            ],
            'alt_text' => [
                'label' => ['pl' => 'Tekst alternatywny', 'en' => 'Alt text'],
                'type' => AttributeType::Text,
                'localizable' => true,
                'rules' => ['max_length' => 255],
            ],
            'caption' => [
                'label' => ['pl' => 'Podpis', 'en' => 'Caption'],
                'type' => AttributeType::Text,
                'localizable' => true,
                'rules' => ['max_length' => 255],
            ],
        ];
    }
}
