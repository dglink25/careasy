<?php

namespace App\Traits;

trait CastsBooleansProperly
{
    /**
     * Cast boolean attributes properly for PostgreSQL
     * 
     * PostgreSQL is strict about types and doesn't accept 1/0 for boolean columns
     * This ensures proper type casting before saving to database
     */
    public function setAttribute($key, $value)
    {
        // Get the casts for this model
        $casts = $this->getCasts();
        
        // If the attribute is cast as boolean and the value is an integer
        if (isset($casts[$key]) && $casts[$key] === 'boolean' && is_int($value)) {
            $value = (bool) $value;
        }
        
        return parent::setAttribute($key, $value);
    }
}
