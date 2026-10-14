<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Capacity\ConversationCapacityAdmission;
use App\Conversation\Capacity\ConversationCapacityUnavailable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class ConversationCapacityAdmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('conversation.capacity', ['enabled' => true, 'store' => 'array', 'model_rpm' => 3,
            'model_tpm' => 3000, 'reserved_tokens_per_turn' => 2000, 'learner_rpm' => 6]);
        Cache::store('array')->flush();
        $this->freezeTime();
    }

    public function test_shared_deployment_paces_different_organizations_without_charging_or_sleeping(): void
    {
        $gate = app(ConversationCapacityAdmission::class);
        $gate->admit('organization-one', 'student-one');
        try {
            $gate->admit('organization-two', 'student-two');
            self::fail('The second organization bypassed shared capacity.');
        } catch (ConversationCapacityUnavailable $exception) {
            self::assertSame(429, $exception->getStatusCode());
            self::assertSame('40', $exception->getHeaders()['Retry-After']);
        }
        $this->travel(40)->seconds();
        $gate->admit('organization-two', 'student-two');

    }

    public function test_one_student_cannot_take_the_whole_lane_after_capacity_is_raised(): void
    {
        config()->set(['conversation.capacity.model_rpm' => 6000, 'conversation.capacity.model_tpm' => 12000000]);
        $gate = app(ConversationCapacityAdmission::class);
        $gate->admit('one', 'student');
        $this->travel(1)->seconds();
        $gate->admit('one', 'different-student');
        $this->expectException(ConversationCapacityUnavailable::class);
        $gate->admit('one', 'student');
    }

    public function test_invalid_capacity_and_store_failure_never_allow_unbounded_dispatch(): void
    {
        config()->set('conversation.capacity.store', 'not-a-store');
        try {
            app(ConversationCapacityAdmission::class)->admit('one', 'student');
            self::fail('Unavailable shared store allowed dispatch.');
        } catch (ConversationCapacityUnavailable $exception) {
            self::assertSame(503, $exception->getStatusCode());
        }
        config()->set('conversation.capacity.model_tpm', 0);
        $this->expectException(ConversationCapacityUnavailable::class);
        app(ConversationCapacityAdmission::class)->admit('one', 'student');
    }
}
