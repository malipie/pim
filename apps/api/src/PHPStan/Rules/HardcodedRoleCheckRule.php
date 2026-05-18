<?php

declare(strict_types=1);

namespace App\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * RBAC-P1-010 Rule 3 — flags hardcoded role checks anywhere outside
 * Identity bundle Voters, where they would short-circuit the permission
 * resolution pipeline and silently bypass per-locale / per-channel /
 * per-attribute-group scope.
 *
 * Forbidden:
 *   - `$user->hasRole('ROLE_ADMIN')` — does not consult UserRole scope
 *   - `in_array('admin', $user->getRoles(), true)` — same, plus magic string
 *   - `$user->isAdmin()`, `$user->isOwner()` — bypasses Voter entirely
 *
 * Required (route through the Voter graph):
 *   - `$this->security->isGranted('products.edit', $product)`
 *   - `#[IsGranted('products.edit', subject: 'product')]`
 *
 * Why Identity/Infrastructure/Security/ is exempt: that is exactly where
 * Voters legitimately read role membership (the bottom of the pipeline).
 * Outside that directory, role checks must go through the Voter abstraction
 * so scope (locale / channel / attribute group) is consulted.
 *
 * @implements Rule<Node>
 */
final class HardcodedRoleCheckRule implements Rule
{
    private const array FORBIDDEN_METHODS = [
        'hasRole',
        'isAdmin',
        'isOwner',
        'isSuperAdmin',
    ];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Voters are the legitimate bottom of the permission pipeline —
        // role reading inside them is correct. Skip the whole Infrastructure/Security
        // tree to keep the rule's noise floor low.
        if (str_contains($file, '/Identity/Infrastructure/Security/')
            || str_contains($file, '/Identity/Domain/Rbac/')
            || str_contains($file, '/Identity/Application/RbacSeeder')) {
            return [];
        }

        // Tests and fixtures legitimately seed roles via array literals;
        // role-array introspection there is not a runtime hot path.
        if (str_contains($file, '/tests/')
            || str_contains($file, '/DataFixtures/')) {
            return [];
        }

        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $methodName = $node->name->toString();
            if (\in_array($methodName, self::FORBIDDEN_METHODS, true)) {
                return [
                    RuleErrorBuilder::message(\sprintf(
                        'Hardcoded role check via ->%s() bypasses the Voter pipeline and ignores UserRole scope '
                        .'(locale / channel / attribute_group). Route the check through Symfony Security: '
                        .'$this->security->isGranted(\'<module>.<action>\', $subject) or #[IsGranted(...)] attribute. '
                        .'(Voters in src/Identity/Infrastructure/Security/ are exempt — they are the bottom of the pipeline.)',
                        $methodName,
                    ))
                        ->identifier('rbac.hardcodedRoleCheck')
                        ->build(),
                ];
            }
        }

        // `in_array('admin', $user->getRoles(), true)` pattern.
        if ($node instanceof FuncCall
            && $node->name instanceof Name
            && $node->name->toString() === 'in_array'
            && isset($node->args[1])
            && $node->args[1] instanceof Arg
            && $node->args[1]->value instanceof MethodCall
            && $node->args[1]->value->name instanceof Identifier
            && $node->args[1]->value->name->toString() === 'getRoles') {
            return [
                RuleErrorBuilder::message(
                    'Hardcoded role check via in_array(..., $user->getRoles()) bypasses the Voter pipeline and ignores '
                    .'UserRole scope. Route the check through Symfony Security: '
                    .'$this->security->isGranted(\'<module>.<action>\', $subject) or #[IsGranted(...)] attribute.',
                )
                    ->identifier('rbac.hardcodedRoleCheck')
                    ->build(),
            ];
        }

        return [];
    }
}
