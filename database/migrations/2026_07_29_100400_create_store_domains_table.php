<?php

use App\Enums\DomainVerificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_domains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('hostname')->unique();
            $table->string('type');
            $table->boolean('is_primary')->default(false);
            $table->string('verification_status')->default(DomainVerificationStatus::Pending->value);
            $table->string('verification_token')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('ssl_status')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_domains');
    }
};
