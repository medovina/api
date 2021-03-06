<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Exceptions\InvalidArgumentException;

use Nette\Security\Passwords;
use Nette\Utils\Validators;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"auth_service", "external_id"})})
 *
 * @method string getExternalId()
 * @method string getAuthService()
 * @method User getUser()
 */
class ExternalLogin
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="string", length=32)
   */
  protected $authService;

  /**
   * @ORM\Column(type="string", length=128)
   */
  protected $externalId;

  /**
   * @ORM\ManyToOne(targetEntity="User", inversedBy="externalLogins")
   */
  protected $user;

  public function __construct(User $user, string $authService, string $externalId) {
    $this->user = $user;
    $this->authService = $authService;
    $this->externalId = $externalId;
  }

}
