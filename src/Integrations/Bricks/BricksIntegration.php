<?php
/**
 * Bricks Builder integration.
 *
 * Registers the Favorite Button as a custom Bricks element.
 *
 * @package WPE\Favorites
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPE\Favorites\Integrations\Bricks;

defined('ABSPATH') || exit;

final class BricksIntegration {

    /**
     * Initialize Bricks integration.
     *
     * Only loads when Bricks Builder is active.
     */
    public static function init(): void {
        if (!defined('BRICKS_VERSION')) {
            return;
        }

        add_filter('bricks/builder/i18n', [self::class, 'register_category']);
        add_action('init', [self::class, 'register_elements'], 11);

        Query_Favorites::init();
        Query_Post_Types::init();
        DynamicData::init();
    }

    /**
     * Register the "Favorites" element category label.
     *
     * @param array<string, string> $i18n Bricks i18n strings.
     * @return array<string, string>
     */
    public static function register_category(array $i18n): array {
        $i18n['favorites'] = esc_html__('Favorites', 'wpef');
        return $i18n;
    }

    /**
     * Register custom Bricks elements.
     */
    public static function register_elements(): void {
        if (!class_exists('\Bricks\Elements')) {
            return;
        }

        $element_files = [
            WPEF_PLUGIN_DIR . 'src/Integrations/Bricks/Element_Favorite_Button.php',
            WPEF_PLUGIN_DIR . 'src/Integrations/Bricks/Element_User_Count.php',
            WPEF_PLUGIN_DIR . 'src/Integrations/Bricks/Element_Post_Count.php',
            WPEF_PLUGIN_DIR . 'src/Integrations/Bricks/Element_Global_Count.php',
            WPEF_PLUGIN_DIR . 'src/Integrations/Bricks/Element_Clear_Button.php',
        ];

        foreach ($element_files as $file) {
            \Bricks\Elements::register_element($file);
        }
    }
}
