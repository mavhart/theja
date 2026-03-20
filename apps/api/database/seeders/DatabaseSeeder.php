<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Dati di test Theja:
     * - 2 organizations: "Ottica Rossi" e "Ottica Bianchi"
     * - 3 points_of_sale: POS1 e POS2 per Ottica Rossi, POS1 per Ottica Bianchi
     * - 4 utenti: 2 per organization (ruoli diversi, assegnati via Spatie Permission in Fase 1)
     *
     * Credenziali di accesso (password: "password"):
     *   org_owner@rossi.test     — proprietario Ottica Rossi
     *   manager@rossi.test       — manager POS Ottica Rossi
     *   org_owner@bianchi.test   — proprietario Ottica Bianchi
     *   optician@bianchi.test    — ottico Ottica Bianchi
     */
    public function run(): void
    {
        // ─── Ottica Rossi ─────────────────────────────────────────────────
        $otticaRossi = Organization::create([
            'name'          => 'Ottica Rossi Group',
            'vat_number'    => 'IT12345678901',
            'billing_email' => 'admin@otticarossi.it',
            'is_active'     => true,
        ]);

        // POS 1 — sede principale (configurazione standard)
        PointOfSale::create([
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

        // POS 2 — seconda sede (add-on: +sessioni web + AI Analysis)
        PointOfSale::create([
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

        // Utenti Ottica Rossi
        User::create([
            'organization_id'  => $otticaRossi->id,
            'name'             => 'Marco Rossi',
            'email'            => 'org_owner@rossi.test',
            'password'         => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active'        => true,
        ]);

        User::create([
            'organization_id'  => $otticaRossi->id,
            'name'             => 'Laura Bianchi',
            'email'            => 'manager@rossi.test',
            'password'         => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active'        => true,
        ]);

        // ─── Ottica Bianchi ───────────────────────────────────────────────
        $otticaBianchi = Organization::create([
            'name'          => 'Ottica Bianchi Srl',
            'vat_number'    => 'IT98765432109',
            'billing_email' => 'info@otticabianchi.it',
            'is_active'     => true,
        ]);

        // POS 1 — unico punto vendita
        PointOfSale::create([
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

        // Utenti Ottica Bianchi
        User::create([
            'organization_id'  => $otticaBianchi->id,
            'name'             => 'Giovanni Bianchi',
            'email'            => 'org_owner@bianchi.test',
            'password'         => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active'        => true,
        ]);

        User::create([
            'organization_id'  => $otticaBianchi->id,
            'name'             => 'Sara Verdi',
            'email'            => 'optician@bianchi.test',
            'password'         => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active'        => true,
        ]);

        $this->command->info('✅ Seeder completato: 2 org, 3 POS, 4 utenti creati.');
        $this->command->info('   Password: "password" per tutti gli utenti.');
    }
}
