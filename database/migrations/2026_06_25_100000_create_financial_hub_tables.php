<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_financial_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // banco_bv, dr_cash, dental_cred, konsiga
            $table->string('environment')->default('sandbox'); // sandbox, production
            $table->string('status')->default('inactive'); // inactive, active, error, circuit_open
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->string('webhook_url')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'provider']);
        });

        Schema::create('financing_simulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider');
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('installments');
            $table->string('cpf_hash'); // nunca armazenar CPF em texto claro
            $table->string('external_id')->nullable();
            $table->decimal('installment_value', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('interest_rate', 8, 4)->nullable();
            $table->decimal('cet', 8, 4)->nullable();
            $table->decimal('fees', 12, 2)->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });

        Schema::create('financing_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('simulation_id')->nullable()->constrained('financing_simulations')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider');
            $table->string('external_id')->nullable();
            $table->string('status')->default('rascunho');
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('installments');
            $table->string('signature_url')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->decimal('fees_amount', 12, 2)->nullable();
            $table->date('expected_settlement_date')->nullable();
            $table->date('settled_at')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['external_id', 'provider']);
        });

        Schema::create('financing_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained('clinic_financial_connections')->nullOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained('financing_proposals')->nullOnDelete();
            $table->string('provider');
            $table->string('event_type');
            $table->string('external_id')->nullable();
            $table->json('payload');
            $table->string('status')->default('received'); // received, processed, failed
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('financing_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('budget_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained('financing_proposals')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_activity_logs');
        Schema::dropIfExists('financing_webhook_events');
        Schema::dropIfExists('financing_proposals');
        Schema::dropIfExists('financing_simulations');
        Schema::dropIfExists('clinic_financial_connections');
    }
};