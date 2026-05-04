<?php

namespace App\Http\Services\Patches;

/**
 * Business Rule Patch: In House + app_support=1 group override
 *
 * When:
 *   - Current status = "Pending Operation DM and Capacity Approval"
 *   - app_support    = 1  (on the change request)
 *   - Workflow type  = "In House"
 *   - Next status    = "Application Support Production Deployment Pre-requisites"
 *
 * Then:
 *   - Assign group to "CR Team Admin" instead of "Application Support"
 */
class InHouseAppSupportGroupPatch
{
    const CURRENT_STATUS  = 'Pending Operation DM and Capacity Approval';
    const NEXT_STATUS     = 'Application Support Production Deployment Pre-requisites';
    const WORKFLOW_TYPE   = 'In House';
    const ORIGINAL_GROUP  = 'Application Support';
    const OVERRIDE_GROUP  = 'CR Team Admin';

    /**
     * Apply the group override if all conditions are met.
     *
     * @param  object  $changeRequest   The CR model (must have app_support & workflowType relation)
     * @param  string  $currentStatus   Name of the current status
     * @param  string  $nextStatus      Name of the resolved next status
     * @param  string  $assignedGroup   Group name resolved by the normal workflow logic
     * @return string                   Final group name to use
     */
    public static function resolveGroup(
        object $changeRequest,
        string $currentStatus,
        string $nextStatus,
        string $assignedGroup
    ): string {
        if (
            $currentStatus === self::CURRENT_STATUS
            && (int) $changeRequest->app_support === 1
            && optional($changeRequest->workflowType)->name === self::WORKFLOW_TYPE
            && $nextStatus === self::NEXT_STATUS
        ) {
            return self::OVERRIDE_GROUP;
        }

        return $assignedGroup;
    }
}
