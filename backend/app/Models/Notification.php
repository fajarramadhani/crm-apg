<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    /**
     * Mark the notification as archived.
     *
     * @return void
     */
    public function archive()
    {
        $this->update(['archived_at' => $this->freshTimestamp()]);
    }

    /**
     * Determine if the notification is archived.
     *
     * @return bool
     */
    public function archived()
    {
        return ! is_null($this->archived_at);
    }
}
