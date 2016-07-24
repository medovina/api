<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Submissions;
use App\Exception\NotFoundException;

class SubmissionsPresenter extends BasePresenter {

  /** @var Submissions */
  private $submissions;

  /**
   * @param Submissions $submissions  Submissions repository
   */
  public function __construct(Submissions $submissions) {
    $this->submissions = $submissions;
  }

  protected function findSubmissionOrThrow(string $id) {
    $submission = $this->submissions->get($id);
    if (!$submission) {
      throw new NotFoundException;
    }

    return $submission;
  }

  /**
   * @GET
   */
  public function actionDefault() {
    $submissions = $this->submissions->findAll();
    $this->sendJson($submissions);
  }

  /**
   * @GET
   */
  public function actionDetail(string $id) {
    $submission = $this->findSubmissionOrThrow($id);
    $this->sendJson($submission);
  }

  // @todo: evaluation

}
