<?php
namespace Psalm\Tests;

class RedundantConditionTest extends TestCase
{
    use Traits\FileCheckerValidCodeParseTestTrait;
    use Traits\FileCheckerInvalidCodeParseTestTrait;

    /**
     * @return array
     */
    public function providerFileCheckerValidCodeParse()
    {
        return [
            'ignoreIssueAndAssign' => [
                '<?php
                    public function foo() : stdClass {
                        return new stdClass;
                    }

                    $b = null;

                    foreach ([0, 1] as $i) {
                        $a = foo();

                        if (!empty($a)) {
                            $b = $a;
                        }
                    }',
                'assertions' => [
                    '$b' => 'null|stdClass',
                ],
                'error_levels' => ['RedundantCondition'],
            ],
            'byrefNoRedundantCondition' => [
                '<?php
                    /**
                     * @param int $min ref
                     * @param int $other
                     */
                    function testmin(&$min, int $other) : void {
                        if (is_null($min)) {
                            $min = 3;
                        } elseif (!is_int($min)) {
                            $min = 5;
                        } elseif ($min < $other) {
                            $min = $other;
                        }
                    }',
            ],
            'assignmentInIf' => [
                '<?php
                    function test(int $x = null) : int {
                        if (!$x && !($x = rand(0, 10))) {
                            echo "Failed to get non-empty x\n";
                            return -1;
                        }
                        return $x;
                    }',
            ],
            'noRedundantConditionAfterAssignment' => [
                '<?php
                    /** @param int $i */
                    function foo($i) : void {
                        if ($i !== null) {
                            $i = (int) $i;

                            if ($i) {}
                        }
                    }',
            ],
            'noRedundantConditionAfterDocblockTypeNullCheck' => [
                '<?php
                    class A {
                        /** @var ?int */
                        public $foo;
                    }
                    class B {}

                    /**
                     * @param  A|B $i
                     */
                    function foo($i) : void {
                        if (empty($i)) {
                            return;
                        }

                        switch (get_class($i)) {
                            case "A":
                                if ($i->foo) {}
                                break;

                            default:
                                break;
                        }
                    }',
            ],
            'noRedundantConditionTypeReplacementWithDocblock' => [
                '<?php
                    class A {}

                    /**
                     * @return A
                     */
                    function getA() {
                        return new A();
                    }

                    $maybe_a = rand(0, 1) ? new A : null;

                    if ($maybe_a === null) {
                        $maybe_a = getA();
                    }

                    if ($maybe_a === null) {}',
            ],
            'noRedundantConditionAfterPossiblyNullCheck' => [
                '<?php
                    if (rand(0, 1)) {
                        $a = "hello";
                    }

                    if ($a) {}',
                'assertions' => [],
                'error_levels' => ['PossiblyUndefinedGlobalVariable'],
            ],
            'noRedundantConditionAfterFromDocblockRemoval' => [
                '<?php
                    class A {
                      public function foo() : void{}
                      public function bar() : void{}
                    }

                    /** @return A */
                    function makeA() {
                      return new A;
                    }

                    $a = makeA();

                    if ($a === null) {
                      exit;
                    }

                    if ($a->foo() || $a->bar()) {}',
            ],
            'noEmptyUndefinedArrayVar' => [
                '<?php
                    if (rand(0,1)) {
                      /** @psalm-suppress UndefinedGlobalVariable */
                      $a = $b[0];
                    } else {
                      $a = null;
                    }
                    if ($a) {}',
                'assertions' => [],
                'error_levels' => ['MixedAssignment', 'MixedArrayAccess'],
            ],
            'noComplaintWithIsNumericThenIsEmpty' => [
                '<?php
                    function takesString(string $s) : void {
                      if (!is_numeric($s) || empty($s)) {}
                    }',
            ],
            'noRedundantConditionOnTryCatchVars' => [
                '<?php
                    function trycatch() : void {
                        $value = null;
                        try {
                            if (rand() % 2 > 0) {
                                throw new RuntimeException("Failed");
                            }
                            $value = new stdClass();
                            if (rand() % 2 > 0) {
                                throw new RuntimeException("Failed");
                            }
                        } catch (Exception $e) {
                            if ($value) {
                                var_export($value);
                            }
                        }

                        if ($value) {}
                    }',
            ],
            'noRedundantConditionInFalseCheck' => [
                '<?php
                    $ch = curl_init();
                    if (!$ch) {}',
            ],
            'noRedundantConditionInForCheck' => [
                '<?php
                    class Node
                    {
                        /** @var Node|null */
                        public $next;

                        public function iterate(): void
                        {
                            for ($node = $this; $node !== null; $node = $node->next) {}
                        }
                    }',
            ],
            'noRedundantConditionComparingBool' => [
                '<?php
                    function getBool() : bool {
                      return (bool)rand(0, 1);
                    }

                    function takesBool(bool $b) : void {
                      if ($b === getBool()) {}
                    }',
            ],
            'evaluateElseifProperly' => [
                '<?php
                    /** @param string $str */
                    function foo($str) : int {
                      if (is_null($str)) {
                        return 1;
                      } else if (strlen($str) < 1) {
                        return 2;
                      }
                      return 2;
                    }',
            ],
            'evaluateArrayCheck' => [
                '<?php
                    function array_check() : void {
                        $data = ["f" => false];
                        while (rand(0, 1) > 0 && !$data["f"]) {
                            $data = ["f" => true];
                        }
                    }',
            ],
        ];
    }

    /**
     * @return array
     */
    public function providerFileCheckerInvalidCodeParse()
    {
        return [
            'ifFalse' => [
                '<?php
                    $y = false:
                    if ($y) {}',
                'error_message' => 'RedundantCondition',
            ],
            'ifNotTrue' => [
                '<?php
                    $y = true:
                    if (!$y) {}',
                'error_message' => 'RedundantCondition',
            ],
            'ifTrue' => [
                '<?php
                    $y = true:
                    if ($y) {}',
                'error_message' => 'RedundantCondition',
            ],
            'unnecessaryInstanceof' => [
                '<?php
                    class One {
                        public function fooFoo() {}
                    }

                    $var = new One();

                    if ($var instanceof One) {
                        $var->fooFoo();
                    }',
                'error_message' => 'RedundantCondition',
            ],
            'failedTypeResolution' => [
                '<?php
                    class A { }

                    /**
                     * @return void
                     */
                    function fooFoo(A $a) {
                        if ($a instanceof A) {
                        }
                    }',
                'error_message' => 'RedundantCondition',
            ],
            'failedTypeResolutionWithDocblock' => [
                '<?php
                    class A { }

                    /**
                     * @param  A $a
                     * @return void
                     */
                    function fooFoo(A $a) {
                        if ($a instanceof A) {
                        }
                    }',
                'error_message' => 'RedundantCondition',
            ],
            'typeResolutionFromDocblockAndInstanceof' => [
                '<?php
                    class A { }

                    /**
                     * @param  A $a
                     * @return void
                     */
                    function fooFoo($a) {
                        if ($a instanceof A) {
                            if ($a instanceof A) {
                            }
                        }
                    }',
                'error_message' => 'RedundantCondition',
            ],
            'typeResolutionRepeatingConditionWithSingleVar' => [
                '<?php
                    $a = rand(0, 10) > 5;
                    if ($a && $a) {}',
                'error_message' => 'RedundantCondition',
            ],
            'typeResolutionRepeatingConditionWithVarInMiddle' => [
                '<?php
                    $a = rand(0, 10) > 5;
                    $b = rand(0, 10) > 5;
                    if ($a && $b && $a) {}',
                'error_message' => 'RedundantCondition',
            ],
            'typeResolutionIsIntAndIsNumeric' => [
                '<?php
                    $c = rand(0, 10) > 5 ? "hello" : 3;
                    if (is_int($c) && is_numeric($c)) {}',
                'error_message' => 'RedundantCondition',
            ],
            'typeResolutionWithInstanceOfAndNotEmpty' => [
                '<?php
                    $x = rand(0, 10) > 5 ? new stdClass : null;
                    if ($x instanceof stdClass && $x) {}',
                'error_message' => 'RedundantCondition',
            ],
            'methodWithMeaninglessCheck' => [
                '<?php
                    class One {
                        /** @return void */
                        public function fooFoo() {}
                    }

                    class B {
                        /** @return void */
                        public function barBar(One $one) {
                            if (!$one) {
                                // do nothing
                            }

                            $one->fooFoo();
                        }
                    }',
                'error_message' => 'RedundantCondition',
            ],
            'SKIPPED-twoVarLogicNotNestedWithElseifNegatedInIf' => [
                '<?php
                    function foo(?string $a, ?string $b) : ?string {
                        if ($a) {
                            $a = null;
                        } elseif ($b) {
                            // do nothing here
                        } else {
                            return "bad";
                        }

                        if (!$a) return $b;
                        return $a;
                    }',
                'error_message' => 'RedundantCondition',
            ],
            'refineTypeInMethodCall' => [
                '<?php
                    class A {}

                    /** @return ?A */
                    function getA() {
                      return rand(0, 1) ? new A : null;
                    }

                    function takesA(A $a) : void {}

                    $a = getA();
                    if ($a instanceof A) {}
                    /** @psalm-suppress PossiblyNullArgument */
                    takesA($a);
                    if ($a instanceof A) {}',
                'error_message' => 'RedundantCondition - src/somefile.php:15',
            ],
        ];
    }
}
