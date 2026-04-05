# Politeia Course Group Plugin

## Purpose
The purpose of this plugin is to create all the custom functionalities for the Politeia website related to courses, grouping courses, selling courses, and creating courses.

## Note (BuddyBoss)
We are no longer using **BuddyBoss Theme** nor **BuddyBoss Platform (Plugin)** as a dependency for the Politeia Learning experience. Any navigation, profile/center routing, and UI pieces that previously relied on BuddyBoss should be provided by our own plugins/modules (e.g. menu management).

## Architecture
The plugin follows a modular architecture. Main functionalities are encapsulated within the `modules/` directory. Each module is designed to be standalone, allowing them to be enabled or disabled without affecting the rest of the plugin.

### Modules
- **course-programs**: Manages the high-level "Philosophical Programs" that group LearnDash course groups.
