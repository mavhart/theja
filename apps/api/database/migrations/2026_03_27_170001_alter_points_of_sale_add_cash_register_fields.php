<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('points_of_sale', function (Blueprint $table) {
            if (Schema::hasColumn('points_of_sale', 'has_virtual_cash_register')
                && ! Schema::hasColumn('points_of_sale', 'virtual_cash_register_enabled')) {
                $table->renameColumn('has_virtual_cash_register', 'virtual_cash_register_enabled');
            }
        });

        Schema::table('points_of_sale', function (Blueprint $table) {
            if (! Schema::hasColumn('points_of_sale', 'rt_provider')) {
                $table->string('rt_provider')->nullable()->after('virtual_cash_register_enabled');
            }
            if (! Schema::hasColumn('points_of_sale', 'rt_credentials')) {
                $table->text('rt_credentials')->nullable()->after('rt_provider');
            }
            if (! Schema::hasColumn('points_of_sale', 'sumup_api_key')) {
                $table->text('sumup_api_key')->nullable()->after('rt_credentials');
            }
        });
    }

    public function down(): void
    {
        Schema::table('points_of_sale', function (Blueprint $table) {
            if (Schema::hasColumn('points_of_sale', 'sumup_api_key')) {
                $table->dropColumn('sumup_api_key');
            }
            if (Schema::hasColumn('points_of_sale', 'rt_credentials')) {
                $table->dropColumn('rt_credentials');
            }
            if (Schema::hasColumn('points_of_sale', 'rt_provider')) {
                $table->dropColumn('rt_provider');
            }
        });

        Schema::table('points_of_sale', function (Blueprint $table) {
            if (Schema::hasColumn('points_of_sale', 'virtual_cash_register_enabled')
                && ! Schema::hasColumn('points_of_sale', 'has_virtual_cash_register')) {
                $table->renameColumn('virtual_cash_register_enabled', 'has_virtual_cash_register');
            }
        });
    }
};

