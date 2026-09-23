<?php
/**
 * Shared UI helpers and badge-class mappers.
 */

declare(strict_types=1);

function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function statusBadgeClass(string $status): string
{
    return match ($status) {
        'Pending'     => 'bg-warning text-dark',
        'Approved'    => 'bg-success',
        'Rejected'    => 'bg-danger',
        'In-Progress' => 'bg-info text-dark',
        'Resolved'    => 'bg-success',
        default       => 'bg-secondary',
    };
}

function complaintBadgeClass(string $status): string
{
    return match ($status) {
        'Pending'     => 'bg-warning text-dark',
        'In-Progress' => 'bg-info text-dark',
        'Resolved'    => 'bg-success',
        default       => 'bg-secondary',
    };
}

function gateLogBadgeClass(string $type): string
{
    return match ($type) {
        'Check-in'         => 'bg-success',
        'Check-out'        => 'bg-primary',
        'Late-night Leave' => 'bg-danger',
        default            => 'bg-secondary',
    };
}

function roleBadgeClass(string $role): string
{
    return match ($role) {
        'admin'   => 'bg-primary',
        'manager' => 'bg-info text-dark',
        default   => 'bg-light text-dark',
    };
}
