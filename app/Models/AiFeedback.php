<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AiFeedback extends Model {
    protected $table    = 'ai_feedbacks';
    protected $fillable = ['message_id','rating','comment','is_helpful','language_detected','domain_detected'];
    public $timestamps  = false;
}