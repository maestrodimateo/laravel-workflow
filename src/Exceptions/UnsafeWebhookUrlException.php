<?php

namespace Maestrodimateo\Workflow\Exceptions;

use RuntimeException;

/**
 * Thrown when a webhook URL is rejected by the SSRF guard (disallowed scheme,
 * host not on the allow-list, or resolving to a non-public address).
 */
class UnsafeWebhookUrlException extends RuntimeException {}
