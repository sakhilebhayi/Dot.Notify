<?php

namespace App\Services\Notify;

use RuntimeException;

/** An outbound URL that Notify refuses to call (see UrlGuard). */
class UnsafeUrl extends RuntimeException {}
