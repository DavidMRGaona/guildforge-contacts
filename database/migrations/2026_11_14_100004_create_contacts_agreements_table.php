<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts_agreements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('contacts_organizations')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('kind', 30);
            $table->string('status', 30)->default('active');
            // Calendar days, not instants: they keep their day whatever the zone
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->date('review_on')->nullable();
            $table->text('conditions')->nullable();
            $table->string('document_url', 2048)->nullable();
            // An account: removing it must not remove the agreement
            $table->foreignUuid('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            // The review_on the reminder was already sent for (one notice per date)
            $table->date('last_reminded_for')->nullable();
            $table->timestamps();

            $table->index(['status', 'review_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts_agreements');
    }
};
