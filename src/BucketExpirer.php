<?php

namespace Drupal\bucket;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;

class BucketExpirer {

  public function __construct(
    protected EntityTypeManagerInterface $etm,
    protected ConfigFactoryInterface $configFactory,
    protected FileSystemInterface $fs,
    protected LoggerInterface $logger,
    protected Connection $db,
    protected TimeInterface $time,
  ) {}

  public function expire(): int {
    $cfg = $this->configFactory->get('bucket.settings');
    $ttl_hours = (int) ($cfg->get('ttl_hours') ?? 48);
    $delete_on_download = (bool) $cfg->get('delete_on_download');
    $now = $this->time->getRequestTime();
    $cutoff = $now - ($ttl_hours * 3600);

    $query = $this->db->select('file_managed', 'fm')
      ->fields('fm', ['fid', 'uri', 'created'])
      ->condition('uri', 'public://bucket/%', 'LIKE');
    $query->join('bucket_item', 'bi', 'bi.fid = fm.fid');
    $query->fields('bi', ['downloaded', 'downloaded_at']);

    $or = $query->orConditionGroup()
      ->condition('fm.created', $cutoff, '<');
    if ($delete_on_download) {
      $or->condition('bi.downloaded', 1, '=');
    }
    $query->condition($or);

    $result = $query->execute();
    $fids = [];
    foreach ($result as $r) {
      $fids[] = (int) $r->fid;
    }
    if (!$fids) {
      return 0;
    }

    $storage = $this->etm->getStorage('file');
    $files = $storage->loadMultiple($fids);
    $count = 0;
    foreach ($files as $file) {
      $storage->delete([$file]);
      $this->db->delete('bucket_item')->condition('fid', (int) $file->id())->execute();
      $count++;
    }

    if ($count) {
      $this->logger->notice('Expired @count bucket file(s).', ['@count' => $count]);
    }
    return $count;
  }

}
