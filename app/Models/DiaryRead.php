<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiaryRead extends Model
{
    protected $fillable = ['diary_id', 'user_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function diary() { return $this->belongsTo(StudentDiary::class); }
    public function user()  { return $this->belongsTo(User::class); }
}
