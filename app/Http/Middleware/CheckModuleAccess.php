<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    /**
     * Explicit verb overrides for action names that are genuinely ambiguous.
     * Keep this list short — most actions are resolved automatically via
     * prefix/suffix detection in mapActionToVerb().
     */
    private const ACTION_OVERRIDES = [
        // Chat-specific actions whose names don't follow the standard pattern
        'leaveGroup'         => 'delete',
        'cancelScheduled'    => 'delete',
        'startDirect'        => 'create',
        'emergencyBroadcast' => 'create',
        'retryBroadcast'     => 'create',
        'sendEmail'          => 'create',
        'preRegister'        => 'create',

        // Read-only reporting/display actions with unusual names
        'salaryForm'         => 'view',
        'payslip'            => 'view',
        'dayBook'            => 'view',
        'dueReport'          => 'view',
        'sendDueReminder'    => 'view',   // sending a reminder is a reporting action, not a data-write
        'routeReport'        => 'view',
        'feeDefaulters'      => 'view',
        'parentView'         => 'view',
        'parentHistory'      => 'view',
        'followUps'          => 'view',
        'dailyReport'        => 'view',
        'receipt'            => 'view',
        'live'               => 'view',
        'verifyQR'           => 'view',
        'exportQRCodes'      => 'view',
        'emergencyForm'      => 'view',
        'emailTemplates'     => 'view',
        'scheduledQueue'     => 'view',
        'scanner'            => 'view',
    ];

    /**
     * Prefix → verb mapping (longest-match wins via ordered iteration).
     * Handles standard REST names + common ERP conventions.
     */
    private const PREFIX_MAP = [
        'store'    => 'create',
        'create'   => 'create',
        'generate' => 'create',
        'apply'    => 'create',
        'send'     => 'create',
        'retry'    => 'create',
        'destroy'  => 'delete',
        'delete'   => 'delete',
        'remove'   => 'delete',
        'revert'   => 'delete',
        'update'   => 'edit',
        'edit'     => 'edit',
        'toggle'   => 'edit',
        'approve'  => 'edit',
        'reject'   => 'edit',
        'mark'     => 'edit',
        'execute'  => 'edit',
        'upload'   => 'edit',
        'vacate'   => 'edit',
        'transfer' => 'edit',
        'reorder'  => 'edit',
        'check'    => 'edit',
        'acknowledge' => 'edit',
    ];

    /**
     * Suffix → verb mapping for compound names like groupStore, headDestroy.
     */
    private const SUFFIX_MAP = [
        'Store'   => 'create',
        'Create'  => 'create',
        'Destroy' => 'delete',
        'Delete'  => 'delete',
        'Update'  => 'edit',
        'Edit'    => 'edit',
    ];

    /**
     * Portal-friendly actions that parents/students may perform even when
     * they only hold the base view permission. Controllers must enforce
     * own-record scoping separately.
     */
    private const PORTAL_ACTIONS = [
        'createRequest', 'storeRequest',
        'pollConversations', 'messages', 'poll',
        'pinnedMessages', 'searchMessages',
        'markRead', 'typing', 'editMessage',
        'deleteMessage', 'uploadPhoto',
    ];

    public function handle(Request $request, Closure $next, string $module): Response
    {
        // 0. Edition gate: if the module is disabled for this school, the
        //    feature should appear nonexistent — return 404, not 403.
        $school = app()->bound('current_school') ? app('current_school') : null;
        if ($school
            && in_array($module, config('features.modules', []), true)
            && ! $school->isFeatureEnabled($module)
        ) {
            abort(404);
        }

        $action = $request->route()->getActionMethod();
        $user   = auth()->user();
        $verb   = $this->mapActionToVerb($action);
        $ability = "{$verb}_{$module}";

        // 1. Standard permission check.
        if ($user->can($ability)) {
            return $next($request);
        }

        // 2. Portal bypass: parents/students can perform portal-friendly actions
        //    if they hold the base view permission for the module.
        if (
            ($user->isParent() || $user->isStudent())
            && in_array($action, self::PORTAL_ACTIONS, true)
            && ($user->can("view_{$module}") || $user->can('view_portal'))
        ) {
            return $next($request);
        }

        abort(403, "You do not have permission to access {$module}.");
    }

    /**
     * Map a controller action name to a CRUD verb (create/view/edit/delete).
     *
     * Resolution order:
     *   1. Explicit override (ACTION_OVERRIDES)
     *   2. Prefix match (PREFIX_MAP)
     *   3. Suffix match (SUFFIX_MAP) — covers compound names like groupStore
     *   4. Default: 'view'
     */
    private function mapActionToVerb(string $action): string
    {
        // 1. Explicit override
        if (isset(self::ACTION_OVERRIDES[$action])) {
            return self::ACTION_OVERRIDES[$action];
        }

        $lower = lcfirst($action);

        // 2. Prefix match
        foreach (self::PREFIX_MAP as $prefix => $verb) {
            if (str_starts_with($lower, $prefix)) {
                return $verb;
            }
        }

        // 3. Suffix match (for compound names like groupStore, headDestroy)
        foreach (self::SUFFIX_MAP as $suffix => $verb) {
            if (str_ends_with($action, $suffix)) {
                return $verb;
            }
        }

        // 4. Default
        return 'view';
    }
}
