<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts_interactions', function (Blueprint $table): void {
            // The history outlives the agreement: deleting it only unlinks the entries
            $table->foreignUuid('agreement_id')
                ->nullable()
                ->constrained('contacts_agreements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts_interactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('agreement_id');
        });
    }
};
