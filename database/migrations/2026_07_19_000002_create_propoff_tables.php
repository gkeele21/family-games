<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PropOff module schema — collapsed port of the source app's migrations,
 * with propoff_ table prefixes and string columns instead of MySQL ENUMs.
 * ranked_answers / event_type / template answers are kept so production
 * data imports cleanly (America Says UI itself is not ported).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propoff_events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 100);
            $table->string('event_type', 30)->default('GameQuiz');
            $table->dateTime('event_date');
            $table->string('status', 20)->default('draft'); // draft|open|locked|in_progress|completed
            $table->dateTime('lock_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('event_date');
        });

        Schema::create('propoff_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('propoff_events')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('grading_source', 20)->default('captain'); // captain|admin
            $table->timestamp('entry_cutoff')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('entry_cutoff');
        });

        Schema::create('propoff_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('propoff_groups')->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->boolean('is_captain')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'group_id']);
        });

        Schema::create('propoff_question_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('question_text');
            $table->string('question_type', 30); // multiple_choice|yes_no|numeric|text|ranked_answers
            $table->string('category')->nullable(); // comma-separated
            $table->integer('default_points')->default(1);
            $table->json('variables')->nullable();
            $table->json('default_options')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->integer('display_order')->default(0);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('propoff_question_template_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_template_id')
                ->constrained('propoff_question_templates')->cascadeOnDelete();
            $table->text('answer_text');
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('propoff_event_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('propoff_events')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()
                ->constrained('propoff_question_templates')->nullOnDelete();
            $table->text('question_text');
            $table->string('question_type', 30);
            $table->json('options')->nullable();
            $table->integer('points')->default(1);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('propoff_group_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('propoff_groups')->cascadeOnDelete();
            $table->foreignId('event_question_id')->nullable()
                ->constrained('propoff_event_questions')->cascadeOnDelete();
            $table->text('question_text');
            $table->string('question_type', 30);
            $table->json('options')->nullable();
            $table->integer('points')->default(1);
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_custom')->default(false);
            $table->timestamps();
        });

        Schema::create('propoff_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('propoff_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('propoff_groups')->cascadeOnDelete();
            $table->integer('total_score')->default(0);
            $table->integer('possible_points')->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->boolean('is_complete')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by_captain_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_by_captain_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id', 'group_id']);
        });

        Schema::create('propoff_user_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('propoff_entries')->cascadeOnDelete();
            $table->foreignId('group_question_id')
                ->constrained('propoff_group_questions')->cascadeOnDelete();
            $table->text('answer_text')->nullable();
            $table->integer('points_earned')->default(0);
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['entry_id', 'group_question_id']);
        });

        Schema::create('propoff_event_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('propoff_events')->cascadeOnDelete();
            $table->foreignId('event_question_id')
                ->constrained('propoff_event_questions')->cascadeOnDelete();
            $table->text('correct_answer');
            $table->integer('display_order')->nullable();
            $table->boolean('is_void')->default(false);
            $table->timestamp('set_at')->nullable();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // No unique pair — ranked_answers stores multiple rows per question.
        });

        Schema::create('propoff_group_question_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('propoff_groups')->cascadeOnDelete();
            $table->foreignId('group_question_id')
                ->constrained('propoff_group_questions')->cascadeOnDelete();
            $table->text('correct_answer');
            $table->integer('points_awarded')->nullable();
            $table->boolean('is_void')->default(false);
            $table->timestamps();

            $table->unique(['group_id', 'group_question_id']);
        });

        Schema::create('propoff_leaderboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('propoff_events')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()
                ->constrained('propoff_groups')->cascadeOnDelete(); // null = global
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('rank')->default(0);
            $table->integer('total_score')->default(0);
            $table->integer('possible_points')->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->integer('answered_count')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'group_id', 'user_id']);
        });

        Schema::create('propoff_event_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('propoff_events')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('propoff_groups')->cascadeOnDelete();
            $table->string('token', 32)->unique();
            $table->integer('max_uses')->nullable();
            $table->integer('times_used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'group_id']);
        });

        Schema::create('propoff_captain_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('propoff_events')->cascadeOnDelete();
            $table->string('token', 32)->unique();
            $table->integer('max_uses')->nullable();
            $table->integer('times_used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'propoff_captain_invitations', 'propoff_event_invitations',
            'propoff_leaderboards', 'propoff_group_question_answers',
            'propoff_event_answers', 'propoff_user_answers', 'propoff_entries',
            'propoff_group_questions', 'propoff_event_questions',
            'propoff_question_template_answers', 'propoff_question_templates',
            'propoff_group_user', 'propoff_groups', 'propoff_events',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
