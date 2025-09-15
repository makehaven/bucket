<?php

namespace Drupal\bucket\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class BucketDownloadController extends ControllerBase {

  public function download(File $file) {
    $uri = $file->getFileUri();
    if (strpos($uri, 'public://bucket/') !== 0) {
      throw $this->createNotFoundException();
    }

    \Drupal::database()->update('bucket_item')
      ->fields([
        'downloaded' => 1,
        'downloaded_at' => \Drupal::time()->getRequestTime(),
      ])
      ->condition('fid', (int) $file->id())
      ->execute();

    $handle = @fopen($uri, 'rb');
    if (!$handle) {
      throw $this->createNotFoundException();
    }

    $response = new StreamedResponse(function () use ($handle) {
      while (!feof($handle)) {
        echo fread($handle, 8192);
      }
      fclose($handle);
    });

    $filename = $file->getFilename();
    $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    $response->headers->set('Content-Disposition', $disposition);
    $response->headers->set('Content-Type', $file->getMimeType() ?: 'application/octet-stream');
    $response->setPublic();

    return $response;
  }

}
