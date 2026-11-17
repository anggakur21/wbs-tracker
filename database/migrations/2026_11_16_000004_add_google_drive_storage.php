<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Small key/value store for app-wide settings (the Google Drive connection lives here).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Where each evidence file is kept: the server's private disk or the connected Google Drive.
        Schema::table('evidences', function (Blueprint $table) {
            $table->string('storage', 20)->default('local')->after('path');
            $table->string('drive_file_id')->nullable()->after('storage');
        });

        // Cache of Drive folders created for the Project / Deliverable / Activity tree.
        Schema::create('drive_folders', function (Blueprint $table) {
            $table->id();
            $table->string('root_id');               // the app folder of the connected account
            $table->string('owner_type', 30);        // project | deliverable | activity
            $table->unsignedBigInteger('owner_id');
            $table->string('folder_id');
            $table->timestamps();
            $table->unique(['root_id', 'owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_folders');
        Schema::table('evidences', function (Blueprint $table) {
            $table->dropColumn(['storage', 'drive_file_id']);
        });
        Schema::dropIfExists('settings');
    }
};
