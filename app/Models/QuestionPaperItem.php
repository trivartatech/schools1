<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionPaperItem extends Model
{
    protected $fillable = [
        'section_id', 'question_text', 'option_a', 'option_b',
        'option_c', 'option_d', 'correct_answer', 'marks', 'sort_order',
    ];

    public function section() { return $this->belongsTo(QuestionPaperSection::class, 'section_id'); }
}
