# Module: Core

The `core` module is the **central administrative hub** and configuration layer for the Politeia Learning plugin. It manages the global settings that govern how the platform behaves.

## 🚀 Key Responsibilities

1.  **Admin Menu & Structure**: Registers the primary "Politeia Learning" menu in the WordPress sidebar, providing a unified location for all plugin management.
2.  **Global Settings**:
    - **Style Options**: Manages global layout constraints like the maximum width for the Course Creator and general containers.
    - **Profile Templates**: Controls which frontend template is used for user profiles (`politeia-profile`, etc.).
    - **Operation Template**: Defines the base slug for the dashboard (e.g., `/center` vs `/center-2`).
3.  **Module Visibility**: Handles the high-level toggle for which functionalities (Ventas, Estudiantes, Escritos, etc.) are enabled for both administrators and end-users.
4.  **Health Check**: Monitors the status of required integrations (WooCommerce, Learni/LMS) to ensure the platform is fully operational.
5.  **Unified Taxonomies**: Provides the management interface for categories and tags used across all learning objects.

## 🛠 Tech Stack & Integration

-   **Admin Engine**: Managed by the `PL_Core_Admin` class, which handles all `admin_menu` and `admin_init` hooks.
-   **Settings API**: Uses the standard WordPress Options API to store critical platform constants.
-   **Templating**: Admin views are kept separate in the `templates/` directory to maintain a clean MVC-style separation of concerns.

## ⚠️ Importance

This module is **ABSOLUTELY CRITICAL**. Deleting this folder would:
-   Remove the entire administrative interface from the WordPress sidebar.
-   Reset global layout widths and branding options.
-   **Break all dashboard links**: Since the `/center-2` slug is stored in this module's settings, removing it would cause the platform's primary navigation to fail.
