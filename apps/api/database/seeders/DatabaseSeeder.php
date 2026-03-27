<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\PointOfSale;
use App\Models\User;
use App\Models\UserPosRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Dati di test Theja:
     * - 2 organizations: "Ottica Rossi" e "Ottica Bianchi"
     * - 3 points_of_sale: POS1 e POS2 per Ottica Rossi, POS1 per Ottica Bianchi
     * - 4 utenti con ruoli assegnati via user_pos_roles
     *
     * Credenziali di accesso (password: "password"):
     *   org_owner@rossi.test     — org_owner su entrambi i POS Rossi
     *   manager@rossi.test       — pos_manager su POS1 Rossi
     *   org_owner@bianchi.test   — org_owner su POS Bianchi
     *   optician@bianchi.test    — optician su POS Bianchi
     */
    public function run(): void
    {
        // Crea prima i ruoli e i permessi di sistema
        $this->call(RolePermissionSeeder::class);
        $this->call(LabelTemplatePresetSeeder::class);

        // ─── Ottica Rossi ─────────────────────────────────────────────────────
        $otticaRossi = Organization::create([
            'name'          => 'Ottica Rossi Group',
            'vat_number'    => 'IT12345678901',
            'billing_email' => 'admin@otticarossi.it',
            'is_active'     => true,
        ]);

        $rossiPos1 = PointOfSale::create([
            'organization_id'             => $otticaRossi->id,
            'name'                        => 'Ottica Rossi — Via Roma',
            'address'                     => 'Via Roma 1',
            'city'                        => 'Milano',
            'has_local_manager'           => true,
            'has_virtual_cash_register'   => false,
            'ai_analysis_enabled'         => false,
            'max_concurrent_web_sessions' => 1,
            'max_mobile_devices'          => 0,
            'is_active'                   => true,
        ]);

        $rossiPos2 = PointOfSale::create([
            'organization_id'             => $otticaRossi->id,
            'name'                        => 'Ottica Rossi — Corso Vittorio',
            'address'                     => 'Corso Vittorio Emanuele 45',
            'city'                        => 'Milano',
            'has_local_manager'           => true,
            'has_virtual_cash_register'   => false,
            'ai_analysis_enabled'         => true,
            'max_concurrent_web_sessions' => 2,
            'max_mobile_devices'          => 1,
            'is_active'                   => true,
        ]);

        $marcoRossi = User::create([
            'organization_id'   => $otticaRossi->id,
            'name'              => 'Marco Rossi',
            'email'             => 'org_owner@rossi.test',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        $lauraManager = User::create([
            'organization_id'   => $otticaRossi->id,
            'name'              => 'Laura Bianchi',
            'email'             => 'manager@rossi.test',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        // Assegna ruoli
        $orgOwnerRole  = Role::where('name', 'org_owner')->first();
        $posManagerRole = Role::where('name', 'pos_manager')->first();

        UserPosRole::insert([
            ['user_id' => $marcoRossi->id, 'pos_id' => $rossiPos1->id, 'role_id' => $orgOwnerRole->id,   'can_see_purchase_prices' => true],
            ['user_id' => $marcoRossi->id, 'pos_id' => $rossiPos2->id, 'role_id' => $orgOwnerRole->id,   'can_see_purchase_prices' => true],
            ['user_id' => $lauraManager->id, 'pos_id' => $rossiPos1->id, 'role_id' => $posManagerRole->id, 'can_see_purchase_prices' => false],
        ]);

        // ─── Ottica Bianchi ───────────────────────────────────────────────────
        $otticaBianchi = Organization::create([
            'name'          => 'Ottica Bianchi Srl',
            'vat_number'    => 'IT98765432109',
            'billing_email' => 'info@otticabianchi.it',
            'is_active'     => true,
        ]);

        $bianchiPos1 = PointOfSale::create([
            'organization_id'             => $otticaBianchi->id,
            'name'                        => 'Ottica Bianchi — Centro',
            'address'                     => 'Piazza Garibaldi 10',
            'city'                        => 'Roma',
            'has_local_manager'           => true,
            'has_virtual_cash_register'   => false,
            'ai_analysis_enabled'         => false,
            'max_concurrent_web_sessions' => 1,
            'max_mobile_devices'          => 0,
            'is_active'                   => true,
        ]);

        $giovanniBianchi = User::create([
            'organization_id'   => $otticaBianchi->id,
            'name'              => 'Giovanni Bianchi',
            'email'             => 'org_owner@bianchi.test',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        $saraVerdi = User::create([
            'organization_id'   => $otticaBianchi->id,
            'name'              => 'Sara Verdi',
            'email'             => 'optician@bianchi.test',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        $opticianRole = Role::where('name', 'optician')->first();

        UserPosRole::insert([
            ['user_id' => $giovanniBianchi->id, 'pos_id' => $bianchiPos1->id, 'role_id' => $orgOwnerRole->id,  'can_see_purchase_prices' => true],
            ['user_id' => $saraVerdi->id,        'pos_id' => $bianchiPos1->id, 'role_id' => $opticianRole->id,  'can_see_purchase_prices' => false],
        ]);

        $this->command->info('✅ Seeder completato: 2 org, 3 POS, 4 utenti, ruoli assegnati.');
        $this->command->info('   Password: "password" per tutti gli utenti.');
    }
}
