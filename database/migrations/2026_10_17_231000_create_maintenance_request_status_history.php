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

        $existingColumns = array_map('strtolower', Schema::getColumnListing($historyTable));
        $expectedColumns = array_map('strtolower', [
            'id',
            'maintenance_request_id',
            'actor_id',
            'from_status',
            'to_status',
            'notes',
            'created_at',
            'updated_at',
        ]);

        $missingColumns = array_values(array_diff($expectedColumns, $existingColumns));

        if ($missingColumns !== []) {
            Schema::table($historyTable, function (Blueprint $table) use ($missingColumns) {
                if (in_array('maintenance_request_id', $missingColumns, true)) {
                    $table->unsignedBigInteger('maintenance_request_id')->nullable(false);
                }

                if (in_array('actor_id', $missingColumns, true)) {
                    $table->unsignedBigInteger('actor_id')->nullable(false);
                }

                if (in_array('from_status', $missingColumns, true)) {
                    $table->string('from_status', 40);
                }

                if (in_array('to_status', $missingColumns, true)) {
                    $table->string('to_status', 40);
                }

                if (in_array('notes', $missingColumns, true)) {
                    $table->text('notes')->nullable();
                }

                if (in_array('created_at', $missingColumns, true)) {
                    $table->timestamps();
                }
            });
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
