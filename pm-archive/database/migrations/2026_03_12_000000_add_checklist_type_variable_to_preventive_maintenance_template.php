<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('psm') || ! DB::getSchemaBuilder()->hasTable('psm_variable') || ! DB::getSchemaBuilder()->hasTable('psm_value')) {
            return;
        }

        $templateId = DB::table('psm')
            ->where('psm_id', 1)
            ->value('psm_id');

        if (! $templateId) {
            return;
        }

        $variableId = DB::table('psm_variable')
            ->where('psm_id', $templateId)
            ->where('name', 'checklist_type')
            ->value('psm_var_id');

        if (! $variableId) {
            DB::table('psm_variable')->insert([
                'psm_id' => $templateId,
                'name' => 'checklist_type',
                'description' => 'Checklist Type',
                'enabled' => 1,
                'input_type' => 'select',
                'created_at' => now(),
            ]);

            $variableId = DB::table('psm_variable')
                ->where('psm_id', $templateId)
                ->where('name', 'checklist_type')
                ->value('psm_var_id');
        }

        if (! $variableId) {
            return;
        }

        $rows = DB::table('psm')
            ->where('type', 'submission')
            ->where('template_psm_id', $templateId)
            ->get(['psm_id'])
            ->map(fn ($row) => [
                'psm_id' => $row->psm_id,
                'psm_var_id' => $variableId,
                'value' => 'pc',
                'status' => null,
                'created_at' => now(),
            ])
            ->all();

        if ($rows !== []) {
            DB::table('psm_value')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('psm_variable') || ! DB::getSchemaBuilder()->hasTable('psm_value')) {
            return;
        }

        $variableId = DB::table('psm_variable')
            ->where('psm_id', 1)
            ->where('name', 'checklist_type')
            ->value('psm_var_id');

        if (! $variableId) {
            return;
        }

        DB::table('psm_value')
            ->where('psm_var_id', $variableId)
            ->delete();

        DB::table('psm_variable')
            ->where('psm_var_id', $variableId)
            ->delete();
    }
};