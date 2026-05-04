<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * VIEW-02 (#374) — POST input shape for `/api/attributes`. Mirrors the
 * AttributeGroupInput (#260) pattern: AP4 default Doctrine processor
 * cannot hydrate the Attribute aggregate's typed constructor + custom
 * setters with guards, so this DTO is the deserialisation target for
 * `AttributeProcessor`.
 *
 * Field naming follows the FE create form (see VIEW-02 ticket §3.4c):
 *   - code (snake_case, immutable post-create)
 *   - label (JSONB { pl, en, ... })
 *   - help (JSONB, optional)
 *   - type (one of 10 AttributeType enum values)
 *   - flags: localizable, scopable, required
 *   - validationRules (JSONB, e.g. { max_length: 280 })
 */
final class AttributeInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex('/^[a-z][a-z0-9_]{0,63}$/', message: 'Code must be snake_case starting with a lowercase letter.')]
    #[Groups(['attribute:create'])]
    public string $code = '';

    /**
     * @var array<string, string>
     */
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[Groups(['attribute:create'])]
    public array $label = [];

    /**
     * @var array<string, string>|null
     */
    #[Assert\Type('array')]
    #[Groups(['attribute:create'])]
    public ?array $help = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['text', 'number', 'select', 'multiselect', 'date', 'boolean', 'asset', 'relation', 'price', 'metric', 'wysiwyg'])]
    #[Groups(['attribute:create'])]
    public string $type = 'text';

    #[Groups(['attribute:create'])]
    public bool $localizable = false;

    #[Groups(['attribute:create'])]
    public bool $scopable = false;

    #[Groups(['attribute:create'])]
    public bool $required = false;

    /**
     * @var array<string, mixed>
     */
    #[Assert\Type('array')]
    #[Groups(['attribute:create'])]
    public array $validationRules = [];

    #[Assert\PositiveOrZero]
    #[Groups(['attribute:create'])]
    public int $position = 0;

    /**
     * VIEW-03 (#375) — popup „Stwórz nowy" in AttributeGroup detail
     * (groups-categories.jsx:813–953) creates the attribute and
     * attaches it to one or more groups atomically in the same request.
     * Each entry is an AttributeGroup code (not UUID, FE-friendly).
     * Unknown codes return 422 from the handler.
     *
     * Empty array (default) keeps the legacy POST behaviour from #381 —
     * attribute lands in the global library only.
     *
     * @var list<string>
     */
    #[Assert\Type('array')]
    #[Groups(['attribute:create'])]
    public array $attachToGroups = [];
}
