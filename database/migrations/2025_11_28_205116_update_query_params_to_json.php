<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('laravel_samlidp_service_providers', function (Blueprint $table) {
            // Migrate data from boolean to JSON
            DB::table('laravel_samlidp_service_providers')->get()->each(function ($sp) {
                $newValue = $sp->query_params
                    ? json_encode(['idp' => config('app.url')])
                    : json_encode(false);

                DB::table('laravel_samlidp_service_providers')
                    ->where('id', $sp->id)
                    ->update(['query_params' => DB::raw("CAST(? AS JSON)", [$newValue])]);
            });

            // Change query_params from boolean to JSON
            $table->json('query_params')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laravel_samlidp_service_providers', function (Blueprint $table) {
            // Revert to boolean
            $table->boolean('query_params')->change();
        });
    }
};
