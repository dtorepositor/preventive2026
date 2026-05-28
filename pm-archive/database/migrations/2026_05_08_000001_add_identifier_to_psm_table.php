<?php

use App\Models\Psm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('psm') || Schema::hasColumn('psm', 'identifier')) {
            return;
        }

        Schema::table('psm', function (Blueprint $table) {
            $table->string('identifier', 50)->nullable()->unique()->after('template_psm_id');
        });

        $checklistTypes = DB::table('psm_value')
            ->join('psm_variable', 'psm_value.psm_var_id', '=', 'psm_variable.psm_var_id')
            ->where('psm_variable.name', 'checklist_type')
            ->pluck('psm_value.value', 'psm_value.psm_id');

        DB::table('psm')
            ->where('type', 'submission')
            ->where('template_psm_id', 1)
            ->orderBy('psm_id')
            ->select('psm_id')
            ->chunkById(100, function ($rows) use ($checklistTypes) {
                foreach ($rows as $row) {
                    $identifier = sprintf(
                        'PM%s-%04d',
                        Psm::preventiveMaintenanceCategoryCode($checklistTypes[$row->psm_id] ?? null),
                        (int) $row->psm_id
                    );

                    DB::table('psm')
                        ->where('psm_id', $row->psm_id)
                        ->update(['identifier' => $identifier]);
                }
            }, 'psm_id');
    }

    public function down(): void
    {
        if (! Schema::hasTable('psm') || ! Schema::hasColumn('psm', 'identifier')) {
            return;
        }

        Schema::table('psm', function (Blueprint $table) {
            $table->dropUnique(['identifier']);
            $table->dropColumn('identifier');
        });
    }
};
