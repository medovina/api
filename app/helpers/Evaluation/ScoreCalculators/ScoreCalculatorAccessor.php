<?php
namespace App\Helpers\Evaluation;
use App\Exceptions\InvalidArgumentException;
use App\Helpers\Evaluation\IScoreCalculator;
use Nette;
use Nette\Utils\Arrays;

/**
 * Provides access to different implementations of score calculation
 */
class ScoreCalculatorAccessor
{
  use Nette\SmartObject;

  private $calculators;

  /**
   * ScoreCalculatorAccessor constructor.
   * @param array $calculators array where keys are identifiers of calculators and values are instances of {@link IScoreCalculator}
   * @throws InvalidArgumentException
   */
  public function __construct(array $calculators)
  {
    if (count($calculators) === 0) {
      throw new InvalidArgumentException("No score calculators provided");
    }

    $this->calculators = $calculators;
  }

  /**
   * @param null|string $name
   * @return IScoreCalculator
   */
  public function getCalculator(?string $name): IScoreCalculator
  {
    if (empty($name)) {
      return $this->getDefaultCalculator();
    }

    return Arrays::get($this->calculators, $name, $this->getDefaultCalculator());
  }

  public function getDefaultCalculator(): IScoreCalculator
  {
    return reset($this->calculators);
  }
}
