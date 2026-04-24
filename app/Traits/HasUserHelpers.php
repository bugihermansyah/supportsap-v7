<?php

namespace App\Traits;

trait HasUserHelpers
{
    /**
     * Get the active team ID for the user.
     */
    public function getTeamId(): string|int|null
    {
        // Misalkan logika team ID Anda tersimpan di kolom team_id
        return $this->team_id; 
    }
}