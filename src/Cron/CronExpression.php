<?php

/**
 * This file is part of Milpa Ops — the system's metabolism: security,
 * backup, scheduled maintenance and bootstrap for the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/ops
 */

declare(strict_types=1);

namespace Milpa\Ops\Cron;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A standard 5-field cron expression (`minute hour day-of-month month day-of-week`)
 * evaluated against a given instant. YAGNI on purpose: no seconds field, no `@daily`-style
 * shorthands — just the 5 standard fields and 4 operator shapes a real deployment needs:
 * `*` (any), `a,b` (list), `a-b` (range) and a step ("star, slash, N"). {@see TaskDefinition} is the
 * only intended caller — it pairs an expression with the callback it gates.
 *
 * Every literal value and every range endpoint is bounds-checked against its field's valid
 * range at construction — an out-of-range value (e.g. minute `99`, hour `25`) throws
 * immediately instead of silently building an expression that can never become due. A range
 * whose start is greater than its end (e.g. minute `5-1`) is likewise rejected at
 * construction in every field — this package supports ascending ranges only, it does not
 * infer a "wrap around the field's max" from a descending pair. The single deliberate
 * exception is day-of-week: it additionally accepts `7` as the standard cron alias for
 * Sunday (`0`), matching `crontab(5)`, so an otherwise-ascending range like `5-7` (5 <= 7)
 * wraps to {5, 6, 0} (Fri, Sat, Sun) once `7` normalizes to `0` for matching.
 */
final class CronExpression
{
    private const int FIELD_COUNT = 5;

    /** @var list<string> Exactly 5 raw field strings: minute, hour, day-of-month, month, day-of-week. */
    private readonly array $fields;

    /**
     * @throws InvalidArgumentException if `$expression` does not have exactly 5
     *                                  whitespace-separated fields, any field uses a shape other than `*`, a comma
     *                                  list, a range, or a step (`*` followed by `/N`), or any literal value / range
     *                                  endpoint falls outside that field's valid range.
     */
    public function __construct(string $expression)
    {
        $fields = preg_split('/\s+/', trim($expression));

        if ($fields === false || count($fields) !== self::FIELD_COUNT) {
            throw new InvalidArgumentException(sprintf(
                'Cron expression must have exactly %d fields (minute hour day-of-month month day-of-week), got %d in "%s".',
                self::FIELD_COUNT,
                $fields === false ? 0 : count($fields),
                $expression,
            ));
        }

        // Validate each field's shape AND value bounds up front (against its own min, using
        // min as a throwaway probe value) so a malformed or out-of-range expression fails
        // fast at construction rather than lazily — or worse, silently never matching —
        // the first time isDue() happens to evaluate that field.
        foreach ($this->fieldSpecs() as $index => [$name, , $min, $max, $allowSundaySeven]) {
            $this->fieldMatches($fields[$index], $min, $name, $min, $max, $allowSundaySeven);
        }

        $this->fields = $fields;
    }

    /**
     * True when `$now` matches all 5 fields of this expression.
     */
    public function isDue(DateTimeImmutable $now): bool
    {
        foreach ($this->fieldSpecs() as $index => [$name, $formatChar, $min, $max, $allowSundaySeven]) {
            $value = (int) $now->format($formatChar);

            if (!$this->fieldMatches($this->fields[$index], $value, $name, $min, $max, $allowSundaySeven)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether `$value` (already extracted from a datetime, within `[$min, $max]`) satisfies
     * `$field` — a comma-separated list of one or more parts, each either `*`, a plain
     * integer, an `a-b` range, or a step (`*` followed by `/N`). Matches when ANY part matches
     * (standard cron list semantics).
     *
     * @throws InvalidArgumentException if any comma-separated part is not one of the 4
     *                                  supported shapes, a step (`*` followed by `/N`) has a non-positive N, a literal
     *                                  value / range endpoint falls outside `[$min, $max]` (`[$min, $max + 1]` when
     *                                  `$allowSundaySeven` is true), or a range's start is greater than its end (before
     *                                  the day-of-week Sunday-7 normalization — see {@see self::partMatches()}).
     */
    private function fieldMatches(string $field, int $value, string $fieldName, int $min, int $max, bool $allowSundaySeven): bool
    {
        $matched = false;

        foreach (explode(',', $field) as $part) {
            if ($this->partMatches($part, $value, $fieldName, $min, $max, $allowSundaySeven)) {
                $matched = true;
            }
        }

        return $matched;
    }

    private function partMatches(string $part, int $value, string $fieldName, int $min, int $max, bool $allowSundaySeven): bool
    {
        if ($part === '*') {
            return true;
        }

        // Day-of-week additionally accepts 7 as the standard cron alias for Sunday (0),
        // so its literal/range bounds check allows one past $max; every other field's
        // valid literal range is exactly [$min, $max].
        $literalMax = $allowSundaySeven ? $max + 1 : $max;

        if (str_starts_with($part, '*/')) {
            $step = substr($part, 2);

            if (!ctype_digit($step) || (int) $step < 1) {
                throw new InvalidArgumentException(sprintf('Invalid step "%s" in cron %s field — expected "*/N" with N >= 1.', $part, $fieldName));
            }

            return ($value - $min) % (int) $step === 0;
        }

        if (str_contains($part, '-')) {
            [$start, $end] = explode('-', $part, 2);

            if (!ctype_digit($start) || !ctype_digit($end)) {
                throw new InvalidArgumentException(sprintf('Invalid range "%s" in cron %s field — expected "a-b" with integer bounds.', $part, $fieldName));
            }

            $startValue = $this->assertWithinBounds((int) $start, $min, $literalMax, $part, $fieldName);
            $endValue = $this->assertWithinBounds((int) $end, $min, $literalMax, $part, $fieldName);

            // Ordering is validated on the RAW endpoints, before the Sunday-7 normalization
            // below — "5-7" in day-of-week is a valid ascending range (5 <= 7) whose wrap only
            // emerges once 7 normalizes to 0. Everywhere else (and dow ranges that don't touch
            // 7, e.g. "5-1") an inverted range is just invalid — YAGNI: this package supports
            // ascending ranges plus the single day-of-week 7=Sunday alias, nothing else that
            // crosses field boundaries.
            if ($startValue > $endValue) {
                throw new InvalidArgumentException(sprintf(
                    'Inverted range "%s" in cron %s field — start (%d) must not be greater than end (%d).',
                    $part,
                    $fieldName,
                    $startValue,
                    $endValue,
                ));
            }

            $normalizedStart = $this->normalizeSunday($startValue, $allowSundaySeven);
            $normalizedEnd = $this->normalizeSunday($endValue, $allowSundaySeven);

            if ($allowSundaySeven && $normalizedStart > $normalizedEnd) {
                // Day-of-week only, and only reachable here because the raw-endpoint check
                // above already guaranteed startValue <= endValue: "7" (Sunday alias)
                // normalizes to 0, which can flip that validated ascending range backwards,
                // e.g. "5-7" (Fri-Sat-Sun) becomes normalizedStart=5, normalizedEnd=0. Treat
                // it as the circular range it represents instead of an always-false one.
                return $value >= $normalizedStart || $value <= $normalizedEnd;
            }

            return $value >= $normalizedStart && $value <= $normalizedEnd;
        }

        if (!ctype_digit($part)) {
            throw new InvalidArgumentException(sprintf('Invalid value "%s" in cron %s field — expected an integer, "*", "a-b", or "*/N".', $part, $fieldName));
        }

        $literal = $this->assertWithinBounds((int) $part, $min, $literalMax, $part, $fieldName);

        return $value === $this->normalizeSunday($literal, $allowSundaySeven);
    }

    /**
     * @throws InvalidArgumentException if `$value` is outside `[$min, $max]`.
     */
    private function assertWithinBounds(int $value, int $min, int $max, string $part, string $fieldName): int
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException(sprintf(
                'Value %d in cron %s field "%s" is out of range — expected between %d and %d.',
                $value,
                $fieldName,
                $part,
                $min,
                $max,
            ));
        }

        return $value;
    }

    /**
     * Day-of-week's `7` is the standard cron alias for Sunday (`0`) — normalize it so every
     * comparison downstream works against `DateTimeImmutable::format('w')`'s native 0-6 range.
     * A no-op for every other field, where `$allowSundaySeven` is always false.
     */
    private function normalizeSunday(int $value, bool $allowSundaySeven): int
    {
        return $allowSundaySeven && $value === 7 ? 0 : $value;
    }

    /**
     * The 5 standard fields in order, each as `[field name, DateTimeImmutable::format() char,
     * min, max, allow-7-as-Sunday]`. Index in this list matches index in `$fields` — both are
     * `[minute, hour, day-of-month, month, day-of-week]`.
     *
     * @return array{
     *     0: array{0: string, 1: string, 2: int, 3: int, 4: bool},
     *     1: array{0: string, 1: string, 2: int, 3: int, 4: bool},
     *     2: array{0: string, 1: string, 2: int, 3: int, 4: bool},
     *     3: array{0: string, 1: string, 2: int, 3: int, 4: bool},
     *     4: array{0: string, 1: string, 2: int, 3: int, 4: bool},
     * }
     */
    private function fieldSpecs(): array
    {
        return [
            0 => ['minute', 'i', 0, 59, false],
            1 => ['hour', 'G', 0, 23, false],       // 0-23, no leading zero
            2 => ['day-of-month', 'j', 1, 31, false], // no leading zero
            3 => ['month', 'n', 1, 12, false],        // no leading zero
            4 => ['day-of-week', 'w', 0, 6, true],    // 0 (Sunday) - 6 (Saturday); 7 also accepted as Sunday
        ];
    }
}
