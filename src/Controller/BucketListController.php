<?php

namespace Drupal\bucket\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;

class BucketListController extends ControllerBase {

  public function view() {
    $cfg = $this->config('bucket.settings');
    $limit = (int) ($cfg->get('list_page_limit') ?? 500);
    $account = $this->currentUser();

    $build = [];

    // Description block with token replacement.
    $desc = (string) $cfg->get('description');
    $desc = strtr($desc, [
      '[ttl_hours]' => (string) ($cfg->get('ttl_hours') ?? 48),
      '[delete_on_download]' => ($cfg->get('delete_on_download') ? 'Immediate' : 'No'),
    ]);
    $build['bucket_description'] = [
      '#type' => 'markup',
      '#markup' => '<div class="bucket-desc">' . nl2br($desc) . '</div>',
    ];

    // Page links.
    $links = [];
    $links['upload'] = [
      '#type' => 'link',
      '#title' => $this->t('Upload files'),
      '#url' => Url::fromRoute('bucket.upload'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];
    if ($account->isAuthenticated()) {
      $links['my'] = [
        '#type' => 'link',
        '#title' => $this->t('My files'),
        '#url' => Url::fromRoute('bucket.my'),
        '#attributes' => ['class' => ['button']],
      ];
    }
    $build['links'] = [
      '#theme' => 'item_list',
      '#items' => $links,
      '#attributes' => ['class' => ['bucket-links']],
    ];

    $rows = [];
    $header = [
      $this->t('File'),
      $this->t('Owner'),
      $this->t('Size'),
      $this->t('Age'),
      $this->t('Actions'),
    ];

    $db = \Drupal::database();
    $q = $db->select('file_managed', 'fm')
      ->fields('fm', ['fid', 'filename', 'uri', 'filesize', 'uid', 'created'])
      ->condition('uri', 'public://bucket/%', 'LIKE')
      ->orderBy('created', 'DESC')
      ->range(0, $limit);
    $q->join('bucket_item', 'bi', 'bi.fid = fm.fid');
    $q->fields('bi', ['downloaded', 'downloaded_at']);
    $result = $q->execute();

    $date = \Drupal::service('date.formatter');

    foreach ($result as $r) {
      $file = File::load($r->fid);
      if (!$file) {
        continue;
      }
      $download_url = Url::fromRoute('bucket.download', ['file' => $r->fid]);

      $owner = $r->uid ? \Drupal::entityTypeManager()->getStorage('user')->load($r->uid) : NULL;
      $owner_name = $owner ? $owner->getDisplayName() : $this->t('Unknown');

      $actions = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Download'),
            '#url' => $download_url,
          ],
        ],
      ];

      $can_delete_any = $account->hasPermission('delete any bucket file');
      $is_owner = (int) $account->id() === (int) $r->uid;
      $can_delete_own = $account->hasPermission('delete own bucket file');

      if ($can_delete_any || ($is_owner && $can_delete_own)) {
        $actions[] = [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Delete'),
            '#url' => Url::fromRoute('bucket.delete', ['file' => $r->fid]),
          ],
        ];
      }

      $rows[] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => $r->filename,
            '#url' => $download_url,
          ],
        ],
        $owner_name,
        ByteSizeMarkup::create((int) $r->filesize),
        $date->formatInterval(\Drupal::time()->getRequestTime() - (int) $r->created),
        ['data' => ['#theme' => 'item_list', '#items' => $actions, '#attributes' => ['class' => ['bucket-actions']]]],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No files yet.'),
    ];

    // Add no-index to the page.
    $build['#attached']['html_head'][] = [
      [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'robots',
          'content' => 'noindex, nofollow',
        ],
      ],
      'bucket_no_index',
    ];

    // Disable caching on this page.
    $build['#cache']['max-age'] = 0;

    return $build;
  }

}
