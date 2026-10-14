# CRM Feature Workflow Steps

This document describes the actual user-facing steps and routes for the six CRM workflow items.

## 1. Priority Matrix

1. There is no separate frontend page called "Priority Matrix." The feature is part of ticket creation and ticket detail.
2. In the app UI, open `Tickets` from the sidebar to create or view tickets.
3. During ticket creation, urgency and impact are used to derive priority on the ticket form.
4. In the ticket detail view, you can see the derived priority shown as "Priority Matrix (Urgency × Impact)."
5. This means the page is:
   - `Tickets` → `Create Ticket` for input
   - `Tickets` → open a ticket for details
6. The backend handles the actual priority mapping and validation during ticket save.
7. Use the derived priority in SLA calculations and reporting.

## 2. Impact & Urgency

1. There is no dedicated frontend page called "Impact & Urgency." It is captured inside the ticket creation flow.
2. Open `Tickets` from the sidebar, then select `Create Ticket`.
3. On the create ticket form, choose Impact and Urgency values.
4. The application derives final ticket priority from the two values automatically.
5. The ticket detail page also shows the combined result when a ticket is loaded.
6. For user navigation, the relevant screen is:
   - `Tickets` → `Create Ticket`
7. The UI does not expose Impact/Urgency as a separate route or admin page.

## 3. SLA Configuration

1. This is the only item with a real frontend admin page.
2. In the sidebar, click `Administration`.
3. On the System Administration page, select the `SLA Management` tab.
4. The actual route is:
   - `/system-administration`
   - and then the SLA Management section inside the page
5. The page is rendered by `crm-frontend/src/pages/system-administration/sla-configuration/index.jsx`.
6. Use this page to manage SLA rules by department, priority, and ticket type.
7. The site saves SLA rules through backend API calls and then uses them for ticket SLA calculations.

## 4. SLA Engine

1. There is no separate UI page called "SLA Engine." It is a backend process.
2. The UI for SLA settings is `Administration` → `SLA Management`.
3. The engine itself runs when tickets are created or updated.
4. It calculates deadlines using priority, department, and configured SLA rules.
5. It also tracks SLA state changes and breaches.
6. The user-facing part is only the SLA settings screen and ticket SLA fields.
7. Any actual engine debug or behavior is in backend services, not a visible page.

## 5. Mitel Inbound Call

1. There is no frontend page for "Mitel Inbound Call" configuration in the current app.
2. Mitel inbound call integration is implemented as backend integration and event handling.
3. The user does not click a Mitel page from the UI for this feature.
4. The front end only displays ticket and communication records once calls are processed.
5. The relevant user view is the `Tickets` list and ticket details for call-related tickets.
6. If you need to configure Mitel integration, that happens in backend/service configuration, not a page.
7. For the current frontend, there is no visible Mitel admin route.

## 6. Escalation Workflow

1. There is no page named "Escalation Workflow" in the frontend.
2. The workflow is implemented in ticket processing logic and SLA escalation rules.
3. In the UI, escalation appears as ticket status changes and reporting data.
4. The user-facing screens are:
   - `Tickets` list
   - ticket detail view
   - administration/reporting pages where SLA and escalation status appear
5. The actual navigation path is not a separate route.
6. The feature is driven by backend rules and role permissions.
7. Track escalation history through ticket details and audit/reporting views.
