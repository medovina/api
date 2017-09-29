<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
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
  public static $BINARY_ARGS_PORT_KEY = "args";
  public static $INPUT_FILES_PORT_KEY = "input-files";
  public static $STDIN_FILE_PORT_KEY = "stdin";
  public static $OUTPUT_FILE_PORT_KEY = "output-file";
  public static $STDOUT_FILE_PORT_KEY = "stdout";
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
        new Port((new PortMeta)->setName(self::$BINARY_ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)),
        new Port((new PortMeta)->setName(self::$STDIN_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta)->setName(self::$INPUT_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)),
        new Port((new PortMeta)->setName(self::$BINARY_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
      );
      self::$defaultOutputPorts = array(
        new Port((new PortMeta)->setName(self::$STDOUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
        new Port((new PortMeta)->setName(self::$OUTPUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
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
   * @param CompilationParams $params
   * @return array
   */
  public function compile(CompilationParams $params): array {
    $task = new Task();
    $task->setType(TaskType::$EXECUTION);
    $task->setCommandBinary($this->getInputPortValue(self::$BINARY_FILE_PORT_KEY)->getPrefixedValue(ConfigParams::$EVAL_DIR));
    if ($this->hasInputPortValue(self::$BINARY_ARGS_PORT_KEY)) {
      $task->setCommandArguments($this->getInputPortValue(self::$BINARY_ARGS_PORT_KEY)->getValue());
    }

    $sandbox = (new SandboxConfig)->setName(LinuxSandbox::$ISOLATE);
    if ($this->hasInputPortValue(self::$STDIN_FILE_PORT_KEY)) {
      $sandbox->setStdin($this->getInputPortValue(self::$STDIN_FILE_PORT_KEY)->getPrefixedValue(ConfigParams::$EVAL_DIR));
    }
    if ($this->hasOutputPortValue(self::$STDOUT_FILE_PORT_KEY)) {
      $sandbox->setStdout($this->getOutputPortValue(self::$STDOUT_FILE_PORT_KEY)->getPrefixedValue(ConfigParams::$EVAL_DIR));
    }
    $task->setSandboxConfig($sandbox);

    return [$task];
  }

}
