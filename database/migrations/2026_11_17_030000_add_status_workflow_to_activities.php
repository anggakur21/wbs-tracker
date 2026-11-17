<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each WBS step moves through: planned → in_progress → in_review → done.
 *   planned      not started yet
 *   in_progress  the partner's Project Manager pressed Start (actual start filled)
 *   in_review    the Project Manager submitted it with evidence for a TIF admin to review
 *   done         a TIF admin accepted it (a rejection sends it back to in_progress)
 * activity_reviews keeps every submission and decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('status', 20)->default('planned')->after('progress')->index();
        });

        // Existing steps: finished ones count as done, started ones as in progress.
        DB::table('activities')->where('progress', '>=', 100)->update(['status' => 'done']);
        DB::table('activities')->where('progress', '<', 100)
            ->where(fn ($q) => $q->where('progress', '>', 0)->orWhereNotNull('actual_start'))
            ->update(['status' => 'in_progress']);

        Schema::create('activity_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->text('submission_note')->nullable();
            $table->unsignedTinyInteger('progress_before')->default(0);   // restored if the admin returns it
            $table->string('decision', 20)->nullable();                     // accepted | rejected (null = waiting)
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reason')->nullable();                             // why it was returned
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_reviews');
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
