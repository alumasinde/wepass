<?php

namespace App\Core;

class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules)
    {
        foreach ($rules as $field => $rule) {

            $value = $data[$field] ?? null;
            $ruleset = explode('|', $rule);

            foreach ($ruleset as $r) {

                if ($r === 'required' && empty($value)) {
                    $this->errors[$field][] = "{$field} is required";
                }

                if ($r === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = "Invalid email";
                }

                if (str_starts_with($r, 'min:')) {
                    $min = explode(':', $r)[1];
                    if (strlen($value) < $min) {
                        $this->errors[$field][] = "{$field} must be at least {$min}";
                    }
                }
            }
        }

        return empty($this->errors);
    }

    public function errors()
    {
        return $this->errors;
    }
}
