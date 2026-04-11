<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Student Diary
        Schema::create('student_diaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained()->onDelete('cascade');
            $table->foreignId('class_id')->constrained('course_classes')->onDelete('cascade');
            $table->foreignId('section_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('staff')->onDelete('cascade');
            $table->date('date');
            $table->text('content'); // Supports CW/HW
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        // 2. Assignments
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained()->onDelete('cascade');
            $table->foreignId('class_id')->constrained('course_classes')->onDelete('cascade');
            $table->foreignId('section_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('staff')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('due_date');
            $table->integer('max_marks')->default(100);
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->dateTime('submitted_at');
            $table->text('content')->nullable();
            $table->json('attachments')->nullable();
            $table->decimal('marks', 5, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // 3. Syllabus Tracking
        Schema::create('syllabus_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('class_id')->constrained('course_classes')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->string('chapter_name');
            $table->string('topic_name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('syllabus_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('syllabus_topics')->onDelete('cascade');
            $table->foreignId('section_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('staff')->onDelete('cascade');
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->date('planned_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->timestamps();
        });

        // 4. Online Classes
        Schema::create('online_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('class_id')->constrained('course_classes')->onDelete('cascade');
            $table->foreignId('section_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('staff')->onDelete('cascade');
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->string('meeting_link');
            $table->string('platform')->nullable(); // Zoom, Meet, etc.
            $table->timestamps();
        });

        // 5. Learning Materials
        Schema::create('learning_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('class_id')->constrained('course_classes')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('staff')->onDelete('cascade');
            $table->string('title');
            $table->string('type'); // pdf, ppt, video, etc.
            $table->string('file_path');
            $table->string('chapter_name')->nullable();
            $table->timestamps();
        });

        // 6. Book Lists
        Schema::create('book_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained()->onDelete('cascade');
            $table->foreignId('class_id')->constrained('course_classes')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->string('book_name');
            $table->string('publisher')->nullable();
            $table->string('author')->nullable();
            $table->string('isbn')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_lists');
        Schema::dropIfExists('learning_materials');
        Schema::dropIfExists('online_classes');
        Schema::dropIfExists('syllabus_status');
        Schema::dropIfExists('syllabus_topics');
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('student_diaries');
    }
};
