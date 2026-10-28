<?php
/**
 * Small fluent validator. Collects the first error per field.
 *
 *   $v = new Validator($_POST);
 *   $v->required('email', 'Email')->email('email');
 *   if ($v->fails()) { ... $v->errors() ... }
 */

declare(strict_types=1);

final class Validator
{
    private array $errors = [];

    public function __construct(private readonly array $data)
    {
    }

    public function value(string $field): string
    {
        $value = $this->data[$field] ?? '';
        return is_string($value) ? trim($value) : (is_scalar($value) ? (string) $value : '');
    }

    private function add(string $field, string $message): self
    {
        $this->errors[$field] ??= $message;
        return $this;
    }

    public function required(string $field, string $label): self
    {
        return $this->value($field) === '' ? $this->add($field, "$label is required.") : $this;
    }

    public function email(string $field): self
    {
        $value = $this->value($field);
        return $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL) ? $this->add($field, 'Please enter a valid email address.') : $this;
    }

    public function length(string $field, string $label, int $min, int $max): self
    {
        $len = mb_strlen($this->value($field));
        if ($this->value($field) === '' && $min === 0) {
            return $this;
        }
        if ($len < $min) {
            return $this->add($field, "$label must be at least $min characters.");
        }
        return $len > $max ? $this->add($field, "$label must be at most $max characters.") : $this;
    }

    public function password(string $field): self
    {
        $value = $this->value($field);
        if (mb_strlen($value) < 8 || !preg_match('/[A-Za-z]/', $value) || !preg_match('/\d/', $value)) {
            return $this->add($field, 'Use at least 8 characters with letters and numbers.');
        }
        return $this;
    }

    public function matches(string $field, string $other, string $message): self
    {
        return $this->value($field) !== $this->value($other) ? $this->add($field, $message) : $this;
    }

    public function in(string $field, array $allowed, string $message = 'Please choose a valid option.'): self
    {
        return !in_array($this->value($field), $allowed, true) ? $this->add($field, $message) : $this;
    }

    public function number(string $field, string $label, float $min, float $max): self
    {
        $value = $this->value($field);
        if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            return $this->add($field, "$label must be between $min and $max.");
        }
        return $this;
    }

    public function phone(string $field): self
    {
        $value = $this->value($field);
        return $value !== '' && !preg_match('/^\+?[0-9 ()\-]{6,20}$/', $value) ? $this->add($field, 'Please enter a valid phone number.') : $this;
    }

    public function date(string $field, string $label): self
    {
        $d = DateTime::createFromFormat('Y-m-d', $this->value($field));
        return !$d || $d->format('Y-m-d') !== $this->value($field) ? $this->add($field, "$label must be a valid date.") : $this;
    }

    public function time(string $field, string $label): self
    {
        return !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', substr($this->value($field), 0, 5)) ? $this->add($field, "$label must be a valid time.") : $this;
    }

    public function custom(bool $failed, string $field, string $message): self
    {
        return $failed ? $this->add($field, $message) : $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        return (string) (array_values($this->errors)[0] ?? '');
    }
}
