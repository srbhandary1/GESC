<?php

declare(strict_types=1);

namespace App\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;
use stdClass;

final class CurrentIdentityPresenter
{
    /**
     * @return array{
     *     user: array{id: string},
     *     current_membership: array{
     *         id: string,
     *         organization: array{id: string, name: string, kind: string},
     *         membership_type: string,
     *         status: string,
     *         role_keys: list<string>
     *     }|null,
     *     memberships: list<array{
     *         id: string,
     *         organization: array{id: string, name: string, kind: string},
     *         membership_type: string,
     *         status: string,
     *         role_keys: list<string>
     *     }>
     * }
     */
    public function forUser(
        User $user,
        ?string $selectedMembershipId,
    ): array {
        $memberships = $this->membershipsFor($user);
        $current = null;

        if ($selectedMembershipId !== null) {
            foreach ($memberships as $membership) {
                if (
                    hash_equals($membership['id'], $selectedMembershipId)
                    && $membership['status'] === 'active'
                ) {
                    $current = $membership;

                    break;
                }
            }
        } else {
            $activeMemberships = array_values(array_filter(
                $memberships,
                static fn (array $membership): bool => $membership['status'] === 'active',
            ));

            if (count($activeMemberships) === 1) {
                $current = $activeMemberships[0];
            }
        }

        return [
            'user' => ['id' => (string) $user->getKey()],
            'current_membership' => $current,
            'memberships' => $memberships,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     organization: array{id: string, name: string, kind: string},
     *     membership_type: string,
     *     status: string,
     *     role_keys: list<string>
     * }
     */
    public function membership(User $user, string $membershipId): array
    {
        foreach ($this->membershipsFor($user) as $membership) {
            if (hash_equals($membership['id'], $membershipId)) {
                return $membership;
            }
        }

        throw new LogicException('An accepted membership is unavailable.');
    }

    /**
     * @return list<array{
     *     id: string,
     *     organization: array{id: string, name: string, kind: string},
     *     membership_type: string,
     *     status: string,
     *     role_keys: list<string>
     * }>
     */
    private function membershipsFor(User $user): array
    {
        /** @var list<stdClass> $membershipRows */
        $membershipRows = DB::table('memberships')
            ->join(
                'organizations',
                'organizations.id',
                '=',
                'memberships.organization_id',
            )
            ->where('memberships.user_id', $user->getKey())
            ->where('organizations.status', 'active')
            ->orderBy('memberships.id')
            ->get([
                'memberships.id',
                'memberships.membership_type',
                'memberships.status',
                'memberships.organization_id',
                'organizations.name as organization_name',
                'organizations.kind as organization_kind',
            ])
            ->all();

        $membershipIds = array_map(
            fn (stdClass $row): string => $this->stringProperty($row, 'id'),
            $membershipRows,
        );
        $roleKeysByMembership = [];

        if ($membershipIds !== []) {
            /** @var list<stdClass> $roleRows */
            $roleRows = DB::table('membership_roles')
                ->join('roles', function ($join): void {
                    $join->on(
                        'roles.organization_id',
                        '=',
                        'membership_roles.organization_id',
                    )->on('roles.id', '=', 'membership_roles.role_id');
                })
                ->whereIn('membership_roles.membership_id', $membershipIds)
                ->orderBy('roles.key')
                ->get([
                    'membership_roles.membership_id',
                    'roles.key',
                ])
                ->all();

            foreach ($roleRows as $roleRow) {
                $membershipId = $this->stringProperty(
                    $roleRow,
                    'membership_id',
                );
                $roleKeysByMembership[$membershipId][] = $this->stringProperty(
                    $roleRow,
                    'key',
                );
            }
        }

        return array_map(
            function (stdClass $row) use ($roleKeysByMembership): array {
                $membershipId = $this->stringProperty($row, 'id');

                return [
                    'id' => $membershipId,
                    'organization' => [
                        'id' => $this->stringProperty(
                            $row,
                            'organization_id',
                        ),
                        'name' => $this->stringProperty(
                            $row,
                            'organization_name',
                        ),
                        'kind' => $this->stringProperty(
                            $row,
                            'organization_kind',
                        ),
                    ],
                    'membership_type' => $this->stringProperty(
                        $row,
                        'membership_type',
                    ),
                    'status' => $this->stringProperty($row, 'status'),
                    'role_keys' => $roleKeysByMembership[$membershipId] ?? [],
                ];
            },
            $membershipRows,
        );
    }

    private function stringProperty(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (! is_string($value)) {
            throw new LogicException('Identity summary data is incomplete.');
        }

        return $value;
    }
}
