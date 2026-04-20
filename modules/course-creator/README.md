# Module: Course Creator (Center-2)

This is the **core management interface** for the Politeia Learning plugin. It provides the front-end dashboard where instructors and staff manage the entire educational ecosystem.

## 🚀 Key Responsibilities

1.  **Center-2 Dashboard**: Powering the `members/{user}/center-2/` URL, providing a premium, unified interface for all creator activities.
2.  **Course Management**: Handles the creation, editing, and publishing of `learni_course` objects.
3.  **Specializations & Programs**: Manages the grouping of courses into Specializations (`learni_specialization`) and larger Programs (`learni_program`).
4.  **Ventas (Sales)**: Integrates with WooCommerce to display real-time sales metrics, revenue reports, and payment status for instructors.
5.  **Estudiantes (Students)**: Provides tracking for student enrollments, progress, and performance across all managed courses.
6.  **Inclusion Approvals**: Implements a snapshot-based approval system used when adding instructors/partners to courses or programs.

## 🛠 Tech Stack & Integration

-   **LMS Engine**: Fully integrated with the internal **Learni** module. It has been 100% migrated away from LearnDash.
-   **Database**: Uses custom tables like `learni_course_items` and system meta for high-performance dashboard rendering.
-   **UI / Assets**: Located in `assets/css/` and `assets/js/`. These styles define the "Golden" professional look of the Politeia Dashboard.

## ⚠️ Importance

This module is **VITAL**. Deleting this folder would remove the primary user interface for instructors, making it impossible to create courses or view sales data on the frontend. It should be maintained as the primary entry point for all "Creator" and "Administrator" frontend functionality.
