<?php

namespace App\Modules\Visitors\DTOs;

/**
 * VisitorDTO — per-database isolation model.
 * No tenant_id property needed.
 */
class VisitorDTO
{
    public readonly string  $first_name;
    public readonly string  $last_name;
    public readonly ?int    $id_type_id;
    public readonly string  $id_number;
    public readonly string  $phone;
    public readonly string  $email;
    public readonly ?int    $company_id;
    public readonly ?string $notes;
    public readonly ?int    $created_by;

    public function __construct(
        string  $first_name,
        string  $last_name,
        ?int    $id_type_id  = null,
        string  $id_number   = '',
        string  $phone       = '',
        string  $email       = '',
        ?int    $company_id  = null,
        ?string $notes       = null,
        ?int    $created_by  = null
    ) {
        $this->first_name  = trim($first_name);
        $this->last_name   = trim($last_name);
        $this->id_type_id  = $id_type_id;
        $this->id_number   = trim($id_number);
        $this->phone       = trim($phone);
        $this->email       = trim($email);
        $this->company_id  = $company_id;
        $this->notes       = $notes;
        $this->created_by  = $created_by;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            first_name:  $data['first_name']  ?? '',
            last_name:   $data['last_name']   ?? '',
            id_type_id:  isset($data['id_type_id']) && $data['id_type_id'] !== '' ? (int) $data['id_type_id'] : null,
            id_number:   $data['id_number']   ?? '',
            phone:       $data['phone']        ?? '',
            email:       $data['email']        ?? '',
            company_id:  isset($data['company_id']) && $data['company_id'] !== '' ? (int) $data['company_id'] : null,
            notes:       $data['notes']        ?? null,
            created_by:  isset($data['created_by']) && $data['created_by'] !== '' ? (int) $data['created_by'] : null,
        );
    }
}
