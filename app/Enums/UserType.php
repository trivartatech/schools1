<?php

namespace App\Enums;

enum UserType: string
{
    case SuperAdmin = 'super_admin';
    case Admin      = 'admin';
    case SchoolAdmin = 'school_admin';
    case Principal  = 'principal';
    case Teacher    = 'teacher';
    case Student    = 'student';
    case Parent     = 'parent';
    case Accountant = 'accountant';
    case Driver          = 'driver';
    case Conductor       = 'conductor';
    case FrontGateKeeper = 'front_gate_keeper';
    case ItSupport       = 'it_support';
    // Photographer: synthetic per-school login used by external photographers
    // during ID-card photoshoots. No Spatie role, hidden from user listings,
    // token issued with ['photographer'] ability.
    case Photographer    = 'photographer';

    public function label(): string
    {
        return match($this) {
            self::SuperAdmin       => 'Super Admin',
            self::Admin            => 'Admin',
            self::SchoolAdmin      => 'School Admin',
            self::Principal        => 'Principal',
            self::Teacher          => 'Teacher',
            self::Student          => 'Student',
            self::Parent           => 'Parent',
            self::Accountant       => 'Accountant',
            self::Driver           => 'Driver',
            self::Conductor        => 'Conductor',
            self::FrontGateKeeper  => 'Front Gate Keeper',
            self::ItSupport        => 'IT Support',
            self::Photographer     => 'Photographer',
        };
    }

    /** Types that count as school management (admin-level access) */
    public static function managementTypes(): array
    {
        return [
            self::Admin->value,
            self::SchoolAdmin->value,
            self::Principal->value,
        ];
    }

    /** Types that count as staff (have a Staff profile) */
    public static function staffTypes(): array
    {
        return [
            self::Admin->value,
            self::SchoolAdmin->value,
            self::Principal->value,
            self::Teacher->value,
            self::Accountant->value,
        ];
    }

    public function isManagement(): bool
    {
        return in_array($this->value, self::managementTypes());
    }

    public function isStaff(): bool
    {
        return in_array($this->value, self::staffTypes());
    }

    /**
     * Returns the Spatie role name that should be assigned to this user_type.
     * This is the single source of truth for the type → role mapping,
     * used by UserObserver (auto-assign on create/update) and the roles:sync
     * command as well as the RolePermissionSeeder.
     *
     * Returns null for user types that have no corresponding Spatie role.
     */
    public function toSpatieRole(): ?string
    {
        return match($this) {
            self::SuperAdmin  => 'super_admin',
            self::Admin       => 'admin',
            self::SchoolAdmin => 'school_admin',
            self::Principal   => 'principal',
            self::Teacher     => 'teacher',
            self::Student     => 'student',
            self::Parent      => 'parent',
            self::Accountant  => 'accountant',
            self::Driver           => 'driver',
            self::Conductor        => 'conductor',
            self::FrontGateKeeper  => 'front_gate_keeper',
            self::ItSupport        => 'it_support',
            // Photographer has no Spatie role — access is gated entirely by
            // user_type checks + the 'photographer' Sanctum token ability.
            self::Photographer     => null,
        };
    }
}
