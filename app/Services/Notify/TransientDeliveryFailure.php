<?php

namespace App\Services\Notify;

use RuntimeException;

/** Might work next time (5xx, 408/425/429, timeouts, connection errors, SMTP errors). */
class TransientDeliveryFailure extends RuntimeException {}
