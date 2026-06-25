<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Ward;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Schedule;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the WCRMS database with initial data.
     * Run: php artisan db:seed
     */
    public function run(): void
    {
        // ── Wards ────────────────────────────────────────────
        $mazimbu = Ward::create([
            'name' => 'Mazimbu',
            'code' => 'MZM',
            'lat'  => -6.8500,
            'lng'  =>  37.6500,
        ]);

        $mwembesongo = Ward::create([
            'name' => 'Mwembesongo',
            'code' => 'MWS',
            'lat'  => -6.8300,
            'lng'  =>  37.6600,
        ]);

        // ── Admin ─────────────────────────────────────────────
        User::create([
            'name'     => 'System Administrator',
            'email'    => 'admin@wcrms.co.tz',
            'password' => Hash::make('Admin@1234'),
            'role'     => 'admin',
        ]);

        // ── Supervisor ────────────────────────────────────────
        $supervisor = User::create([
            'name'     => 'Joseph Mwangi',
            'email'    => 'supervisor@wcrms.co.tz',
            'password' => Hash::make('Super@1234'),
            'role'     => 'supervisor',
            'ward_id'  => $mazimbu->id,
        ]);

        // ── Registrar (Registration Officer) ─────────────────
        User::create([
            'name'     => 'Amina Registrar',
            'email'    => 'registrar@wcrms.co.tz',
            'password' => Hash::make('Registrar@1234'),
            'role'     => 'registrar',
            'ward_id'  => $mazimbu->id,
        ]);

        // ── Collection Officers ───────────────────────────────
        $officer1 = User::create([
            'name'     => 'Ali Hassan',
            'email'    => 'officer1@wcrms.co.tz',
            'password' => Hash::make('Officer@1234'),
            'role'     => 'officer',
            'ward_id'  => $mazimbu->id,
        ]);

        $officer2 = User::create([
            'name'     => 'Grace Moshi',
            'email'    => 'officer2@wcrms.co.tz',
            'password' => Hash::make('Officer@1234'),
            'role'     => 'officer',
            'ward_id'  => $mwembesongo->id,
        ]);

        // ── Residents ─────────────────────────────────────────
        User::create([
            'name'          => 'Fatuma Ally',
            'email'         => 'resident1@wcrms.co.tz',
            'password'      => Hash::make('Resident@1234'),
            'role'          => 'resident',
            'ward_id'       => $mazimbu->id,
            'property_type' => 'residential',
        ]);

        // ── Vehicles ──────────────────────────────────────────
        $vehicle1 = Vehicle::create([
            'registration' => 'T 100 AAA',
            'make'         => 'Isuzu',
            'model'        => 'NPR Tipper',
        ]);

        $vehicle2 = Vehicle::create([
            'registration' => 'T 200 BBB',
            'make'         => 'Mitsubishi',
            'model'        => 'Canter',
        ]);

        // ── Schedules ─────────────────────────────────────────
        Schedule::create([
            'ward_id'         => $mazimbu->id,
            'vehicle_id'      => $vehicle1->id,
            'officer_id'      => $officer1->id,
            'supervisor_id'   => $supervisor->id,
            'collection_days' => 'Monday,Wednesday,Friday',
            'start_time'      => '07:00',
            'end_time'        => '12:00',
            'status'          => 'active',
        ]);

        Schedule::create([
            'ward_id'         => $mwembesongo->id,
            'vehicle_id'      => $vehicle2->id,
            'officer_id'      => $officer2->id,
            'supervisor_id'   => $supervisor->id,
            'collection_days' => 'Tuesday,Thursday,Saturday',
            'start_time'      => '07:00',
            'end_time'        => '12:00',
            'status'          => 'active',
        ]);

        $this->command->info('✅ WCRMS database seeded successfully.');
        $this->command->info('   Admin:      admin@wcrms.co.tz        / Admin@1234');
        $this->command->info('   Supervisor: supervisor@wcrms.co.tz   / Super@1234');
        $this->command->info('   Registrar:  registrar@wcrms.co.tz    / Registrar@1234');
        $this->command->info('   Officer 1:  officer1@wcrms.co.tz     / Officer@1234');
        $this->command->info('   Officer 2:  officer2@wcrms.co.tz     / Officer@1234');
        $this->command->info('   Resident:   resident1@wcrms.co.tz    / Resident@1234');
    }
}
