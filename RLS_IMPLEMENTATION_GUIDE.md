# Row-Level Security (RLS) Implementation Guide

## Overview

This system automatically filters Superset dashboards by `agent_id` and `role_id` using the embedded SDK's RLS feature.

## How It Works

1. **User opens dashboard** → Frontend reads `user.agent.id` and `user.role_id`
2. **RLS rules are created** → `[{ dataset: X, clause: "agent_id = Y" }]`
3. **Backend sends to Superset** → Guest token includes RLS rules
4. **Superset filters data** → Only shows data matching the RLS clause

## SQL Files Status

### Files with Agent-Level RLS ✅
These files include `agent_id` in SELECT and will be automatically filtered:

- `agent_performance.sql` - Line 97: `a.id as agent_id`
- `agent_personal_kpi.sql` - Line 104: `a.id as agent_id`
- `csat_metrics.sql` - Line 10: `a.id as agent_id`
- `kpi_acw.sql` - Line 8: `a.id as agent_id`
- `kpi_aht.sql` - Line 8: `a.id as agent_id`
- `kpi_fcr.sql` - Line 8: `a.id as agent_id`
- `kpi_utilisation.sql` - Line 46: `a.id as agent_id`

### Files with Department/Queue Level Metrics
These files aggregate at department or queue level and don't need agent filtering:

- `kpi_abandon_rate.sql` - Department-level metrics
- `kpi_asa.sql` - Department-level metrics
- `kpi_short_abandon.sql` - Department-level metrics
- `queue_stats.sql` - Queue-level metrics
- `ticket_sla.sql` - Department-level metrics

## Setup Steps

### 1. Run Database Migration

```bash
php artisan migrate
```

This adds the `dataset_ids` column to `superset_dashboards` table.

### 2. Create Datasets in Superset

For each SQL file:

1. Go to Superset → **SQL Lab**
2. Run your SQL query
3. Click **Save** → **Save as Dataset**
4. Note the Dataset ID from URL: `/dataset/edit/{ID}`

### 3. Configure Dashboard in CRM

1. **System Administration** → **Report Configuration**
2. Create/Edit dashboard
3. Set **Dataset IDs**: `5` (comma-separated if multiple: `5, 6, 7`)
4. Save

### 4. Test RLS Filtering

Login as different agents and verify:
- Agent A (id=1) sees only their data
- Agent B (id=2) sees only their data
- Supervisors see all data (if role_id allows)

## RLS Rules Generated

For each dataset configured in the dashboard, the system automatically generates:

```json
[
  {
    "dataset": 5,
    "clause": "agent_id = 42"
  },
  {
    "dataset": 5,
    "clause": "role_id = 4"
  },
  {
    "dataset": 5,
    "clause": "user_id = 123"
  }
]
```

## Important Notes

1. **Column Names Matter**: Your SQL SELECT must include `agent_id` column for RLS to work
2. **Multiple Datasets**: If a dashboard uses multiple datasets, add all IDs: `5, 6, 7`
3. **Department-Level Dashboards**: Don't set dataset_ids for department-level reports
4. **Testing**: Always test with different user accounts to verify RLS is working

## Troubleshooting

### Issue: RLS not filtering data

**Check:**
1. Dataset ID is correct in dashboard configuration
2. SQL query includes `a.id as agent_id` in SELECT
3. User has `agent` relationship populated
4. Browser console for any errors

### Issue: "Column not found" error

**Solution:**
Make sure your SQL includes the column that RLS is filtering on:
```sql
SELECT
    a.id as agent_id,  -- Required for RLS filtering
    ...
FROM agents a
```

### Issue: Multiple datasets not filtering

**Solution:**
Add all dataset IDs to the dashboard configuration:
```
Dataset IDs: 5, 6, 7
```

## File Locations

- **Backend RLS Logic**: `app/Http/Controllers/Api/SupersetController.php:160-167`
- **Frontend RLS**: `crm-frontend/src/components/SupersetDashboardModal.tsx:42-64`
- **Dashboard Form**: `crm-frontend/src/pages/system-administration/report-configuration/components/DashboardForm.tsx:214-225`
- **SQL Files**: `crm-reporting/sql/`

## Example Configuration

**Dashboard**: Agent Personal KPI
- **Dataset ID**: `5`
- **SQL File**: `agent_personal_kpi.sql`
- **RLS Applied**: `agent_id = {current_user_agent_id}`

**Dashboard**: Queue Statistics
- **Dataset ID**: (leave empty)
- **SQL File**: `queue_stats.sql`
- **RLS Applied**: None (department-level metrics)

