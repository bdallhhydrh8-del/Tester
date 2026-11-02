<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dev 1's demo data: admins, testers, invitation links and who joined which link.
 * Safe to run many times. Local use only.
 */
class DemoUsersSeeder extends Seeder
{
    private const TESTER_NAMES = [
        'أحمد صالح', 'سارة عمر', 'محمد ناصر', 'فاطمة علي', 'خالد سعيد',
        'نور الدين', 'ليلى حسن', 'يوسف قاسم', 'مريم عبدالله', 'عمر باوزير',
        'هدى مهدي', 'طارق سالم', 'رنا محسن', 'ياسر فضل', 'آمنة جابر',
    ];

    public function run(): void
    {
        $admin = $this->seedAdmins();
        $testers = $this->seedTesters();
        $this->seedInvitations($admin, $testers);

        $this->command?->info('Demo admins: demo_admin1 / demo_admin2, password: password123');
        $this->command?->info('Demo testers log in with phone only, for example +967771000001');
    }

    private function seedAdmins(): User
    {
        $first = null;

        foreach ([['demo_admin1', 'Demo Admin One'], ['demo_admin2', 'Demo Admin Two']] as [$username, $name]) {
            $admin = User::firstOrCreate(
                ['username' => $username],
                ['role' => Role::Admin, 'status' => 'active', 'name' => $name, 'password' => 'password123']
            );

            $first ??= $admin;
        }

        return $first;
    }

    /** @return array<int, User> */
    private function seedTesters(): array
    {
        $testers = [];

        foreach (self::TESTER_NAMES as $index => $name) {
            $phone = '+96777' . sprintf('%07d', 1000001 + $index);

            $testers[] = User::where('phone', $phone)->first()
                ?? DB::transaction(fn () => User::create([
                    'role' => Role::Tester,
                    'status' => 'active',
                    'name' => $name,
                    'phone' => $phone,
                    'tester_number' => $this->nextTesterNumber(),
                ]));
        }

        return $testers;
    }

    /** @param array<int, User> $testers */
    private function seedInvitations(User $admin, array $testers): void
    {
        $links = [
            ['demo-week-1', 'Demo Week 1 (active)', null, null, range(0, 9)],
            ['demo-week-2', 'Demo Week 2 (expires in 7 days)', now()->addDays(7), null, range(7, 11)],
            ['demo-stopped', 'Demo stopped link', null, now()->subDay(), []],
            ['demo-expired', 'Demo expired link', now()->subDays(2), null, []],
        ];

        foreach ($links as [$key, $name, $expiresAt, $stoppedAt, $joined]) {
            // Fixed, 40-character token so everyone gets the same demo URLs
            $token = substr(hash('sha256', $key), 0, 40);

            $invitation = Invitation::firstOrCreate(
                ['token' => $token],
                ['name' => $name, 'expires_at' => $expiresAt, 'stopped_at' => $stoppedAt, 'created_by' => $admin->id]
            );

            foreach ($joined as $testerIndex) {
                DB::table('invitation_testers')->insertOrIgnore([
                    'invitation_id' => $invitation->id,
                    'tester_id' => $testers[$testerIndex]->id,
                    'joined_at' => now(),
                ]);
            }

            $this->command?->line("Link '{$name}': /links/{$token}");
        }
    }

    /** Same logic as LinkController: numbers are handed out once and never reused. */
    private function nextTesterNumber(): int
    {
        $row = DB::table('tester_counter')->lockForUpdate()->first();

        if (! $row) {
            $id = DB::table('tester_counter')->insertGetId(['last_number' => 0]);
            $row = DB::table('tester_counter')->where('id', $id)->lockForUpdate()->first();
        }

        $next = $row->last_number + 1;
        DB::table('tester_counter')->where('id', $row->id)->update(['last_number' => $next]);

        return $next;
    }
}