<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

final class AdminGovernanceRoutePolicyTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const array REQUIRED_OPERATOR_MIDDLEWARE = [
        'auth.first-party',
        'auth.web-only',
        'organization.context',
    ];

    /**
     * @var list<string>
     */
    private const array MUTATING_METHODS = [
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
    ];

    /**
     * Credential/recovery routes live outside the operator prefix. Within the
     * operator API, only durable administrator-authority changes retain
     * current-password confirmation under ADR-0023.
     *
     * @var list<string>
     */
    private const array PASSWORD_CONFIRMED_OPERATOR_ROUTES = [
        'api.v1.operator.institution-invites.revocations.store',
        'api.v1.operator.institution-invites.store',
        'api.v1.operator.memberships.deactivations.store',
        'api.v1.operator.memberships.reactivations.store',
        'api.v1.operator.memberships.role-assignments.store',
        'api.v1.operator.memberships.role-revocations.store',
        'api.v1.operator.roles.retirements.store',
        'api.v1.operator.roles.store',
        'api.v1.operator.roles.updates.store',
    ];

    public function test_every_operator_route_is_web_only_and_tenant_scoped(): void
    {
        $routes = $this->operatorRoutes();

        self::assertNotEmpty($routes);

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();

            foreach (self::REQUIRED_OPERATOR_MIDDLEWARE as $required) {
                self::assertContains(
                    $required,
                    $middleware,
                    sprintf('%s is missing %s.', $route->uri(), $required),
                );
            }

            self::assertIsString($route->getName());
            self::assertStringStartsWith(
                'api.v1.operator.',
                (string) $route->getName(),
            );
        }
    }

    public function test_only_administrator_authority_mutations_require_fresh_password_confirmation(): void
    {
        $protected = [];

        foreach ($this->operatorRoutes() as $route) {
            if (
                array_intersect(self::MUTATING_METHODS, $route->methods())
                === []
            ) {
                continue;
            }

            $name = (string) $route->getName();
            $requiresConfirmation = in_array(
                'auth.password-confirmed',
                $route->gatherMiddleware(),
                true,
            );

            if (in_array($name, self::PASSWORD_CONFIRMED_OPERATOR_ROUTES, true)) {
                self::assertTrue(
                    $requiresConfirmation,
                    sprintf('%s must require fresh password confirmation.', $route->uri()),
                );
                $protected[] = $name;

                continue;
            }

            self::assertFalse(
                $requiresConfirmation,
                sprintf('%s is routine role-authorized work and must not require repeated password confirmation.', $route->uri()),
            );
        }

        sort($protected);
        $expected = self::PASSWORD_CONFIRMED_OPERATOR_ROUTES;
        sort($expected);
        self::assertSame($expected, $protected);
    }

    public function test_operator_lifecycle_commands_do_not_use_http_delete(): void
    {
        foreach ($this->operatorRoutes() as $route) {
            self::assertNotContains(
                'DELETE',
                $route->methods(),
                sprintf(
                    '%s must use an explicit lifecycle command instead of generic deletion.',
                    $route->uri(),
                ),
            );
        }
    }

    /**
     * @return list<Route>
     */
    private function operatorRoutes(): array
    {
        $routes = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (
                $route->uri() === 'api/v1/operator'
                || str_starts_with($route->uri(), 'api/v1/operator/')
            ) {
                $routes[] = $route;
            }
        }

        return $routes;
    }
}
