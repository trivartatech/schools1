<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionPaperSection extends Model
{
    protected $fillable = [
        'question_paper_id', 'name', 'question_type',
        'marks_per_question', 'num_questions', 'instructions', 'sort_order',
    ];

    public function questionPaper() { return $this->belongsTo(QuestionPaper::class); }
    public function items()         { return $this->hasMany(QuestionPaperItem::class, 'section_id')->orderBy('sort_order'); }
}
