<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\LinuxSandbox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskType;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Box which represents execution of custom compiled program in ELF format.
 */
class ElfExecutionBox extends Box
{
  /** Type key */
  public static $ELF_EXEC_TYPE = "elf-exec";
  public static $BINARY_FILE_PORT_KEY = "binary-file";
  public static $INPUT_FILE_PORT_KEY = "input-file";
  public static $STDIN_FILE_PORT_KEY = "stdin";
  public static $OUTPUT_FILE_PORT_KEY = "output-file";
  public static $STDOUT_FILE_PORT_KEY = "stdin";
  public static $DEFAULT_NAME = "ELF Execution";

  private static $initialized = false;
  private static $defaultInputPorts;
  private static $defaultOutputPorts;

  /**
   * Static initializer.
   */
  public static function init() {
    if (!self::$initialized) {
      self::$initialized = true;
      self::$defaultInputPorts = array(
        //new Port((new PortMeta)->setName(self::$STDIN_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)->setVariable("")),
        new Port((new PortMeta)->setName(self::$INPUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)->setVariable("")),
        new Port((new PortMeta)->setName(self::$BINARY_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)->setVariable(""))
      );
      self::$defaultOutputPorts = array(
        //new Port((new PortMeta)->setName(self::$STDOUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)->setVariable("")),
        new Port((new PortMeta)->setName(self::$OUTPUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)->setVariable(""))
      );
    }
  }

  /**
   * ElfExecutionBox constructor.
   * @param BoxMeta $meta
   */
  public function __construct(BoxMeta $meta) {
    parent::__construct($meta);
  }


  /**
   * Get type of this box.
   * @return string
   */
  public function getType(): string {
    return self::$ELF_EXEC_TYPE;
  }

  /**
   * Get default input ports for this box.
   * @return array
   */
  public function getDefaultInputPorts(): array {
    self::init();
    return self::$defaultInputPorts;
  }

  /**
   * Get default output ports for this box.
   * @return array
   */
  public function getDefaultOutputPorts(): array {
    self::init();
    return self::$defaultOutputPorts;
  }

  /**
   * Get default name of this box.
   * @return string
   */
  public function getDefaultName(): string {
    return self::$DEFAULT_NAME;
  }

  /**
   * Compile box into set of low-level tasks.
   * @return Task[]
   */
  public function compile(): array {
    $task = new Task();
    $task->setType(TaskType::$EXECUTION);
    $task->setCommandBinary(ConfigParams::$EVAL_DIR .
      $this->getInputPort(self::$BINARY_FILE_PORT_KEY)->getVariableValue()->getPrefixedValue());
    $task->setSandboxConfig((new SandboxConfig)->setName(LinuxSandbox::$ISOLATE));
    return [$task];
  }

}
