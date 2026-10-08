<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a change would leave the system without any admin.
 */
class LastAdminException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('Sistēmā jāpaliek vismaz vienam administratoram.'));
    }
}
