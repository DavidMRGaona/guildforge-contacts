<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts_interactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('contacts_organizations')
                ->cascadeOnDelete();
            // A calendar day, not an instant: it never moves with the time zone
            $table->date('occurred_on');
            $table->string('kind', 20);
            $table->text('body');
            // The history outlives the person and the account that wrote the entry
            $table->foreignUuid('person_id')
                ->nullable()
                ->constrained('contacts_people')
                ->nullOnDelete();
            $table->foreignUuid('author_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts_interactions');
    }
};
