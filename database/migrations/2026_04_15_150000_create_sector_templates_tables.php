<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sector_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('form_name')->default('Abertura padrao');
            $table->text('form_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sector_template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type');
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('show_on_board')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['sector_template_id', 'slug']);
        });

        Schema::create('sector_template_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('default_ticket_group_slug')->nullable();
            $table->string('default_priority')->default('medium');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sector_template_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger');
            $table->string('run_mode')->default('sync');
            $table->json('trigger_settings')->nullable();
            $table->unsignedInteger('cooldown_minutes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sector_template_automation_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_template_automation_rule_id', 'sector_template_automation_conditions_rule_fk')
                ->constrained('sector_template_automation_rules')
                ->cascadeOnDelete();
            $table->string('field');
            $table->string('operator');
            $table->json('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sector_template_automation_rule_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_template_automation_rule_id', 'sector_template_automation_actions_rule_fk')
                ->constrained('sector_template_automation_rules')
                ->cascadeOnDelete();
            $table->string('action');
            $table->json('payload')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sector_template_sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_template_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sector_template_sla_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_template_sla_policy_id')->constrained()->cascadeOnDelete();
            $table->string('priority');
            $table->unsignedInteger('first_response_minutes')->nullable();
            $table->unsignedInteger('resolution_minutes')->nullable();
            $table->timestamps();

            $table->unique(['sector_template_sla_policy_id', 'priority'], 'sector_template_sla_targets_priority_unique');
        });

        Schema::table('sectors', function (Blueprint $table) {
            $table->foreignId('sector_template_id')->nullable()->after('company_id')
                ->constrained('sector_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sector_template_id');
        });

        Schema::dropIfExists('sector_template_sla_targets');
        Schema::dropIfExists('sector_template_sla_policies');
        Schema::dropIfExists('sector_template_automation_rule_actions');
        Schema::dropIfExists('sector_template_automation_rule_conditions');
        Schema::dropIfExists('sector_template_automation_rules');
        Schema::dropIfExists('sector_template_catalog_items');
        Schema::dropIfExists('sector_template_fields');
        Schema::dropIfExists('sector_templates');
    }
};
