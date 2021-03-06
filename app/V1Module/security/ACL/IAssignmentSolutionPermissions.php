<?php
namespace App\Security\ACL;


use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;

interface IAssignmentSolutionPermissions {
  function canViewAll(): bool;
  function canViewDetail(AssignmentSolution $assignmentSolution): bool;
  function canDelete(AssignmentSolution $assignmentSolution): bool;
  function canSetBonusPoints(AssignmentSolution $assignmentSolution): bool;
  function canSetAccepted(AssignmentSolution $assignmentSolution): bool;
  function canViewResubmissions(AssignmentSolution $assignmentSolution): bool;

  function canViewEvaluation(AssignmentSolution $assignmentSolution): bool;
  function canViewEvaluationDetails(AssignmentSolution $assignmentSolution): bool;
  function canViewEvaluationValues(AssignmentSolution $assignmentSolution): bool;
  function canViewEvaluationJudgeOutput(AssignmentSolution $assignmentSolution): bool;
  function canDeleteEvaluation(AssignmentSolution $assignmentSolution): bool;
  function canDownloadResultArchive(AssignmentSolution $assignmentSolution): bool;
}
