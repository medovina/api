<?php

namespace App\Security\ACL;

use App\Model\Entity\Exercise;
use App\Model\Entity\Group;

interface IExercisePermissions {
  function canViewAll(): bool;
  function canViewAllAuthors(): bool;
  function canViewList(): bool;

  function canViewDetail(Exercise $exercise): bool;
  function canUpdate(Exercise $exercise): bool;
  function canCreate(): bool;
  function canRemove(Exercise $exercise): bool;
  function canFork(Exercise $exercise): bool;

  function canViewLimits(Exercise $exercise): bool;
  function canSetLimits(Exercise $exercise): bool;

  function canViewScoreConfig(Exercise $exercise): bool;
  function canSetScoreConfig(Exercise $exercise): bool;

  function canAddReferenceSolution(Exercise $exercise): bool;

  function canAttachPipeline(Exercise $exercise): bool;
  function canDetachPipeline(Exercise $exercise): bool;
  function canViewPipelines(Exercise $exercise): bool;

  function canViewAssignments(Exercise $exercise): bool;
  function canAttachGroup(Exercise $exercise, Group $group): bool;
  function canDetachGroup(Exercise $exercise, Group $group): bool;

  function canViewAllTags(): bool;
  function canAddTag(Exercise $exercise): bool;
  function canRemoveTag(Exercise $exercise): bool;
}
