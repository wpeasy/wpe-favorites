<?php
/**
 * GitHub plugin updater.
 *
 * Wires the Plugin Update Checker library to the public GitHub repository
 * so WordPress can detect and install updates pushed as GitHub Releases.
 *
 * @package WPE\Favorites
 */

declare(strict_types=1);

namespace WPE\Favorites\Updater;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined('ABSPATH') || exit;

final class GitHubUpdater {

    private const REPO_URL = 'https://github.com/wpeasy/wpe-favorites/';
    private const SLUG     = 'wpe-favorites';

    public static function init(): void {
        if (!class_exists(PucFactory::class)) {
            return;
        }

        $updater = PucFactory::buildUpdateChecker(
            self::REPO_URL,
            WPEF_PLUGIN_FILE,
            self::SLUG
        );

        $updater->getVcsApi()->enableReleaseAssets();
    }
}
