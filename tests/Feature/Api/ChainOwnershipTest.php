<?php

namespace Tests\Feature\Api;

use App\Models\Chain;
use App\Models\Project;
use App\Models\User;
use App\Services\ChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChainOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function authedUser(): User
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);
        return $user;
    }

    private function createUser(array $attrs = []): User
    {
        static $i = 0;
        $i++;
        $user = User::create(array_merge([
            'name' => 'Test User ' . $i,
            'username' => 'test_user_' . uniqid() . '_' . $i,
            'email' => 'test_' . uniqid() . '_' . $i . '@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ], $attrs));
        $user->is_active = true;
        $user->save();
        return $user;
    }

    private function createProject(User $owner, array $members = []): Project
    {
        $project = Project::create([
            'name' => 'Project ' . uniqid(),
            'created_by' => $owner->id,
        ]);
        $project->addUser($owner->id, 'owner');
        foreach ($members as $member) {
            $project->addUser($member->id, 'user');
        }
        return $project;
    }

    private function createChain(User $owner, array $projects): Chain
    {
        $chain = Chain::create([
            'name' => 'Chain ' . uniqid(),
            'created_by' => $owner->id,
        ]);
        $service = app(ChainService::class);
        foreach ($projects as $project) {
            $service->addToChain($chain->id, $project->id);
        }
        return $chain->fresh();
    }

    // ---- GET /my-chains ----

    public function test_my_chains_returns_owned_chains_with_project_chain_id(): void
    {
        $owner = $this->authedUser();
        $projectA = $this->createProject($owner);
        $projectB = $this->createProject($owner);
        $chain = $this->createChain($owner, [$projectA, $projectB]);

        $response = $this->getJson('/api/my-chains');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $chain->id)
            ->assertJsonPath('data.0.projects.0.chain_id', $chain->id)
            ->assertJsonPath('data.0.projects.1.chain_id', $chain->id)
            ->assertJsonPath('data.0.projects.0.chain.id', $chain->id);
    }

    public function test_my_chains_excludes_chains_owned_by_others(): void
    {
        $user = $this->authedUser();
        $other = $this->createUser();
        $chain = $this->createChain($other, [$this->createProject($other)]);

        $response = $this->getJson('/api/my-chains');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'data');
    }

    // ---- POST /projects/{project}/users/transfer-ownership/{userId} ----

    public function test_transfer_ownership_detaches_project_from_chain(): void
    {
        $owner = $this->authedUser();
        $member = $this->createUser();
        $projectA = $this->createProject($owner, [$member]);
        $projectB = $this->createProject($owner);
        $chain = $this->createChain($owner, [$projectA, $projectB]);

        $response = $this->postJson(
            "/api/projects/{$projectA->id}/users/transfer-ownership/{$member->id}"
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.detached_from_chain', true)
            ->assertJsonPath('data.new_owner_id', $member->id);

        $projectA = $projectA->fresh();

        // Project no longer belongs to the original chain
        $this->assertDatabaseMissing('chain_projects', [
            'chain_id' => $chain->id,
            'project_id' => $projectA->id,
        ]);
        $this->assertNotEquals($chain->id, $projectA->chain_id);

        // Original chain keeps the other project
        $this->assertDatabaseHas('chain_projects', [
            'chain_id' => $chain->id,
            'project_id' => $projectB->id,
        ]);

        // New owner got a standalone chain containing the project
        $standalone = Chain::where('created_by', $member->id)->first();
        $this->assertNotNull($standalone);
        $this->assertTrue($standalone->projects->contains($projectA->id));
        $this->assertEquals($standalone->id, $projectA->chain_id);

        // Ownership actually moved
        $this->assertEquals($member->id, $projectA->created_by);
        $this->assertEquals('owner', $projectA->getUserRole($member->id));
        $this->assertEquals('user', $projectA->getUserRole($owner->id));
    }

    public function test_transfer_ownership_deletes_chain_when_it_becomes_empty(): void
    {
        $owner = $this->authedUser();
        $member = $this->createUser();
        $project = $this->createProject($owner, [$member]);
        $chain = $this->createChain($owner, [$project]);

        $response = $this->postJson(
            "/api/projects/{$project->id}/users/transfer-ownership/{$member->id}"
        );

        $response->assertOk();
        $this->assertDatabaseMissing('chains', ['id' => $chain->id]);
    }

    public function test_transfer_ownership_of_unchained_project_does_not_detach(): void
    {
        $owner = $this->authedUser();
        $member = $this->createUser();
        $project = $this->createProject($owner, [$member]);

        $response = $this->postJson(
            "/api/projects/{$project->id}/users/transfer-ownership/{$member->id}"
        );

        $response->assertOk()
            ->assertJsonPath('data.detached_from_chain', false)
            ->assertJsonPath('data.new_owner_id', $member->id);
        $this->assertEquals($member->id, $project->fresh()->created_by);
    }

    public function test_transfer_ownership_requires_project_owner(): void
    {
        $owner = $this->createUser();
        $notOwner = $this->authedUser();
        $member = $this->createUser();
        $project = $this->createProject($owner, [$notOwner, $member]);

        $response = $this->postJson(
            "/api/projects/{$project->id}/users/transfer-ownership/{$member->id}"
        );

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
