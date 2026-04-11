<?php

namespace App\Policies;

use App\Models\FeePayment;
use App\Models\User;

class FeePaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_fee');
    }

    public function view(User $user, FeePayment $feePayment): bool
    {
        if (!$user->hasPermissionTo('view_fee') && !$user->hasPermissionTo('view_own_fee')) {
            return false;
        }

        // Tenant isolation
        if ($user->school_id && $user->school_id !== $feePayment->school_id) {
            return false;
        }

        // Parents/students can only view their own payments
        if ($user->isParent()) {
            $parentStudentIds = $user->studentParent?->students?->pluck('id') ?? collect();
            return $parentStudentIds->contains($feePayment->student_id);
        }

        if ($user->isStudent()) {
            return $feePayment->student_id === $user->student?->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_fee') || $user->isAccountant();
    }

    public function update(User $user, FeePayment $feePayment): bool
    {
        return $user->hasPermissionTo('edit_fee')
            && ($user->school_id === null || $user->school_id === $feePayment->school_id);
    }

    public function delete(User $user, FeePayment $feePayment): bool
    {
        // Only admins can delete fee payments — accountants cannot
        return $user->hasPermissionTo('delete_fee')
            && $user->isAdmin()
            && ($user->school_id === null || $user->school_id === $feePayment->school_id);
    }

    public function refund(User $user, FeePayment $feePayment): bool
    {
        return $user->hasPermissionTo('edit_fee')
            && $user->isAdmin()
            && ($user->school_id === null || $user->school_id === $feePayment->school_id);
    }

    /**
     * Waive (cancel) an outstanding fee — requires explicit 'waive_fee' permission.
     * Admins + accountants with the permission can waive; students/parents cannot.
     */
    public function waive(User $user, FeePayment $feePayment): bool
    {
        return $user->hasPermissionTo('waive_fee')
            && ($user->school_id === null || $user->school_id === $feePayment->school_id);
    }

    /**
     * Generate/print a receipt PDF for a fee payment.
     * Students and parents can generate their own receipts;
     * staff need 'generate_fee_receipt' permission.
     */
    public function generateReceipt(User $user, FeePayment $feePayment): bool
    {
        if ($user->school_id && $user->school_id !== $feePayment->school_id) {
            return false;
        }

        // Own payment — students and parents with the permission
        if ($user->isStudent() && $user->hasPermissionTo('generate_fee_receipt')) {
            return $feePayment->student_id === $user->student?->id;
        }

        if ($user->isParent() && $user->hasPermissionTo('generate_fee_receipt')) {
            $parentStudentIds = $user->studentParent?->students?->pluck('id') ?? collect();
            return $parentStudentIds->contains($feePayment->student_id);
        }

        // Staff with the explicit permission
        return $user->hasPermissionTo('generate_fee_receipt');
    }

    /**
     * Apply a manual discount to a fee — requires 'override_fee_discount'.
     * Intended for admin/principal use only.
     */
    public function applyDiscount(User $user, FeePayment $feePayment): bool
    {
        return $user->hasPermissionTo('override_fee_discount')
            && ($user->school_id === null || $user->school_id === $feePayment->school_id);
    }
}
