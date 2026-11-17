<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits implementation partners (companies) from their user accounts.
 *
 * Before: a partner was a single user (role = partner) with a "company" text field,
 *         and projects.partner_id pointed at that user.
 * After:  partners is its own table; users belong to a partner with a partner_role
 *         (project_manager | project_admin | business_analyst); projects.partner_id
 *         points at the partner company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('contact_email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->after('role')->constrained()->restrictOnDelete();
            $table->string('partner_role', 30)->nullable()->after('partner_id');
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        // One partner company per distinct company name (or the person's name when no company was set).
        $userToPartner = [];
        foreach (DB::table('users')->where('role', 'partner')->orderBy('id')->get() as $user) {
            $name = trim((string) $user->company) ?: $user->name;
            $partnerId = DB::table('partners')->where('name', $name)->value('id')
                ?? DB::table('partners')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);

            DB::table('users')->where('id', $user->id)->update(['partner_id' => $partnerId, 'partner_role' => 'project_manager']);
            $userToPartner[$user->id] = $partnerId;
        }

        // Re-point projects from the partner's user account to the partner company.
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
        });
        foreach (DB::table('projects')->whereNotNull('partner_id')->get(['id', 'partner_id']) as $project) {
            DB::table('projects')->where('id', $project->id)->update(['partner_id' => $userToPartner[$project->partner_id] ?? null]);
        }
        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('partner_id')->references('id')->on('partners')->restrictOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('company');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company')->nullable();
        });
        foreach (DB::table('users')->whereNotNull('partner_id')->get(['id', 'partner_id']) as $user) {
            DB::table('users')->where('id', $user->id)->update(['company' => DB::table('partners')->where('id', $user->partner_id)->value('name')]);
        }

        // Point each project back at the first account of its partner.
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
        });
        foreach (DB::table('projects')->whereNotNull('partner_id')->get(['id', 'partner_id']) as $project) {
            DB::table('projects')->where('id', $project->id)
                ->update(['partner_id' => DB::table('users')->where('partner_id', $project->partner_id)->orderBy('id')->value('id')]);
        }
        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('partner_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_id');
            $table->dropColumn(['partner_role', 'must_change_password']);
        });
        Schema::dropIfExists('partners');
    }
};
