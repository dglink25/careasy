<?php

namespace App\Models;


use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

abstract class BaseModel extends Model{
    protected function serializeDate(DateTimeInterface $date): string  {
        return Carbon::instance($date)
            ->timezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }
}