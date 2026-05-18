<?php

declare(strict_types=1);

namespace App\PHPStan\Rules;

use App\Identity\Domain\Attribute\NoPermissionRequired;
use App\Identity\Domain\Attribute\RequiresPermission;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RBAC-P1-010 Rule 1 — every controller action mapped by Symfony's
 * `#[Route]` must declare either `#[RequiresPermission]` (positive
 * permission gating) or `#[NoPermissionRequired]` (explicit opt-out
 * for public auth / webhook / probe routes).
 *
 * Why static enforcement: the runtime EndpointGuardListener (Phase 3 #664)
 * trusts the attribute to be present. A typo or forgotten annotation
 * yields a silently-public endpoint. Catching the omission at PHPStan
 * level means a missed annotation breaks CI, not production.
 *
 * Baseline strategy for the Phase 1 retrofit window:
 *   The 60+ pre-RBAC controllers are grandfathered via phpstan-baseline.neon
 *   so this rule does not block the PR introducing it. Phase 6 (#714-#717)
 *   walks each baseline entry, adds the proper attribute, and removes
 *   it from the baseline — the rule then catches new regressions.
 *
 * @implements Rule<ClassMethod>
 */
final class RequiresPermissionAnnotationRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->isPublic()) {
            return [];
        }

        if (!$this->hasAttribute($node, Route::class)) {
            return [];
        }

        if ($this->hasAttribute($node, RequiresPermission::class)
            || $this->hasAttribute($node, NoPermissionRequired::class)) {
            return [];
        }

        $methodName = $node->name->toString();
        $className = $scope->getClassReflection()?->getName() ?? '(unknown)';

        return [
            RuleErrorBuilder::message(\sprintf(
                'Controller action %s::%s() carries #[Route] but no permission attribute. '
                .'Add #[RequiresPermission(module: ..., action: ...)] for a permission-gated endpoint, '
                .'or #[NoPermissionRequired(reason: ...)] for a public / probe / webhook endpoint.',
                $className,
                $methodName,
            ))
                ->identifier('rbac.missingPermissionAttribute')
                ->build(),
        ];
    }

    private function hasAttribute(ClassMethod $node, string $attributeFqcn): bool
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->toString() === $attributeFqcn) {
                    return true;
                }
                // Symfony's Route attribute is also re-exported under
                // `Symfony\Component\Routing\Annotation\Route` (legacy
                // alias kept by the bundle); accept short name match as
                // well, as PHPStan emits the resolved FQCN per the
                // imported `use` statement.
                if (str_ends_with($attribute->name->toString(), '\\'.\basename(\str_replace('\\', '/', $attributeFqcn)))) {
                    return true;
                }
                if ($attribute->name->toString() === \basename(\str_replace('\\', '/', $attributeFqcn))) {
                    return true;
                }
            }
        }

        return false;
    }
}
