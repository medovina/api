<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Pipeline\Box\FileInBox;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariablesTable;


/**
 * Internal exercise configuration compilation service. This one is supposed
 * to resolve references to variables and fill them directly in ports in boxes.
 * This way next compilation services can compare boxes or directly assign
 * variable values during boxes compilation.
 */
class VariablesResolver {

  /**
   * Regular expressions are allowed only in file inputs and should be resolved
   * against files given during submission.
   * @param Variable|null $variable
   * @param string[] $submittedFiles
   * @return Variable|null
   * @throws ExerciseConfigException
   */
  private function resolveFileInputsRegexp(?Variable $variable,
      array $submittedFiles): ?Variable {
    if (!$variable || !$variable->isFile() || $variable->isValueArray()) {
      // variable is null or variable is not file or value is already array,
      // then no regexp matching is needed
      return $variable;
    }

    // regexp matching of all files against variable value
    $value = $variable->getValue();
    $matches = array_filter($submittedFiles, function (string $file) use ($value) {
      return fnmatch($value, $file);
    });

    if (empty($matches)) {
      // there were no matches, but variable value cannot be empty!
      throw new ExerciseConfigException("Regular expression in variable '{$variable->getName()}' could not be resolved against submitted files");
    }

    // construct resulting variable from given variable info
    $result = (new Variable($variable->getType()))->setName($variable->getName());
    if ($variable->isArray()) {
      $result->setValue($matches);
    } else {
      // variable is not an array, so take only first element from all matches
      $result->setValue(current($matches));
    }

    return $result;
  }

  /**
   * Input boxes has to be treated differently. Variables can be loaded from
   * external configuration - environment config or exercise config.
   * @note Has to be called before @ref resolveForOtherNodes()
   * @param MergeTree $mergeTree
   * @param VariablesTable $environmentVariables
   * @param VariablesTable $exerciseVariables
   * @param VariablesTable $pipelineVariables
   * @param string[] $submittedFiles
   * @throws ExerciseConfigException
   */
  public function resolveForInputNodes(MergeTree $mergeTree,
      VariablesTable $environmentVariables, VariablesTable $exerciseVariables,
      VariablesTable $pipelineVariables, array $submittedFiles) {
    foreach ($mergeTree->getInputNodes() as $node) {

      /** @var FileInBox $inputBox */
      $inputBox = $node->getBox();

      // input data box should have only one output port, that is why current is sufficient
      $outputPort = current($inputBox->getOutputPorts());
      $variableName = $outputPort->getVariable();
      $child = current($node->getChildren());
      $inputPortName = array_search($node, $child->getParents());

      if ($inputPortName === FALSE) {
        // input node not found in parents of the next one
        throw new ExerciseConfigException("Malformed tree - input node '{$inputBox->getName()}' not found in child '{$child->getBox()->getName()}'");
      }

      // variable value in local pipeline config
      $variable = $pipelineVariables->get($variableName);
      if (!$variable) {
        // something is really wrong there... just leave and do not look back
        throw new ExerciseConfigException("Variable '$variableName' from input data box could not be resolved");
      }

      // find references
      $variable = $this->findReferenceIfAny($variable, $environmentVariables, $exerciseVariables);

      // try to look for remote variable in configuration tables
      $inputVariable = null;
      $environmentVariable = $environmentVariables->get($variableName);
      $exerciseVariable = $exerciseVariables->get($variableName);
      if ($environmentVariable) {
        $inputVariable = $this->resolveFileInputsRegexp($environmentVariable, $submittedFiles);
      } else if ($exerciseVariable) {
        $inputVariable = $exerciseVariable;
      }

      if ($variable->isEmpty()) {
        // variable value is empty, replace it with input variable value
        // there is no need for explicit input variable anymore
        $variable = $inputVariable;
        $inputVariable = null;
      }

      // assign variable to both nodes
      $inputBox->setInputVariable($inputVariable);
      $outputPort->setVariableValue($variable);
      $child->getBox()->getInputPort($inputPortName)->setVariableValue($variable);
    }
  }

  /**
   * If variable is reference, try to find it in given variables tables.
   * @param Variable $variable
   * @param VariablesTable $environmentVariables
   * @param VariablesTable $exerciseVariables
   * @return Variable
   * @throws ExerciseConfigException
   */
  private function findReferenceIfAny(Variable $variable,
      VariablesTable $environmentVariables,
      VariablesTable $exerciseVariables): Variable {
    if ($variable->isReference()) {
      $referenceName = $variable->getReference();
      $variable = $environmentVariables->get($referenceName);
      if (!$variable) {
        $variable = $exerciseVariables->get($referenceName);
      }

      // reference could not be found
      if (!$variable) {
        throw new ExerciseConfigException("Variable reference '{$referenceName}' could not be resolved");
      }
    }

    return $variable;
  }

  /**
   * Resolve variables from other nodes, that means nodes which are not input
   * ones. This is general method for handling parent -> children pairs.
   * @note Parent and outPortName can be null
   * @param PortNode|null $parent
   * @param PortNode $child
   * @param string $inPortName
   * @param string|null $outPortName
   * @param VariablesTable $environmentVariables
   * @param VariablesTable $exerciseVariables
   * @param VariablesTable $pipelineVariables
   * @throws ExerciseConfigException
   */
  private function resolveForVariable(?PortNode $parent, PortNode $child,
      string $inPortName, ?string $outPortName,
      VariablesTable $environmentVariables, VariablesTable $exerciseVariables,
      VariablesTable $pipelineVariables) {

    // init
    $inPort = $child->getBox()->getInputPort($inPortName);
    $outPort = $parent === null ? null : $parent->getBox()->getOutputPort($outPortName);

    // check if the ports was processed and processed correctly
    if ($inPort->getVariableValue() !== null) {
      return; // this port was already processed
    } else if ($inPort->getVariableValue() === null && $outPort && $outPort->getVariableValue() !== null) {
      // only input value is assigned... well this is weird
      throw new ExerciseConfigException("Malformed ports detected: $inPortName, $outPortName");
    }

    $variableName = $inPort->getVariable();
    if (empty($variableName)) {
      // variable is either null or empty, this means that we do not have to
      // process it and can safely return
      return;
    }

    // check if variable name is the same in both ports
    if ($outPort !== null && $variableName !== $outPort->getVariable()) {
      throw new ExerciseConfigException("Malformed tree - variables in corresponding ports ($inPortName, $outPortName) do not matches");
    }

    // get the variable from the correct table
    $variable = $pipelineVariables->get($variableName);
    // something's fishy here... better leave now
    if (!$variable) {
      throw new ExerciseConfigException("Variable '$variableName' could not be resolved");
    }

    // variable is reference, try to find its value in external variables tables
    $variable = $this->findReferenceIfAny($variable, $environmentVariables, $exerciseVariables);

    // set variable to both proper ports in child and parent
    $inPort->setVariableValue($variable);
    if ($outPort !== null) { $outPort->setVariableValue($variable); }
  }

  /**
   * Values for variables is taken only from pipeline variables table.
   * This procedure should also process all output boxes.
   * @note Has to be called after @ref resolveForInputNodes()
   * @param MergeTree $mergeTree
   * @param VariablesTable $environmentVariables
   * @param VariablesTable $exerciseVariables
   * @param VariablesTable $pipelineVariables
   * @throws ExerciseConfigException
   */
  public function resolveForOtherNodes(MergeTree $mergeTree,
      VariablesTable $environmentVariables, VariablesTable $exerciseVariables,
      VariablesTable $pipelineVariables) {
    foreach ($mergeTree->getOtherNodes() as $node) {
      foreach ($node->getBox()->getInputPorts() as $inPortName => $inputPort) {
        $parent = $node->getParent($inPortName);
        $outPortName = $parent === null ? null : $parent->findChildPort($node);
        if ($parent !== null && $outPortName === null) {
          // I do not like what you got!
          throw new ExerciseConfigException("Malformed tree - node {$node->getBox()->getName()} not found in parent {$parent->getBox()->getName()}");
        }

        $this->resolveForVariable($parent, $node, $inPortName, $outPortName, $environmentVariables, $exerciseVariables, $pipelineVariables);
      }

      foreach ($node->getChildrenByPort() as $outPortName => $children) {
        foreach ($children as $child) {
          $inPortName = $child->findParentPort($node);
          if (!$inPortName) {
            // Oh boy, here we go throwing exceptions again!
            throw new ExerciseConfigException("Malformed tree - node {$node->getBox()->getName()} not found in child {$child->getBox()->getName()}");
          }

          $this->resolveForVariable($node, $child, $inPortName, $outPortName, $environmentVariables, $exerciseVariables, $pipelineVariables);
        }
      }
    }
  }

  /**
   * Resolve variables for the whole given tree.
   * @param MergeTree $mergeTree
   * @param VariablesTable $environmentVariables
   * @param VariablesTable $exerciseVariables
   * @param VariablesTable $pipelineVariables
   * @param string[] $submittedFiles
   */
  public function resolve(MergeTree $mergeTree,
      VariablesTable $environmentVariables, VariablesTable $exerciseVariables,
      VariablesTable $pipelineVariables, array $submittedFiles) {
    $this->resolveForInputNodes($mergeTree, $environmentVariables, $exerciseVariables, $pipelineVariables, $submittedFiles);
    $this->resolveForOtherNodes($mergeTree, $environmentVariables, $exerciseVariables, $pipelineVariables);
  }

}
