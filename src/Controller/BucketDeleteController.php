<?php

namespace Drupal\bucket\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\RedirectResponse;

class BucketDeleteController extends ControllerBase {

  public function delete(File $file) {
    $account = $this->currentUser();
    $owner_id = (int) $file->getOwnerId();

    $can_any = $account->hasPermission('delete any bucket file');
    $can_own = $account->hasPermission('delete own bucket file') && (int) $account->id() === $owner_id;

    if (!$can_any && !$can_own) {
      throw $this->createAccessDeniedException();
    }

    $fid = (int) $file->id();
    $file->delete();
    \Drupal::database()->delete('bucket_item')->condition('fid', $fid)->execute();

    $this->messenger()->addStatus($this->t('File deleted.'));
    return new RedirectResponse('/bucket/my');
  }

}
