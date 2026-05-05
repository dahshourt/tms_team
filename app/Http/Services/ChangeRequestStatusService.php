<?php

namespace App\Http\Services;

use App\Http\Services\WorkflowGroupResolver;
use App\Models\ChangeRequest;
use App\Models\Group;
use App\Models\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles status transitions for Change Requests.
 *
 * Group-assignment rules are centralised in WorkflowGroupResolver so that
 * all overrides (patches) are applied consistently every time a status
 * transition occurs.
 */
class ChangeRequestStatusService
{
    /**
     * Advance a Change Request to the given next status and assign the
     * correct group, applying all business-rule patches.
     *
     * @param  int    $changeRequestId
     * @param  int    $nextStatusId
     * @param  array  $extra            Any additional payload (e.g. comments).
     * @return bool
     */
    public function transition(int $changeRequestId, int $nextStatusId, array $extra = []): bool
    {
        DB::beginTransaction();
        try {
            /** @var ChangeRequest $cr */
            $cr = ChangeRequest::with('workflowType')->findOrFail($changeRequestId);

            // --- Resolve status names -------------------------------------------
            $currentStatus = Status::findOrFail($cr->current_status_id);
            $nextStatus    = Status::findOrFail($nextStatusId);

            // --- Resolve the default group from the workflow table ---------------
            $defaultGroupName = $this->resolveDefaultGroup($cr, $nextStatus);

            // --- Apply all business-rule patches ---------------------------------
            // WorkflowGroupResolver is the single place that applies every patch,
            // including InHouseAppSupportGroupPatch.
            $finalGroupName = WorkflowGroupResolver::resolve(
                $cr,
                $currentStatus->name,
                $nextStatus->name,
                $defaultGroupName
            );

            Log::info('[ChangeRequestStatusService] transition', [
                'cr_id'          => $changeRequestId,
                'current_status' => $currentStatus->name,
                'next_status'    => $nextStatus->name,
                'default_group'  => $defaultGroupName,
                'final_group'    => $finalGroupName,
            ]);

            // --- Persist the new status & group ----------------------------------
            $group = Group::where('name', $finalGroupName)->firstOrFail();

            $cr->current_status_id = $nextStatusId;
            $cr->group_id          = $group->id;
            $cr->save();

            // Optionally record a status-history entry here.

            DB::commit();
            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[ChangeRequestStatusService] transition failed', [
                'cr_id' => $changeRequestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Derive the default group name from the workflow definition for this
     * Change Request + next status combination.
     *
     * Replace the body of this method with however your project currently
     * looks up the group from `new_workflow` or a similar table.
     *
     * @param  ChangeRequest  $cr
     * @param  Status         $nextStatus
     * @return string
     */
    private function resolveDefaultGroup(ChangeRequest $cr, Status $nextStatus): string
    {
        // Example: look up from the new_workflow table.
        // Adapt column names to match your actual schema.
        $row = DB::table('new_workflow')
            ->where('from_status_id', $cr->current_status_id)
            ->where('type_id', $cr->workflow_type_id)
            ->where('to_status_label', $nextStatus->name)
            ->where('active', '1')
            ->first();

        return $row?->assigned_group ?? 'Application Support';
    }
}
