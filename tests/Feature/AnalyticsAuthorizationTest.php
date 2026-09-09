<?php

namespace Tests\Feature;

use App\Models\SocialPerson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_read_analytics(): void
    {
        $this->getJson('/api/analytics/overview')->assertUnauthorized();
    }

    public function test_user_cannot_read_another_users_person(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $p = SocialPerson::create(['id' => (string) Str::uuid(), 'user_id' => $b->id, 'display_name' => 'Private Person', 'normalized_name' => 'private person']);
        $this->actingAs($a)->getJson('/api/analytics/people/'.$p->id)->assertNotFound();
    }
}
