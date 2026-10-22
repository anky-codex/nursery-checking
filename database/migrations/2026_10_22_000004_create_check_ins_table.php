<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ONE_OPEN_CHECK_IN_INDEX = 'check_ins_one_open_per_child';

    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamps();

            // Serves "who is in this room right now?".
            $table->index(['tenant_id', 'room_id', 'checked_out_at']);
        });

        // Safety net behind the application check: a child can have at most one
        // open check-in, even if two requests race. Partial indexes are supported
        // by SQLite and PostgreSQL (see README for MySQL).
        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX '.self::ONE_OPEN_CHECK_IN_INDEX.
                ' ON check_ins (child_id) WHERE checked_out_at IS NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
