<?php

namespace Database\Seeders;

use App\Models\Chain;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChainSeeder extends Seeder
{
    public function run(): void
    {
        $alaa = User::where('email', 'alaa.gbh0@gmail.com')->first();

        // Pick a real project member (not alaa) to own one chain, so the
        // membership rule in ChainProjectSeeder is actually exercised.
        $otherCreatorId = DB::table('project_users')
            ->where('user_id', '!=', $alaa->id)
            ->pluck('user_id')
            ->unique()
            ->first();

        $chains = [
            ['name' => 'Q3 Product Roadmap', 'created_by' => $alaa->id],
            ['name' => 'Mobile App Development', 'created_by' => $alaa->id],
            ['name' => 'Infrastructure Migration', 'created_by' => $alaa->id],
            ['name' => 'Marketing Campaign', 'created_by' => $otherCreatorId ?? $alaa->id],
        ];

        foreach ($chains as $data) {
            Chain::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}