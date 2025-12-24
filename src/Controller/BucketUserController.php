<?php

namespace Drupal\bucket\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\file\Entity\File;

class BucketUserController extends ControllerBase {

  public function view() {
    $cfg = $this->config('bucket.settings');
    $limit = (int) ($cfg->get('list_page_limit') ?? 500);
    $uid = (int) $this->currentUser()->id();

    $build = [];

    // Upload button at top (requested).
    $build['upload_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Upload files'),
      '#url' => Url::fromRoute('bucket.upload'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    $db = \Drupal::database();
    $q = $db->select('file_managed', 'fm')
      ->fields('fm', ['fid', 'filename', 'uri', 'filesize', 'uid', 'created'])
      ->condition('uri', 'public://bucket/%', 'LIKE')
      ->condition('fm.uid', $uid)
      ->orderBy('created', 'DESC')
      ->range(0, $limit);
    $q->join('bucket_item', 'bi', 'bi.fid = fm.fid');
    $q->fields('bi', ['downloaded', 'downloaded_at']);
    $result = $q->execute();

    $header = [$this->t('File'), $this->t('Size'), $this->t('Age'), $this->t('Actions')];
    $date = \Drupal::service('date.formatter');
    $rows = [];

    foreach ($result as $r) {
      $file = File::load($r->fid);
      if (!$file) {
        continue;
      }

      $download_url = Url::fromRoute('bucket.download', ['file' => $r->fid]);

      $rows[] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => $r->filename,
            '#url' => $download_url,
          ],
        ],
        ByteSizeMarkup::create((int) $r->filesize),
        $date->formatInterval(\Drupal::time()->getRequestTime() - (int) $r->created),
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Delete'),
            '#url' => Url::fromRoute('bucket.delete', ['file' => $r->fid]),
          ],
        ],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No files yet.'),
      '#cache' => [
        'tags' => [
          'file_list',
          'user:' . $uid,
        ],
      ],
    ];

    return $build;
  }

}
