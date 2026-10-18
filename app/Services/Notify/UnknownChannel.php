<?php

namespace App\Services\Notify;

use RuntimeException;

/** The caller named a channel that is not one of the company's active channels. */
class UnknownChannel extends RuntimeException {}
