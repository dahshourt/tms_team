-- =============================================================================
-- Fix: In House workflow + app_support=1 group assignment
-- =============================================================================
-- Business Rule:
--   When current status = "Pending Operation DM and Capacity Approval"
--   AND app_support = 1
--   AND workflow type = "In House"
--   AND the next status IS "Application Support Production Deployment Pre-requisites"
--   => Assign group to "CR Team Admin" instead of "Application Support"
-- =============================================================================

-- Step 1: Preview what will change (run this first to verify)
SELECT
    nw.id                        AS workflow_id,
    nw.from_status_id,
    s_from.name                  AS from_status_name,
    nw.to_status_id,
    s_to.name                    AS to_status_name,
    nw.group_id,
    g.name                       AS current_group_name,
    nw.type_id                   AS workflow_type_id,
    wt.name                      AS workflow_type_name
FROM new_workflow nw
JOIN statuses s_from ON s_from.id = nw.from_status_id
JOIN statuses s_to   ON s_to.id   = nw.to_status_id
JOIN groups   g      ON g.id      = nw.group_id
JOIN workflow_types wt ON wt.id   = nw.type_id
WHERE s_from.name = 'Pending Operation DM and Capacity Approval'
  AND s_to.name   = 'Application Support Production Deployment Pre-requisites'
  AND wt.name     = 'In House'
  AND g.name      = 'Application Support'
  AND nw.active   = 1;

-- Step 2: Apply the fix
-- Reassign group from "Application Support" to "CR Team Admin"
-- only for the specific transition described above.
UPDATE new_workflow nw
JOIN statuses s_from   ON s_from.id = nw.from_status_id
JOIN statuses s_to     ON s_to.id   = nw.to_status_id
JOIN groups g_old      ON g_old.id  = nw.group_id
JOIN groups g_new      ON g_new.name = 'CR Team Admin'
JOIN workflow_types wt ON wt.id     = nw.type_id
SET nw.group_id   = g_new.id,
    nw.updated_at = NOW()
WHERE s_from.name = 'Pending Operation DM and Capacity Approval'
  AND s_to.name   = 'Application Support Production Deployment Pre-requisites'
  AND wt.name     = 'In House'
  AND g_old.name  = 'Application Support'
  AND nw.active   = 1;

-- Step 3: Verify the result
SELECT
    nw.id                        AS workflow_id,
    s_from.name                  AS from_status_name,
    s_to.name                    AS to_status_name,
    g.name                       AS group_name_after_fix,
    wt.name                      AS workflow_type_name
FROM new_workflow nw
JOIN statuses s_from ON s_from.id = nw.from_status_id
JOIN statuses s_to   ON s_to.id   = nw.to_status_id
JOIN groups   g      ON g.id      = nw.group_id
JOIN workflow_types wt ON wt.id   = nw.type_id
WHERE s_from.name = 'Pending Operation DM and Capacity Approval'
  AND s_to.name   = 'Application Support Production Deployment Pre-requisites'
  AND wt.name     = 'In House'
  AND nw.active   = 1;
