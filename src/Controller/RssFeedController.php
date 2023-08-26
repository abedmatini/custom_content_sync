<?php

namespace Drupal\custom_content_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;


class RssFeedController extends ControllerBase {

  public function importRssFeed() {
    // Fetch the RSS feed.
    $rss_url = 'https://www.gaultmillau.ch/rss_feed';
    $rss_content = file_get_contents($rss_url);
    $rss_feed = simplexml_load_string($rss_content);

    // Get all nodes of the article content type.
    $nodes = \Drupal::entityTypeManager()->getStorage('node')
      ->loadByProperties(['type' => 'article']);


    // Process each item in the RSS feed.
    foreach ($rss_feed->channel->item as $item) {
      $guid = (string) $item->guid; // Assuming <guid> contains the unique identifier.

      // Check if a node with the same GUID already exists.
      $existing_node = $this->getNodeByGuid($guid);

      if (!$existing_node) {
        $this->createNodeFieldsFromRss($item, $guid);
      }

      elseif (!$this->wasNodeManuallyUpdated($existing_node) && !$this->wasNodeChanged($existing_node)) {

        echo "Node exists, wasn't manually updated, and wasn't changed!!!" . "<br>";

          $this->updateNodeFieldsFromRss($existing_node,$item, $guid);
      }
      else {
        echo "(Node exists and either was manually updated or was changed)" . "<br>";
      }

    }

    return new Response('RSS feed imported and nodes checked successfully.');
  }

  private function getNodeByGuid($guid) {
    $query = \Drupal::entityQuery('node')
      ->condition('field_guid', $guid)
      ->execute();

    if (!empty($query)) {
      $nid = reset($query);
      return Node::load($nid);
    }

    return null;
  }
  private function wasNodeManuallyUpdated(Node $node) {
    // Compare the 'changed' timestamp with the 'created' timestamp.
    $createdTimestamp = $node->getCreatedTime();
    $changedTimestamp = $node->getChangedTime();

    return $createdTimestamp !== $changedTimestamp;
  }

  private function wasNodeChanged(Node $node) {
    $lastSavedTimestamp = $node->getChangedTime();
    $currentTimestamp = \Drupal::time()->getRequestTime();
    return $lastSavedTimestamp > $currentTimestamp;
  }

  private function createNodeFieldsFromRss ($item, $guid){
    // Node doesn't exist, create a new one.
    $title = (string) $item->title;
    $link = (string) $item->link;
    $description = (string) $item->description;

    $node = Node::create([
      'type' => 'article',
      'title' => $title,
      'body' => [
        'value' => $description,
        'format' => 'full_html',
      ],
      'field_guid' => $guid,
      'field_link' => $link,
    ]);
    $node->save();
    $this->createUrlAlias($node, $link);
  }
  private function updateNodeFieldsFromRss(Node $node,$item, $guid) {

    $title = (string) $item->title;
    $description = (string) $item->description;
    $guid = (string) $item->guid;

    $node->setTitle($title);
    $node->set('body', [
      'value' => $description,
      'format' => 'full_html',
    ]);

    $node->set('field_guid', $guid);

    $node->save();
  }

  private function createUrlAlias(Node $node, $originalLink) {
    // Dismiss the domain from the link.
    $path = parse_url($originalLink, PHP_URL_PATH);

    $alias = $path;

    // Load the alias storage service.
    $alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');

    // Create an alias for the node.
    $alias_storage->create([
      'path' => '/node/' . $node->id(),
      'alias' => $alias,
    ])->save();

    // Set the alias to the node.
    $node->path = ['alias' => $alias];
    $node->save();
  }





}
