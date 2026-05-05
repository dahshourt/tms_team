<?php

namespace App\Http\Services;

use App\Http\Services\Patches\InHouseAppSupportGroupPatch;
use App\Models\Group;
use App\Models\Status;

/**
 * Resolves the group to assign when a Change Request moves to a new status.
 *
 * This is the single entry-point for group assignment so that all override
 * patches live in one place and are easy to find / test.
 */
class WorkflowGroupResolver
{
    /**
     * Determine the group name that should be assigned for the transition
     * from $currentStatusName -> $nextStatusName on the given Change Request.
     *
     * Call this wherever a group is being set during a status transition,
     * replacing any raw group-name string with the return value of this method.
     *
     * @param  object  $changeRequest      The ChangeRequest Eloquent model.
     *                                      Must have:
     *                                        - app_support  (int / bool)
     *                                        - workflowType (relation, with ->name)
     * @param  string  $currentStatusName  Human-readable name of the CURRENT status.
     * @param  string  $nextStatusName     Human-readable name of the NEXT (target) status.
     * @param  string  $defaultGroup       Group name the normal workflow logic resolved.
     * @return string                       Final group name to use.
     */
    public static function resolve(
        object $changeRequest,
        string $currentStatusName,
        string $nextStatusName,
        string $defaultGroup
    ): string {
        // ----------------------------------------------------------------
        // Patch: In-House + app_support=1 -> override group to CR Team Admin
        // when the next status is "Application Support Production Deployment
        // Pre-requisites".
        // ----------------------------------------------------------------
        $defaultGroup = InHouseAppSupportGroupPatch::resolveGroup(
            $changeRequest,
            $currentStatusName,
            $nextStatusName,
            $defaultGroup
        );

        // Add future patches here in the same pattern:
        // $defaultGroup = SomeOtherPatch::resolveGroup(..., $defaultGroup);

        return $defaultGroup;
    }
}
