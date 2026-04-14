<?php
/**
 * Unified taxonomy registration for Politeia Learning.
 */
if (!defined('ABSPATH')) {
    exit;
}

class PL_Taxonomy
{
    public const CATEGORY_TAXONOMY = 'pl_learning_category';
    public const TAG_TAXONOMY = 'pl_learning_tag';
    private const SEEDED_OPTION = 'pl_learning_taxonomy_seed_v1';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register_taxonomies'], 20);
        add_action('init', [__CLASS__, 'maybe_seed_default_categories'], 30);
        add_action('pl_seed_default_categories', [__CLASS__, 'maybe_seed_default_categories']);
    }

    public static function register_taxonomies(): void
    {
        // Keep LearnDash types for legacy screens, but also support internal Learni courses.
        $object_types = ['sfwd-courses', 'learni_course', 'groups', 'course_program'];

        // Categories (hierarchical).
        if (!taxonomy_exists(self::CATEGORY_TAXONOMY)) {
            register_taxonomy(self::CATEGORY_TAXONOMY, $object_types, [
                'hierarchical' => true,
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => false,
                'show_in_rest' => true,
                'show_admin_column' => false,
                'query_var' => false,
                'rewrite' => false,
                'labels' => [
                    'name' => __('Categorías de aprendizaje', 'politeia-learning'),
                    'singular_name' => __('Categoría de aprendizaje', 'politeia-learning'),
                    'search_items' => __('Buscar categorías', 'politeia-learning'),
                    'all_items' => __('Todas las categorías', 'politeia-learning'),
                    'parent_item' => __('Categoría padre', 'politeia-learning'),
                    'parent_item_colon' => __('Categoría padre:', 'politeia-learning'),
                    'edit_item' => __('Editar categoría', 'politeia-learning'),
                    'update_item' => __('Actualizar categoría', 'politeia-learning'),
                    'add_new_item' => __('Agregar nueva categoría', 'politeia-learning'),
                    'new_item_name' => __('Nombre de la nueva categoría', 'politeia-learning'),
                    'menu_name' => __('Categorías', 'politeia-learning'),
                ],
                'capabilities' => [
                    'manage_terms' => 'manage_options',
                    'edit_terms' => 'manage_options',
                    'delete_terms' => 'manage_options',
                    'assign_terms' => 'edit_posts',
                ],
            ]);
        } else {
            foreach ($object_types as $t) {
                register_taxonomy_for_object_type(self::CATEGORY_TAXONOMY, $t);
            }
        }

        // Tags (non-hierarchical).
        if (!taxonomy_exists(self::TAG_TAXONOMY)) {
            register_taxonomy(self::TAG_TAXONOMY, $object_types, [
                'hierarchical' => false,
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => false,
                'show_in_rest' => true,
                'show_admin_column' => false,
                'query_var' => false,
                'rewrite' => false,
                'labels' => [
                    'name' => __('Etiquetas de aprendizaje', 'politeia-learning'),
                    'singular_name' => __('Etiqueta de aprendizaje', 'politeia-learning'),
                    'search_items' => __('Buscar etiquetas', 'politeia-learning'),
                    'popular_items' => __('Etiquetas populares', 'politeia-learning'),
                    'all_items' => __('Todas las etiquetas', 'politeia-learning'),
                    'edit_item' => __('Editar etiqueta', 'politeia-learning'),
                    'update_item' => __('Actualizar etiqueta', 'politeia-learning'),
                    'add_new_item' => __('Agregar nueva etiqueta', 'politeia-learning'),
                    'new_item_name' => __('Nombre de la nueva etiqueta', 'politeia-learning'),
                    'separate_items_with_commas' => __('Separa las etiquetas con comas', 'politeia-learning'),
                    'add_or_remove_items' => __('Agregar o quitar etiquetas', 'politeia-learning'),
                    'choose_from_most_used' => __('Elige entre las más usadas', 'politeia-learning'),
                    'menu_name' => __('Etiquetas', 'politeia-learning'),
                ],
                'capabilities' => [
                    'manage_terms' => 'manage_options',
                    'edit_terms' => 'manage_options',
                    'delete_terms' => 'manage_options',
                    'assign_terms' => 'edit_posts',
                ],
            ]);
        } else {
            foreach ($object_types as $t) {
                register_taxonomy_for_object_type(self::TAG_TAXONOMY, $t);
            }
        }
    }

    /**
     * Seed default learning categories (idempotent).
     */
    public static function maybe_seed_default_categories(): void
    {
        // Step 6: Allow running in background (Cron) or in Admin.
        if (!is_admin() && !doing_action('pl_seed_default_categories')) {
            return;
        }

        if (!taxonomy_exists(self::CATEGORY_TAXONOMY)) {
            return;
        }

        if (get_option(self::SEEDED_OPTION) === '1') {
            return;
        }

        $taxonomy = self::CATEGORY_TAXONOMY;

        $tree = [
            [
                'name' => 'Humanidades (El Pensamiento y la Cultura)',
                'slug' => 'humanidades',
                'children' => [
                    [
                        'name' => 'Filosofía',
                        'slug' => 'filosofia',
                        'children' => [
                            ['name' => 'Filosofía Política', 'slug' => 'filosofia-politica'],
                            ['name' => 'Ética y Moral', 'slug' => 'etica-y-moral'],
                            ['name' => 'Metafísica y Epistemología', 'slug' => 'metafisica-y-epistemologia'],
                        ],
                    ],
                    [
                        'name' => 'Historia',
                        'slug' => 'historia',
                        'children' => [
                            ['name' => 'Historia Universal', 'slug' => 'historia-universal'],
                            ['name' => 'Historia de las Ideas', 'slug' => 'historia-de-las-ideas'],
                            ['name' => 'Historia de Chile y Latinoamérica', 'slug' => 'historia-de-chile-y-latinoamerica'],
                        ],
                    ],
                    [
                        'name' => 'Literatura y Arte',
                        'slug' => 'literatura-y-arte',
                        'children' => [
                            ['name' => 'Grandes Libros y Literatura Clásica', 'slug' => 'grandes-libros-y-literatura-clasica'],
                            ['name' => 'Apreciación Cinematográfica', 'slug' => 'apreciacion-cinematografica'],
                            ['name' => 'Historia del Arte', 'slug' => 'historia-del-arte'],
                        ],
                    ],
                    [
                        'name' => 'Religión y Teología',
                        'slug' => 'religion-y-teologia',
                        'children' => [
                            ['name' => 'Religiones Comparadas', 'slug' => 'religiones-comparadas'],
                            ['name' => 'Pensamiento Cristiano / Teología Sistemática', 'slug' => 'pensamiento-cristiano-teologia-sistematica'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Ciencias y Pensamiento Formal (La Verdad y la Naturaleza)',
                'slug' => 'ciencias-y-pensamiento-formal',
                'children' => [
                    [
                        'name' => 'Ciencias Sociales',
                        'slug' => 'ciencias-sociales',
                        'children' => [
                            ['name' => 'Ciencia Política y Actualidad', 'slug' => 'ciencia-politica-y-actualidad'],
                            ['name' => 'Economía', 'slug' => 'economia'],
                            ['name' => 'Sociología y Antropología', 'slug' => 'sociologia-y-antropologia'],
                        ],
                    ],
                    [
                        'name' => 'Ciencias Exactas y Naturales',
                        'slug' => 'ciencias-exactas-y-naturales',
                        'children' => [
                            ['name' => 'Física y Cosmología', 'slug' => 'fisica-y-cosmologia'],
                            ['name' => 'Biología y Evolución', 'slug' => 'biologia-y-evolucion'],
                            ['name' => 'Matemáticas Aplicadas', 'slug' => 'matematicas-aplicadas'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Saberes Prácticos (Habilidades y Oficios)',
                'slug' => 'saberes-practicos',
                'children' => [
                    [
                        'name' => 'Tecnología y Digital',
                        'slug' => 'tecnologia-y-digital',
                        'children' => [
                            ['name' => 'Programación y Desarrollo Web', 'slug' => 'programacion-y-desarrollo-web'],
                            ['name' => 'Inteligencia Artificial', 'slug' => 'inteligencia-artificial'],
                        ],
                    ],
                    [
                        'name' => 'Gestión y Finanzas',
                        'slug' => 'gestion-y-finanzas',
                        'children' => [
                            ['name' => 'Finanzas Personales e Inversiones', 'slug' => 'finanzas-personales-e-inversiones'],
                            ['name' => 'Emprendimiento y Gestión de Proyectos', 'slug' => 'emprendimiento-y-gestion-de-proyectos'],
                        ],
                    ],
                    [
                        'name' => 'Escritura y Comunicación',
                        'slug' => 'escritura-y-comunicacion',
                        'children' => [
                            ['name' => 'Escritura Creativa y Ensayo', 'slug' => 'escritura-creativa-y-ensayo'],
                            ['name' => 'Retórica y Oratoria', 'slug' => 'retorica-y-oratoria'],
                        ],
                    ],
                ],
            ],
        ];

        $ensure_term = static function (array $node, int $parent_id = 0) use ($taxonomy, &$ensure_term): int {
            $slug = (string) ($node['slug'] ?? '');
            $name = (string) ($node['name'] ?? '');
            if ($slug === '' || $name === '') {
                return 0;
            }

            $term_id = 0;
            $existing = get_term_by('slug', $slug, $taxonomy);
            if ($existing && !is_wp_error($existing)) {
                $term_id = (int) $existing->term_id;
            }

            if ($term_id <= 0) {
                $inserted = wp_insert_term($name, $taxonomy, [
                    'slug' => $slug,
                    'parent' => $parent_id,
                ]);
                if (is_wp_error($inserted) || empty($inserted['term_id'])) {
                    return 0;
                }
                $term_id = (int) $inserted['term_id'];
            } else {
                wp_update_term($term_id, $taxonomy, [
                    'name' => $name,
                    'parent' => $parent_id,
                ]);
            }

            foreach ((array) ($node['children'] ?? []) as $child) {
                $ensure_term((array) $child, $term_id);
            }

            return $term_id;
        };

        foreach ($tree as $node) {
            $ensure_term((array) $node, 0);
        }

        update_option(self::SEEDED_OPTION, '1', false);
    }
}
