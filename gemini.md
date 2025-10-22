# Gemini Project Analysis: Bezoekverslag App

This document provides a comprehensive overview of the Bezoekverslag App, its architecture, file structure, and key components to provide context for future development and maintenance.

## 1. Project Overview

- **Purpose:** A web application for creating, managing, and exporting visit reports ("bezoekverslagen").
- **Technology:** Custom-built PHP application.
- **Database:** MySQL/MariaDB.

## 2. Core Architecture

The application follows a simple Model-View-Controller (MVC) like pattern.

- **Entry Point:** All web requests are routed through `public/index.php`.
- **Routing:** A `switch` statement in `public/index.php` handles routing based on the `?page=` GET parameter. This is a front-controller pattern.
- **Base Classes:** The `/core` directory contains base `Controller` and `Model` classes, though they are very lightweight.

## 3. File Structure Breakdown

```
/
├── app/                  # Core application logic (MVC)
│   ├── controllers/      # Handles business logic and user input
│   ├── models/           # Database table representations
│   ├── views/            # Presentation files (PHP templates)
│   │   └── layout/       # Header and footer templates
│   └── helpers/          # Helper functions (e.g., auth, logging)
│
├── config/               # Configuration files
│   ├── config.php        # Main application config
│   ├── database.php      # Database connection settings
│   └── schema.sql        # The full database schema
│
├── core/                 # Base MVC classes
│   ├── Controller.php    # Base controller
│   ├── Model.php         # Base model
│   └── Router.php        # (Seemingly unused) Router class
│
├── public/               # Web server document root
│   ├── index.php         # Application entry point and router
│   ├── css/              # Stylesheets
│   ├── js/               # JavaScript files
│   └── uploads/          # Directory for user-uploaded files
│
├── vendor/               # Composer dependencies (e.g., PHPMailer, DomPDF)
│
├── composer.json         # PHP package dependencies
├── gemini.md             # This analysis file
└── ...                   # Other root-level files
```

## 4. Main Data Models

- **`bezoekverslag`**: The central entity representing a single visit report.
- **`ruimte`**: Represents a space/room within a visit report. A `bezoekverslag` can have multiple `ruimtes`.
    - *Versioning*: This table uses a `schema_version` column to allow for different form structures between old and new entries.
- **`foto`**: A photo associated with a `ruimte`. A `ruimte` can have multiple `fotos`.
- **`users`**: Application users with different roles (admin, poweruser, etc.).
- **`client_access`**: Manages external client access to a specific `bezoekverslag`.

## 5. Routing

Routing is handled in `public/index.php` via a `switch` statement on the `$_GET['page']` parameter.

| `page` value                      | Controller@Method                          | Description                               |
| --------------------------------- | ------------------------------------------ | ----------------------------------------- |
| **Authentication**                |                                            |                                           |
| `login`                           | `AuthController@login`                     | Displays and handles user login.          |
| `logout`                          | `AuthController@logout`                    | Logs the user out.                        |
| `register`                        | `AuthController@register`                  | Displays and handles user registration.   |
| `forgot` / `reset`                | `AuthController@forgot`/`reset`            | Handles the password reset flow.          |
| `2fa_verify`                      | `AuthController@verify2FA`                 | Handles two-factor authentication.        |
| **Bezoekverslagen**               |                                            |                                           |
| `dashboard`                       | `BezoekverslagController@showDashboard`    | Main dashboard view, lists all reports.   |
| `nieuw`                           | `BezoekverslagController@nieuw`            | Displays the form to create a new report. |
| `bewerk`                          | `BezoekverslagController@bewerk`           | Displays the form to edit an existing report. |
| `delete_verslag`                  | `BezoekverslagController@delete`           | Deletes a report.                         |
| `submit`                          | `BezoekverslagController@generatePdf`      | Generates a PDF of the report.            |
| **Ruimtes**                       |                                            |                                           |
| `ruimte_new`                      | `RuimteController@create`                  | Displays the form for a new space.        |
| `ruimte_edit`                     | `RuimteController@edit`                    | Displays the form to edit a space.        |
| `ruimte_save`                     | `RuimteController@save`                    | Saves a new space.                        |
| `ruimte_delete`                   | `RuimteController@delete`                  | Deletes a space.                          |
| `foto_delete`                     | `RuimteController@deleteFoto`              | Deletes a photo from a space.             |
| **Admin**                         |                                            |                                           |
| `admin`                           | `AdminController@users`                    | User management view.                     |
| `admin_delete_user`               | `AdminController@deleteUser`               | Deletes a user.                           |
| `admin_reset_password`            | `AdminController@adminResetPassword`       | Resets a user's password.                 |
| `admin_impersonate`               | `AdminController@impersonateUser`          | Allows an admin to log in as another user.|
| `admin_check_updates`             | `UpdateController@check`                   | Checks for application updates.           |
| `admin_perform_update`            | `UpdateController@performUpdate`           | Performs an application update.           |
| **Client Portal**                 |                                            |                                           |
| `client_login`                    | `ClientController@login`                   | Login page for external clients.          |
| `client_view`                     | `ClientController@view`                    | View a report as an external client.      |
| **API**                           |                                            |                                           |
| `api_postcode_lookup`             | `ApiController@postcodeLookup`             | Internal API for postcode lookups.        |

---
*This document was last updated on 2025-10-22.*