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

    public function test_every_operator_mutation_requires_fresh_password_confirmation(): void
    {
        foreach ($this->operatorRoutes() as $route) {
            if (
                array_intersect(self::MUTATING_METHODS, $route->methods())
                === []
            ) {
                continue;
            }

            self::assertContains(
                'auth.password-confirmed',
                $route->gatherMiddleware(),
                sprintf('%s must require fresh password confirmation.', $route->uri()),
            );
        }
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
