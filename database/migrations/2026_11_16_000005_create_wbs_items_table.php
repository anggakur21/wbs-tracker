<?php

use App\Services\WbsTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The standard WBS becomes admin-configurable. Each item is mandatory (always part of every
 * deliverable) or optional (the admin can leave it out per deliverable when creating a project).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wbs_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('phase');
            $table->string('name');
            $table->decimal('weight', 5, 2)->default(0);
            $table->boolean('is_mandatory')->default(true);
            $table->text('circulation')->nullable();
            $table->text('evidence_required')->nullable();
            $table->unsignedInteger('offset_start')->default(0);   // days after the project start
            $table->unsignedInteger('offset_finish')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach (WbsTemplate::standard() as $i => $item) {
            DB::table('wbs_items')->insert($item + [
                'sort_order' => $i + 1,
                // Items the template marks "(if any)" only apply to some deliverables.
                'is_mandatory' => ! str_contains(strtolower($item['name']), '(if any)'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->boolean('is_mandatory')->default(true)->after('weight');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('is_mandatory');
        });
        Schema::dropIfExists('wbs_items');
    }
};
