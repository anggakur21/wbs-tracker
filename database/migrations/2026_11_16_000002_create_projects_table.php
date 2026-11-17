<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contract_number')->nullable();
            $table->decimal('contract_value', 18, 2)->nullable();
            $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // A project holds one or more deliverables; each deliverable runs the full WBS.
        Schema::create('deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained()->cascadeOnDelete();
            $table->string('phase');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('name');
            $table->decimal('weight', 5, 2)->default(0);
            $table->text('circulation')->nullable();        // "Sirkulir" column of the template
            $table->date('plan_start');
            $table->date('plan_finish');
            $table->text('evidence_required')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->date('actual_start')->nullable();
            $table->date('actual_finish')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidences');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('deliverables');
        Schema::dropIfExists('projects');
    }
};
