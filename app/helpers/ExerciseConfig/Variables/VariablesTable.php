<?php

namespace App\Helpers\ExerciseConfig;
use JsonSerializable;
use Symfony\Component\Yaml\Yaml;

/**
 * Represents variables table which is in fact key value map of variables.
 */
class VariablesTable implements JsonSerializable
{
  /**
   * @var array
   */
  protected $table = array();

  /**
   * True if internal table contains given variable key.
   * @param string $key
   * @return bool
   */
  public function contains(string $key): bool {
    return array_key_exists($key, $this->table);
  }

  /**
   * Returns variable with specified key, if there is none, return null.
   * @param string $key
   * @return Variable|null
   */
  public function get(string $key): ?Variable {
    if (array_key_exists($key, $this->table)) {
      return $this->table[$key];
    }

    return null;
  }

  /**
   * If table contains variable with the same key as the given one.
   * Original variable is replaced by the new one.
   * @param string $key
   * @param Variable $value
   * @return VariablesTable
   */
  public function set(string $key, Variable $value): VariablesTable {
    $this->table[$key] = $value;
    return $this;
  }

  /**
   * Remove variable with given key.
   * @param string $key
   * @return VariablesTable
   */
  public function remove(string $key): VariablesTable {
    unset($this->table[$key]);
    return $this;
  }

  /**
   * Return size of the table.
   * @return int
   */
  public function size(): int {
    return count($this->table);
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = [];
    foreach ($this->table as $key => $value) {
      $data[$key] = $value->toArray();
    }
    return $data;
  }

  /**
   * Serialize the config.
   * @return string
   */
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }

  /**
   * Enable automatic serialization to JSON
   * @return array
   */
  public function jsonSerialize() {
    return $this->toArray();
  }
}
