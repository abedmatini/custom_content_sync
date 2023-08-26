<?php
namespace Drupal\custom_content_sync\Commands;

use Drush\Commands\DrushCommands;


/**
 * Drush command file.
 */
class SyncModuleCommands extends DrushCommands {

  /**
   * This command pull and sync rss data
   *
   * @command sync_rss:sync
   *
   * @aliases srss
   *
   * @usage your_module:meaning
   *
   */
  public function custom_content_sync_import() {
    // Run the import logic from RssFeedController.
    $controller = \Drupal::getContainer()->get('custom_content_sync.rss_feed_controller');
    $controller->importRssFeed();
  }
}
