<?php

declare(strict_types=1);

use App\Application\Linking\DTOs\LinkableRecordDTO;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('contacts_organizations')
                ->cascadeOnDelete();
            // Empty when the organization itself is linked; set when one of its agreements is
            $table->foreignUuid('agreement_id')
                ->nullable()
                ->constrained('contacts_agreements')
                ->cascadeOnDelete();
            // Only the type and id: the title and date are read live from the source every time
            $table->string('record_type', 100);
            $table->string('record_id', LinkableRecordDTO::MAX_ID_LENGTH);
            // An account: removing it must not remove the link
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'agreement_id', 'record_type', 'record_id'], 'contacts_links_owner_record_unique');
        });

        // Unique indexes count every NULL as different, so the unique key above never stops the
        // organization's own links (no agreement) from repeating. A partial index does, in both
        // PostgreSQL and SQLite.
        DB::statement(
            'CREATE UNIQUE INDEX contacts_links_organization_record_unique '
            .'ON contacts_links (organization_id, record_type, record_id) WHERE agreement_id IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts_links');
    }
};
