# Politeia Course Group Plugin

## Purpose
The purpose of this plugin is to create all the custom functionalities for the Politeia website related to courses, grouping courses, selling courses, and creating courses.

## Architecture
The plugin follows a modular architecture. Main functionalities are encapsulated within the `modules/` directory. Each module is designed to be standalone, allowing them to be enabled or disabled without affecting the rest of the plugin.

### Modules
- **course-programs**: Manages the high-level "Philosophical Programs" that group LearnDash course groups.
