# Module: Blog Post (Escritos)

This module manages the custom frontend presentation of standard WordPress posts, which are referred to within the Politeia ecosystem as **"Escritos"**.

## 🚀 Key Responsibilities

1.  **Custom Single Template**: Overrides the default theme's `single.php` with a specialized `templates/single-post.php` layout optimized for long-form reading.
2.  **Premium Typography**: Enforces high-quality academic typography using **EB Garamond** (for body text) and **Inter** (for UI/Headings) to ensure a scholarly reading experience.
3.  **Visual Consistency**: Applies the `pl-bp-blog-post-body` class and custom CSS to ensure that blog content matches the refined aesthetic of the rest of the learning platform.

## 🛠 Tech Stack & Integration

-   **Frontend Injection**: Uses the `template_include` filter to surgicaly replace the post template without affecting other post types.
-   **LMS Connection**: While technically standard WP posts, these "Escritos" are the primary source of reading material for **Learni** lessons.
-   **Assets**: Uses specialized CSS in `assets/css/blog-post.css` to handle margins, line-heights, and responsive reading widths.

## ⚠️ Importance

This module is **IMPORTANT** for the content integrity of the platform. Since "Escritos" are a core part of the curriculum, deleting this folder would cause all scholarly articles to lose their professional formatting, specialized fonts, and custom layout, making them much harder for students to read and less visually "premium."
