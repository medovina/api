<?php

namespace App\Model\Repository;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Query;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Exercise;
use App\Model\Entity\LocalizedExercise;
use App\Model\Entity\Group;
use App\Model\Entity\User;
use App\Helpers\Pagination;
use App\Model\Helpers\PaginationDbHelper;
use App\Exceptions\InvalidArgumentException;

/**
 * @method Exercise findOrThrow($id)
 */
class Exercises extends BaseSoftDeleteRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Exercise::class);
  }

  /**
   * Replace all runtime configurations in exercise with given ones.
   * @param Exercise $exercise
   * @param array $configs configurations which will be placed to exercise
   * @param bool $flush if true then all changes will be flush at the end
   */
  public function replaceEnvironmentConfigs(Exercise $exercise, array $configs, bool $flush = true) {
    $originalConfigs = $exercise->getExerciseEnvironmentConfigs()->toArray();
    foreach ($configs as $config) {
      $exercise->addExerciseEnvironmentConfig($config);
    }
    foreach ($originalConfigs as $config) {
      $exercise->removeExerciseEnvironmentConfig($config);
    }
    if ($flush) {
      $this->flush();
    }
  }

  /**
   * Search exercises names based on given string.
   * @param string|null $search
   * @return Exercise[]
   */
  public function searchByName(?string $search): array {
    if ($search === null || empty($search)) {
      return $this->findAll();
    }

    return $this->searchHelper($search, function ($search) {
      $qb = $this->createQueryBuilder("e");
      $sub = $this->em->createQueryBuilder()->select("le")->from(LocalizedExercise::class, "le");
      $sub->andWhere($sub->expr()->isMemberOf("le", "e.localizedTexts"))
        ->andWhere($qb->expr()->like("le.name", $qb->expr()->literal('%' . $search . '%')));
      $qb->andWhere($qb->expr()->exists($sub->getDQL()));
      return $qb->getQuery()->getResult();
    });
  }


  /**
   * Get a list of exercises filtered and ordered for pagination.
   * The exercises must be paginated manually, since they are tested by ACLs.
   * @param Pagination $pagination Pagination configuration object.
   * @param Groups Groups entity manager.
   * @return Exercise[]
   */
  public function getPreparedForPagination(Pagination $pagination, Groups $groups)
  {
    // Welcome to Doctrine HELL! Put your sickbags on standby!

    $qb = $this->createQueryBuilder('e'); // takes care of softdelete cases

    // Filter by instance Id (through group membership) ...
    if ($pagination->hasFilter("instanceId")) {
      $instanceId = trim($pagination->getFilter("instanceId"));

      $sub = $groups->createQueryBuilder("g");
      $sub->andWhere($qb->expr()->eq("g.instance", $qb->expr()->literal($instanceId)))
        ->andWhere($sub->expr()->isMemberOf("g", "e.groups"));

      $qb->andWhere($qb->expr()->exists($sub->getDQL()));
    }

    // Only exercises of given authors ...
    if ($pagination->hasFilter("authorsIds")) {
      $authorIds = $pagination->getFilter("authorsIds");
      if (!is_array($authorIds)) {
        $authorIds = [ $authorIds ];
      }
      $qb->andWhere($qb->expr()->in("e.author", $authorIds));
    }

    // Only exercises in explicitly given groups (or their ascendants) ...
    if ($pagination->hasFilter("groupsIds")) {
      $groupsIds = $pagination->getFilter("groupsIds");
      if (!is_array($groupsIds)) {
        $groupsIds = [ $groupsIds ];
      }

      // Each group has a separate OR clause ...
      $orExpr = $qb->expr()->orX();
      $gcounter = 0;
      foreach ($groups->groupsIdsAncestralClosure($groupsIds) as $id) {
        $var = "group" . ++$gcounter;
        $orExpr->add($qb->expr()->isMemberOf(":$var", "e.groups"));
        $qb->setParameter($var, $id);
      }
      $qb->andWhere($orExpr);
    }

    // Only exercises with given tags
    if ($pagination->hasFilter("tags")) {
      $tagNames = $pagination->getFilter("tags");
      if (!is_array($tagNames)) {
        $tagNames = [ $tagNames ];
      }

      $qb->join("e.exerciseTags", "et")
        ->andWhere($qb->expr()->in("et.name", $tagNames));
    }

    if ($pagination->getOrderBy() === "name") {
      // Special patch, we need to load localized names from another entity ...
      // Note: This requires custom COALESCE_SUB, which is actually normal COALESCE function that allows subqueries inside in DQL
      $qb->addSelect('COALESCE_SUB(
          (SELECT le1.name FROM App\Model\Entity\LocalizedExercise AS le1 WHERE le1 MEMBER OF e.localizedTexts AND le1.locale = :locale),
          (SELECT le2.name FROM App\Model\Entity\LocalizedExercise AS le2 WHERE le2 MEMBER OF e.localizedTexts AND le2.locale = \'en\'),
          (SELECT MAX(le3.name) FROM App\Model\Entity\LocalizedExercise AS le3 WHERE le3 MEMBER OF e.localizedTexts)
        ) AS HIDDEN localizedName');
      $qb->setParameter('locale', $pagination->getLocale() ?? 'en');
    }

    // Apply common pagination stuff (search and ordering) and yield the results ...
    $paginationDbHelper = new PaginationDbHelper(
      [ // known order by columns
        'name' =>      [ 'localizedName' ], // HIDDEN column created by special patch
        'createdAt' => [ 'e.createdAt' ],
      ],
      [ 'name' ], // search column names
      LocalizedExercise::class // search is performed on external localized texts
    );
    $paginationDbHelper->apply($qb, $pagination);
    return $paginationDbHelper->getResult($qb, $pagination);
  }

  /**
   * Get distinct authors of all exercises.
   * @param string $instanceId ID of an instance from which the authors are selected.
   * @param string|null $groupId A group which restricts the exercies.
   *                             Only exercises attached to that group (or any ancestral group) are considered.
   * @return User[] List of exercises authors.
   */
  public function getAuthors(string $instanceId = null, string $groupId = null, Groups $groups)
  {
    $qb = $this->em->createQueryBuilder()->select("a")->from(User::class, "a");
    if ($instanceId) {
      $qb->andWhere(":instance MEMBER OF a.instances")->setParameter("instance", $instanceId);
    }

    $sub = $this->createQueryBuilder("e"); // takes care of softdelete cases
    $sub->andWhere("a = e.author");

    if ($groupId) {
      // Each group of the ancestral closure has a separate OR clause ...
      $orExpr = $sub->expr()->orX();
      $gcounter = 0;
      foreach ($groups->groupsIdsAncestralClosure([$groupId]) as $id) {
        $var = "group" . ++$gcounter;
        $orExpr->add($sub->expr()->isMemberOf(":$var", "e.groups"));
        $qb->setParameter($var, $id);
      }
      $sub->andWhere($orExpr);
    }

    $qb->andWhere($qb->expr()->exists($sub->getDQL()));
    return $qb->getQuery()->getResult();
  }
}
