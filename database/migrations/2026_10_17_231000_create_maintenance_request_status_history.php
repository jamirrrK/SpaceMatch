<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $historyTable = 'maintenance_request_status_history';

        if (! Schema::hasTable($historyTable)) {
            Schema::create($historyTable, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('maintenance_request_id');
                $table->unsignedBigInteger('actor_id');
                $table->string('from_status', 40);
                $table->string('to_status', 40);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumns($historyTable, [
            'id',
            'maintenance_request_id',
            'actor_id',
            'from_status',
            'to_status',
            'notes',
            'created_at',
            'updated_at',
        ])) {
            throw new \RuntimeException("The existing {$historyTable} table does not match the expected schema.");
        }

        $foreignKeys = collect(Schema::getForeignKeys($historyTable));

        if (! $foreignKeys->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['maintenance_request_id']
            && $foreignKey['foreign_table'] === 'maintenance_requests'
            && $foreignKey['foreign_columns'] === ['id'])) {
            Schema::table($historyTable, function (Blueprint $table) {
                $table->foreign('maintenance_request_id', 'mr_status_request_fk')
                    ->references('id')
                    ->on('maintenance_requests')
                    ->cascadeOnDelete();
            });
        }

        if (! $foreignKeys->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['actor_id']
            && $foreignKey['foreign_table'] === 'users'
            && $foreignKey['foreign_columns'] === ['id'])) {
            Schema::table($historyTable, function (Blueprint $table) {
                $table->foreign('actor_id', 'mr_status_actor_fk')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasIndex($historyTable, ['maintenance_request_id', 'created_at'])) {
            Schema::table($historyTable, function (Blueprint $table) {
                $table->index(['maintenance_request_id', 'created_at'], 'mrsh_request_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_request_status_history');
    }
};
