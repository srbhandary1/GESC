<?php

declare(strict_types=1);

namespace App\Entitlements;

use App\Authentication\Federated\FederatedAccount;
use App\Authentication\Federated\FederatedSignInService;
use App\Authentication\Password\PasswordAuthenticationFailed;
use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\Password\PasswordPolicyViolation;
use App\Entitlements\Exceptions\EntitlementDenied;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\DirectLearnerProvisioner;
use App\Tenancy\Exceptions\IdentityUnavailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;
use Throwable;

final readonly class PaidRegistrationMaterializer
{
    public const string IDENTITY_TYPE = 'paid_phone';

    public function __construct(
        private DirectLearnerProvisioner $directLearners,
        private PasswordCredentialService $passwords,
        private FederatedSignInService $federated,
    ) {}

    public function attachMembership(string $checkoutId, Request $request): void
    {
        $intent = DB::table('paid_registration_intents')
            ->where('checkout_id', $checkoutId)
            ->lockForUpdate()
            ->first();

        if (! $intent instanceof stdClass || $intent->consumed_at !== null) {
            return;
        }

        $phone = (string) $intent->phone;
        $existing = User::query()->where('username', $phone)->lockForUpdate()->first();
        $membershipId = $existing instanceof User
            ? $this->existingMembership($existing)
            : $this->createLearner($phone, (string) $intent->password_hash, $request);

        $provider = is_string($intent->federated_provider ?? null) && $intent->federated_provider !== ''
            ? $intent->federated_provider
            : null;
        $ciphertext = is_string($intent->federated_subject_ciphertext ?? null) && $intent->federated_subject_ciphertext !== ''
            ? $intent->federated_subject_ciphertext
            : null;

        if ($provider !== null && $ciphertext !== null) {
            try {
                $subject = Crypt::decryptString($ciphertext);
                $user = User::query()->where('username', $phone)->lockForUpdate()->first();
                if (! $user instanceof User) {
                    throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
                }
                $this->federated->link($user, new FederatedAccount($provider, $subject));
            } catch (Throwable) {
                throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
            }
        }

        DB::table('subscription_checkouts')->where('id', $checkoutId)->update([
            'learner_membership_id' => $membershipId,
            'updated_at' => Date::now(),
        ]);
        DB::table('paid_registration_intents')->where('checkout_id', $checkoutId)->update([
            'password_hash' => null,
            'federated_subject_ciphertext' => null,
            'consumed_at' => Date::now(),
            'updated_at' => Date::now(),
        ]);
    }

    private function existingMembership(User $user): string
    {
        $organizationId = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->value('id');
        $membershipId = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->getKey())
            ->where('membership_type', Membership::TYPE_LEARNER)
            ->where('status', Membership::STATUS_ACTIVE)
            ->value('id');

        if (! is_string($membershipId) || $membershipId === '') {
            throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
        }

        return $membershipId;
    }

    private function createLearner(string $phone, string $passwordHash, Request $request): string
    {
        try {
            $result = $this->directLearners->provisionVerifiedIdentity(
                self::IDENTITY_TYPE,
                $phone,
                null,
            );
            $this->passwords->storePreparedHash($result->user, $phone, $passwordHash, $request);
        } catch (IdentityUnavailable|PasswordPolicyViolation|PasswordAuthenticationFailed) {
            throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
        }

        return (string) $result->membership->getKey();
    }
}
