<?php
namespace App\Model\View;

use App\Helpers\PermissionHints;
use App\Model\Entity\LocalizedShadowAssignment;
use App\Model\Entity\ShadowAssignment;
use App\Security\ACL\IShadowAssignmentPermissions;

class ShadowAssignmentViewFactory {

  /** @var IShadowAssignmentPermissions */
  public $shadowAssignmentAcl;

  public function __construct(IShadowAssignmentPermissions $shadowAssignmentAcl) {
    $this->shadowAssignmentAcl = $shadowAssignmentAcl;
  }

  public function getAssignment(ShadowAssignment $assignment) {
    return [
      "id" => $assignment->getId(),
      "version" => $assignment->getVersion(),
      "isPublic" => $assignment->isPublic(),
      "createdAt" => $assignment->getCreatedAt()->getTimestamp(),
      "updatedAt" => $assignment->getUpdatedAt()->getTimestamp(),
      "localizedTexts" => $assignment->getLocalizedTexts()->map(function (LocalizedShadowAssignment $text) {
        return $text->jsonSerialize();
      })->getValues(),
      "groupId" => $assignment->getGroup()->getId(),
      "isBonus" => $assignment->isBonus(),
      "permissionHints" => PermissionHints::get($this->shadowAssignmentAcl, $assignment)
    ];
  }
}
