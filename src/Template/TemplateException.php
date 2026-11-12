<?php
/**
 * Template error.
 *
 * @package Saizul\CourierExport
 */

declare(strict_types=1);

namespace Saizul\CourierExport\Template;

use InvalidArgumentException;

/**
 * Thrown when a column template cannot be compiled.
 */
final class TemplateException extends InvalidArgumentException {
}
