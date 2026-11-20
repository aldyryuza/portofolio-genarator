<?php

namespace App\Services\Media;

use RuntimeException;

/**
 * Thrown when an upload passed request validation but its content is not a
 * safe, decodable file. The message is safe to show to the owner.
 */
class InvalidMediaException extends RuntimeException
{
    //
}
