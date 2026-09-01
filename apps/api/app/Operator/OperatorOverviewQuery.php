<?php

declare(strict_types=1);

namespace App\Operator;

use App\Models\User;
use App\Operator\Exceptions\OperatorShellRejected;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class OperatorOverviewQuery
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OperatorShellAuthorization $authorization,
    ) {}

    /**
     * @return array{
     *     generated_at: string,
     *     organization: array{id: string, name: string, timezone: string},
     *     navigation: list<string>,
     *     panels: list<string>,
     *     search_available: bool
     * }
     */
    public function show(User $actor): array
    {
        $access = $this->authorization->access($actor);
        $organizationId = $this->contexts->current()->organizationId;
        $organization = DB::table('organizations')
            ->where('id', $organizationId)
            ->where('status', 'active')
            ->first(['id', 'name', 'timezone']);

        if (! $organization instanceof stdClass) {
            throw new OperatorShellRejected;
        }

        $navigation = ['overview'];
        $panels = [];

        $dedicatedPeoplePanels = array_values(array_filter(
            $access['people_panels'],
            static fn (string $panel): bool => $panel !== 'organization_settings',
        ));

        if ($dedicatedPeoplePanels !== []) {
            $navigation[] = 'people';
        }

        if ($access['learner_panels'] !== []) {
            $navigation[] = 'learners';
        }

        if ($access['entitlement_panels'] !== []) {
            $navigation[] = 'entitlements';
        }

        if ($access['content'] || $access['media']) {
            $navigation[] = 'learning';
        }

        if ($access['support']) {
            $navigation[] = 'operations';
        }

        if ($access['audit']) {
            $navigation[] = 'safety';
        }

        if ($access['content'] || $access['audit']) {
            $panels[] = 'pilot_analytics';
        }

        if ($access['content']) {
            $panels[] = 'content_studio';
            $panels[] = 'review_queue';
            $panels[] = 'publish_status';
        }

        if ($access['media']) {
            $panels[] = 'media_library';
        }

        if ($access['support']) {
            $panels[] = 'support_diagnostics';
        }

        if ($access['audit']) {
            $panels[] = 'audit';
        }

        array_push($panels, ...$access['people_panels']);
        array_push($panels, ...$access['learner_panels']);
        array_push($panels, ...$access['entitlement_panels']);

        return [
            'generated_at' => now()->toIso8601String(),
            'organization' => [
                'id' => $this->string($organization, 'id'),
                'name' => $this->string($organization, 'name'),
                'timezone' => $this->string($organization, 'timezone'),
            ],
            'navigation' => $navigation,
            'panels' => $panels,
            'search_available' => $access['search_available'],
        ];
    }

    private function string(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) ? $value : '';
    }
}
