<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation;

/**
 * Lightweight value object describing a single validation failure.
 *
 * The catalog validator chain returns a list of these — the API layer
 * (#41) maps them onto RFC 7807 Problem Details `errors[]`, and the
 * admin UI (#56) renders them inline next to form fields.
 *
 * Why not Symfony's {@see \Symfony\Component\Validator\ConstraintViolation}?
 * The shape we need is a flat tuple — no metadata, no parameterised
 * messages, no localisation hooks (i18n stays in the UI layer). A
 * three-field DTO is cheaper than dragging the validator constraint
 * machinery through every per-type validator + lossless to translate
 * back to ConstraintViolation when API Platform asks for one in #41.
 */
final readonly class ValidationError
{
    public function __construct(
        public string $path,
        public string $code,
        public string $message,
    ) {
    }
}
