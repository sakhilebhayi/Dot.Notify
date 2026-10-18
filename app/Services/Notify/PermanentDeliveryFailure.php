<?php

namespace App\Services\Notify;

use RuntimeException;

/** Retrying cannot help (4xx from the destination, bad address, blocked URL). */
class PermanentDeliveryFailure extends RuntimeException {}
