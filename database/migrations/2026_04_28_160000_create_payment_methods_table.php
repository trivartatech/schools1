<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('label', 100);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'is_active']);
        });

        $defaults = [
            ['code' => 'cash',          'label' => 'Cash',          'sort_order' => 1],
            ['code' => 'cheque',        'label' => 'Cheque',        'sort_order' => 2],
            ['code' => 'online',        'label' => 'Online',        'sort_order' => 3],
            ['code' => 'upi',           'label' => 'UPI',           'sort_order' => 4],
            ['code' => 'card',          'label' => 'Card',          'sort_order' => 5],
            ['code' => 'dd',            'label' => 'Demand Draft',  'sort_order' => 6],
            ['code' => 'neft',          'label' => 'NEFT',          'sort_order' => 7],
            ['code' => 'rtgs',          'label' => 'RTGS',          'sort_order' => 8],
            ['code' => 'bank_transfer', 'label' => 'Bank Transfer', 'sort_order' => 9],
        ];

        $schoolIds = DB::table('schools')->pluck('id');
        $now = now();
        foreach ($schoolIds as $schoolId) {
            foreach ($defaults as $row) {
                DB::table('payment_methods')->insert(array_merge($row, [
                    'school_id'  => $schoolId,
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
