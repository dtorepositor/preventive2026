<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('psm_value_archive')) {
            Schema::create('psm_value_archive', function (Blueprint $table) {
                $table->id('id');
                $table->unsignedBigInteger('psm_val_id')->nullable();
                $table->unsignedBigInteger('psm_id');
                $table->unsignedBigInteger('psm_var_id');
                $table->string('variable_name', 150)->nullable();
                $table->text('value')->nullable();
                $table->string('status', 50)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['psm_id']);
                $table->index(['psm_var_id']);
            });

            // Backfill existing psm_value rows (include variable name if available)
            $rows = DB::table('psm_value')
                ->leftJoin('psm_variable', 'psm_value.psm_var_id', '=', 'psm_variable.psm_var_id')
                ->select('psm_value.psm_val_id', 'psm_value.psm_id', 'psm_value.psm_var_id', 'psm_variable.name as variable_name', 'psm_value.value', 'psm_value.status', 'psm_value.created_at')
                ->get();

            $inserts = [];
            foreach ($rows as $r) {
                $inserts[] = [
                    'psm_val_id' => $r->psm_val_id,
                    'psm_id' => $r->psm_id,
                    'psm_var_id' => $r->psm_var_id,
                    'variable_name' => $r->variable_name,
                    'value' => $r->value,
                    'status' => $r->status,
                    'created_at' => $r->created_at,
                ];
            }

            if (! empty($inserts)) {
                // Insert in chunks to avoid large single queries
                foreach (array_chunk($inserts, 500) as $chunk) {
                    DB::table('psm_value_archive')->insert($chunk);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('psm_value_archive');
    }
};
