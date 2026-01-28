Hospital CMMS
Computerized Maintenance Management System (CMMS) for hospital equipment — lightweight PHP/MySQL application.

Overview
This repository provides a small CMMS used to declare equipment failures (pannes), create maintenance work orders, assign technicians, and track interventions.

Key roles:

Engineer: review pannes, create/assign maintenance, view reports
Technician: declare pannes, view assigned work orders, update maintenance progress
Project Structure (important files)
auth/: login/logout handlers (auth/login.php)
config/: DB schema and configuration (config/db.sql, config/config.php) -- engineer/: engineer UI and actions (engineer/accept_panne.php, engineer/dashboard.php) -- technician/: technician UI (technician/declare_panne.php, technician/dashboard.php) -- includes/: shared code (includes/db.php, includes/auth_check.php) -- cmms root SQL: config/db.sql — creates tables and demo data
Full project structure
cmms/
├─ add_technician_column.php
├─ ARCHITECTURE_DIAGRAM.txt
├─ CHANGEMENT_SUMMARY.md
├─ create_users_table.php
├─ DOCUMENTATION_INDEX.md
├─ IMPLEMENTATION_CHECKLIST.md
├─ IMPLEMENTATION_COMPLETE.txt
├─ IMPLEMENTATION_GUIDE.md
├─ init_reports_table.php
├─ INSTALLATION_CHECKLIST.md
├─ PANNE_SYSTEM_DOCUMENTATION.md
├─ QUICK_START_GUIDE.md
├─ RAPPORT_INTERVENTION_README.md
├─ README_IMPLEMENTATION.md
├─ README.md (project README)
├─ SETUP_PANNE.php
├─ test_connection.php
├─ test_suppliers.php
├─ test.php
├─ testdb.php
├─ verify_setup.php
├─ auth/
│  ├─ login.php
│  └─ logout.php
├─ config/
│  ├─ add_reports_table.sql
│  ├─ config.php
│  └─ db.sql
├─ engineer/
│  ├─ accept_panne.php
│  ├─ add_equipment.php
│  ├─ add_intervention.php
│  ├─ add_maintenance.php
│  ├─ add_technician.php
│  ├─ corrective_maintenance.php
│  ├─ dashboard.php
│  ├─ delete_technician.php
│  ├─ downtime.php
│  ├─ edit_technician.php
│  ├─ equipment.php
│  ├─ get_report.php
│  ├─ maintenance_reports.php
│  ├─ mark_notification_read.php
│  ├─ preventive_maintenance.php
│  ├─ priority.php
│  ├─ reports.php
│  ├─ services.php
│  ├─ tech_tasks.php
│  └─ users.php
├─ includes/
│  ├─ auth_check.php
│  ├─ db.php
│  ├─ footer.php
│  └─ header.php
├─ technician/
│  ├─ dashboard.php
│  ├─ declare_panne.php
│  ├─ downtime.php
│  ├─ history.php
│  ├─ maintenance_report.php
│  ├─ maintenance.php
│  ├─ mark_notification_read.php
│  ├─ part_detail.php
│  ├─ spare_parts.php
│  ├─ update_order.php
│  ├─ work_order_detail.php
│  └─ work_orders.php
Prerequisites
Windows / macOS / Linux with PHP 7.4+ (or PHP 8.x)
MySQL / MariaDB
A local server environment (XAMPP, WAMP, MAMP) for development
Installation (quick)
Copy the project into your web server document root (e.g., C:/xampp/htdocs/):

Place project in htdocs/cmms so index pages are reachable.
Create the database and sample data:

Import config/db.sql using phpMyAdmin or CLI:
mysql -u root -p < config/db.sql
Configure DB connection:
Edit config/config.php and set MySQL host, username, password, and database name.
Open the app in your browser and log in:
Demo credentials (only for testing):
Engineer: engineer@hospital.ma / engineer123
Technician: technician@hospital.ma / technician123
If you use demo credentials, the login code will look up the matching employee record and populate $_SESSION['employee_id'] automatically.

How the Panne → Maintenance flow works
Technician declares a panne using technician/declare_panne.php. This inserts a row into Panne_Declarations and updates the Assets status to "En Maintenance".
Engineers see pending pannes in engineer/dashboard.php filtered to technicians they manage (Technicians.Managed_ByEngineer_ID).
When an engineer accepts a panne (engineer/accept_panne.php):
The Panne_Declarations row is updated (Status='Vue', Accepted_Date, Accepted_By_Engineer_ID).
A Maintenance record is created; the code assigns Assigned_Engineer_ID and also Assigned_Technician_ID (this ensures the technician sees it on their dashboard).
The Assets row is updated to Status_ID = 2 (En Maintenance).
If you still don't see assigned maintenance on the technician dashboard, check:

That the logged-in session has $_SESSION['employee_id'] populated (see auth/login.php).
The technician record exists and Technicians.Employee_ID matches that employee_id.
The Maintenance record has Assigned_Technician_ID set (check SELECT * FROM Maintenance ORDER BY Reported_Date DESC LIMIT 5).
Troubleshooting
Tablespace error when importing db.sql: use phpMyAdmin to DROP DATABASE first or run DROP DATABASE IF EXISTS cmms; at the top of the SQL, then import. The repo includes config/db.sql that already handles recreation in most setups.
If demo login doesn't show assigned tasks: ensure demo login creates $_SESSION['employee_id'] (the project code now retrieves it by name). If using real users, make sure Users.Employee_ID is set.
Removing temporary files
If you want to remove one-time setup/test files, consider deleting:

test_connection.php, test_suppliers.php, test.php, testdb.php, SETUP_PANNE.php, verify_setup.php
Key SQL tables (high level)
Employees, Technicians, Biomedical_Engineers
Assets, Asset_Types, Asset_Status, Criticality_Level
Panne_Declarations (panne reports)
Maintenance, Maintenance_Type, Maintenance_Priority, Maintenance_Task
Spare_Parts, Parts_Category, Task_Parts_Usage, Task_Technicians
Developer notes
Database helper functions used across the codebase: fetch_one, fetch_all, execute_query (see includes/db.php).
Session keys used: $_SESSION['user_id'], $_SESSION['employee_id'], $_SESSION['name'], $_SESSION['role'], $_SESSION['logged_in'].
Next steps / recommended improvements
Add proper user registration and password reset flows
Replace demo users with fully managed Users and email verification
Add unit tests for DB helpers and critical flows
Improve UI/UX and accessibility
If you'd like, I can additionally:

Create a CONTRIBUTING.md with development rules
Clean up suggested test files safely
Generate a smaller quick_start.md for non-dev users
File created: cmms/README.md
