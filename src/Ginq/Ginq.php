<?php
/**
 * Ginq: `LINQ to Object` inspired DSL for PHP
 * Copyright 2013, Atsushi Kanehara <akanehara@gmail.com>
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * PHP Version 5.3 or later
 *
 * @author     Atsushi Kanehara <akanehara@gmail.com>
 * @copyright  Copyright 2013, Atsushi Kanehara <akanehara@gmail.com>
 * @license    MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @package    Ginq
 */

namespace Ginq;

use Ginq\Core\Selector;
use Ginq\Comparer\ComparerParser;
use Ginq\Core\JoinSelector;
use Ginq\Core\Comparer;
use Ginq\Core\EqualityComparer;
use Ginq\EqualityComparer\EqualityComparerParser;
use Ginq\JoinSelector\KeyJoinSelector;
use Ginq\JoinSelector\ValueJoinSelector;
use Ginq\Selector\KeySelector;
use Ginq\Selector\SelectorParser;
use Ginq\JoinSelector\JoinSelectorParser;
use Ginq\Predicate\PredicateParser;
use Ginq\Selector\ValueSelector;
use Ginq\Util\FuncUtil;
use Ginq\Util\IteratorUtil;
use Ginq\Comparer\ReverseComparer;
use Ginq\Comparer\ProjectionComparer;
use Ginq\OrderingGinq;

/**
 * GinqContext
 *
 * @package Ginq
 */
class Ginq implements \IteratorAggregate
{
    /**
     * @var array|\Iterator
     */
    protected $it;

    /**
     * @var Core\IterProvider
     */
    protected static $gen = null;

    public static function useIterator()
    {
        self::$gen = Core\IterProviderIterImpl::getInstance();
    }

    /**
     * Constructor
     *
     * @param array|\Iterator $it  Any traversable variable
     */
    protected function __construct($it)
    {
        $this->it = $it;
    }

    /**
     * default compare function
     * @param mixed $x
     * @param mixed $y
     * @return int
     */
    public static function compare($x, $y) {
        return Comparer::getDefault()->compare($x, $y);
    }

    /**
     * default equality compare function
     * @param mixed $x
     * @param mixed $y
     * @return bool
     */
    public static function equals($x, $y) {
        return EqualityComparer::getDefault()->equals($x, $y);
    }

    /**
     * default hash function
     * @param mixed $x
     * @return string
     */
    public static function hash($x) {
        return EqualityComparer::getDefault()->hash($x);
    }

    /**
     * Overridden interface of IteratorAggregate.
     * @return \Iterator
     */
    public function getIterator()
    {
        return $this->it;
    }

    /**
     * @param callable|null  $combiner (existV, v, k) -> mixed
     * @return array
     */
    public function toArray($combiner = null)
    {
        if (is_null($combiner)) {
            return IteratorUtil::toArray($this->getIterator());
        } else {
            return IteratorUtil::toArrayWithCombine($this->getIterator(), $combiner);
        }
    }

    /**
     * @param int|null       $depth
     * @param callable|null  $combiner (existV, v, k) -> mixed
     * @return array
     */
    public function toArrayRec($depth = null, $combiner = null)
    {
        if (is_null($combiner)) {
            return IteratorUtil::toArrayRec($this->getIterator(), $depth);
        } else {
            return IteratorUtil::toArrayRecWithCombine($this->getIterator(), $depth, $combiner);
        }
    }

    /**
     * @param \Closure|string|Selector $lookupKeySelector (v, k) -> mixed
     * @param \Closure|string|Selector $elementSelector   (v, k) -> mixed
     * @param EqualityComparer         $eqComparer
     * @return LookupGinq
     */
    public function toLookup($lookupKeySelector, $elementSelector = null, $eqComparer = null)
    {
        $lookupKeySelector = SelectorParser::parse($lookupKeySelector, ValueSelector::getInstance());
        $elementSelector   = SelectorParser::parse($elementSelector, ValueSelector::getInstance());
        $lookup = new LookupGinq(
            EqualityComparerParser::parse($eqComparer, EqualityComparer::getDefault())
        );
        $it = $this->getIterator();
        foreach ($it as $k => $v) {
            $lookup->put(
                $lookupKeySelector->select($v, $k),
                $elementSelector->select($v, $k)
            );
        }
        return $lookup;
    }

    /**
     * @return array
     */
    public function toList()
    {
        return IteratorUtil::toList($this->getIterator());
    }

    /**
     * @param null|int $depth
     * @return array
     */
    public function toListRec($depth = null)
    {
        return IteratorUtil::toListRec($this->getIterator(), $depth);
    }

    /**
     * @return array
     */
    public function toAList()
    {
        return IteratorUtil::toAList($this->getIterator());
    }

    /**
     * @param null|int $depth
     * @return array
     */
    public function toAListRec($depth = null)
    {
        return IteratorUtil::toAListRec($this->getIterator(), $depth);
    }

    /**
     * @param string|callable $predicate (v, k) -> bool
     * @return bool
     */
    public function any($predicate)
    {
        $p = PredicateParser::parse($predicate);
        foreach ($this->getIterator() as $k => $v) {
            if ($p->predicate($v, $k) == true) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string|callable $predicate (v, k) -> bool
     * @return bool
     */
    public function all($predicate)
    {
        $p = PredicateParser::parse($predicate);
        foreach ($this->getIterator() as $k => $v) {
            if ($p->predicate($v, $k) == false) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param \Closure|string|null $predicate (v, k) -> bool
     * @return int
     */
    public function count($predicate = null)
    {
        if (is_null($predicate)) {
            return $this->foldLeft(0, function ($acc) { return $acc + 1; });
        } else {
            $p = PredicateParser::parse($predicate);
            return $this->foldLeft(0,
                function ($acc, $v, $k) use ($p) {
                    return ($p->predicate($v, $k)) ? ($acc + 1) : $acc;
                }
            );
        }
    }

    /**
     * @param \Closure|string|null $selector (v, k) -> int
     * @return int
     */
    public function sum($selector = null)
    {
        $s = SelectorParser::parse($selector, ValueSelector::getInstance());
        return $this->foldLeft(0,
            function ($acc, $v, $k) use($s) {
                return $acc + $s->select($v, $k);
            }
        );
    }

    /**
     * @param \Closure|string|null $selector (v, k) -> number
     * @throws \RuntimeException
     * @return float
     */
    public function average($selector = null)
    {
        $it = $this->getIterator();
        if (!$it->valid()) {
            throw new \RuntimeException("Sequence is empty");
        }
        $selector = SelectorParser::parse($selector, ValueSelector::getInstance());
        $sum   = 0;
        $count = 0;
        foreach ($this as $k => $v) {
            $count++;
            $sum += $selector->select($v, $k);
        }
        return $sum / $count;
    }

    /**
     * @param \Closure|string|null $selector (v, k) -> comparable
     * @throws \RuntimeException
     * @return mixed
     */
    public function min($selector = null)
    {
        $it = $this->getIterator();
        if (!$it->valid()) {
            throw new \RuntimeException("Sequence is empty");
        }
        $comparer = Comparer::getDefault();
        $selector = SelectorParser::parse($selector, ValueSelector::getInstance());
        $min = $selector->select($it->current(), $it->key());
        $it->next();
        while ($it->valid()) {
            $x = $selector->select($it->current(), $it->key());
            if ($comparer->compare($x, $min) < 0) $min = $x;
            $it->next();
        }
        return $min;
    }

    /**
     * @param \Closure|string|null $selector (v, k) -> comparable
     * @throws \RuntimeException
     * @return mixed
     */
    public function max($selector = null)
    {
        $it = $this->getIterator();
        if (!$it->valid()) {
            throw new \RuntimeException("Sequence is empty");
        }
        $comparer = Comparer::getDefault();
        $selector = SelectorParser::parse($selector, ValueSelector::getInstance());
        $max = $selector->select($it->current(), $it->key());
        $it->next();
        while ($it->valid()) {
            $x = $selector->select($it->current(), $it->key());
            if (0 < $comparer->compare($x, $max)) $max = $x;
            $it->next();
        }
        return $max;
    }

    /**
     * @param callable|null $predicate (v, k) -> bool
     * @return mixed
     * @throws \RuntimeException
     */
    public function first($predicate = null)
    {
        if (is_null($predicate)) {
            $it = $this->getIterator();
            $it->rewind();
            if ($it->valid()) {
                return $it->current();
            } else {
                throw new \RuntimeException("Sequence is empty");
            }
        } else {
            foreach ($this->getIterator() as $k => $v) {
                if ($predicate($v, $k)) {
                    return $v;
                }
            }
            throw new \RuntimeException("No items matched the predicate");
        }
    }

    /**
     * @param mixed|\Closure $default
     * @param callable|null  $predicate (v, k) -> bool
     * @return mixed
     */
    public function firstOrElse($default, $predicate = null)
    {
        if (is_null($predicate)) {
            $it = $this->getIterator();
            $it->rewind();
            if ($it->valid()) {
                return $it->current();
            } else {
                return FuncUtil::applyOrItself($default);
            }
        } else {
            foreach ($this->getIterator() as $k => $v) {
                if ($predicate($v, $k)) {
                    return $v;
                }
            }
            return FuncUtil::applyOrItself($default);
        }
    }

    /**
     * @param mixed|\Closure $default
     * @return Ginq
     */
    public function elseIfZero($default)
    {
        $it = $this->getIterator();
        $it->rewind();
        if ($it->valid()) {
            return $this;
        } else {
            if ($default instanceof \Closure) {
                return self::from(self::$gen->lazyRepeat($default, 1));
            } else {
                return self::from(self::$gen->repeat($default, 1));
            }
        }
    }

    /**
     * @param callable|null $predicate (v, k) -> bool
     * @return mixed
     * @throws \RuntimeException
     */
    public function last($predicate = null)
    {
        if (is_null($predicate)) {
            $last  = null;
            $found = false;
            foreach ($this->getIterator() as $k => $v) {
                $last = $v;
                $found = true;
            }
            if ($found) {
                return $last;
            } else {
                throw new \RuntimeException("Sequence is empty");
            }
        } else {
            $last  = null;
            $found = false;
            foreach ($this->getIterator() as $k => $v) {
                if ($predicate($v, $k)) {
                    $last = $v;
                    $found = true;
                }
            }
            if ($found) {
                return $last;
            } else {
                throw new \RuntimeException("No items matched the predicate");
            }
        }
    }

    /**
     * @param mixed|\Closure $default
     * @param callable|null  $predicate (v, k) -> bool
     * @return mixed
     */
    public function lastOrElse($default, $predicate = null)
    {
        if (is_null($predicate)) {
            $last  = null;
            $found = false;
            foreach ($this->getIterator() as $k => $v) {
                $last = $v;
                $found = true;
            }
            if ($found) {
                return $last;
            } else {
                return FuncUtil::applyOrItself($default);
            }
        } else {
            $last  = null;
            $found = false;
            foreach ($this->getIterator() as $k => $v) {
                if ($predicate($v, $k)) {
                    $last = $v;
                    $found = true;
                }
            }
            if ($found) {
                return $last;
            } else {
                return FuncUtil::applyOrItself($default);
            }
        }
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function contains($value)
    {
        $eqComparer = EqualityComparer::getDefault();
        return $this->any(
            function ($v, $k) use ($value, $eqComparer) {
                return $eqComparer->equals($v, $value);
            }
        );
    }

    /**
     * @param mixed $key
     * @return bool
     */
    public function containsKey($key)
    {
        $eqComparer = EqualityComparer::getDefault();
        return $this->any(
            function ($v, $k) use ($key, $eqComparer) {
                return $eqComparer->equals($k, $key);
            }
        );
    }



    /**
     * @param mixed $accumulator
     * @param callable $operator (acc, v, k) -> mixed
     * @return mixed
     */
    public function aggregate($accumulator, $operator)
    {
        return $this->foldLeft($accumulator, $operator);
    }

    /**
     * @param mixed $accumulator
     * @param callable $operator (acc, v, k) -> mixed
     * @return mixed
     */
    public function foldLeft($accumulator, $operator)
    {
        $acc = $accumulator;
        foreach ($this as $k => $v) {
            $acc = $operator($acc, $v, $k);
        }
        return $acc;
    }

    /**
     * @param mixed $accumulator
     * @param callable $operator (acc, v, k) -> mixed
     * @return mixed
     */
    public function foldRight($accumulator, $operator)
    {
        $acc = $accumulator;
        foreach ($this->reverse() as $k => $v) {
            $acc = $operator($acc, $v, $k);
        }
        return $acc;
    }

    /**
     * @param \Closure $operator (acc, v, k) -> mixed
     * @return mixed
     * @throws \LengthException
     */
    public function reduceLeft($operator)
    {
        $it = $this->getIterator();
        $it->rewind();
        if (!$it->valid()) {
            throw new \LengthException("reduce of empty sequence");
        }
        $acc = $it->current();
        $it->next();
        while ($it->valid()) {
            $acc = $operator($acc, $it->current(), $it->key());
            $it->next();
        }
        return $acc;
    }

    /**
     * @param \Closure $operator (acc, v, k) -> mixed
     * @return mixed
     * @throws \LengthException
     */
    public function reduceRight($operator)
    {
        $it = $this->reverse()->getIterator();
        $it->rewind();
        if (!$it->valid()) {
            throw new \LengthException("reduce of empty sequence");
        }
        $acc = $it->current();
        $it->next();
        while ($it->valid()) {
            $acc = $operator($acc, $it->current(), $it->key());
            $it->next();
        }
        return $acc;
    }

    /**
     * empty
     * @return Ginq
     */
    public static function zero()
    {
        return self::from(self::$gen->zero());
    }

    /**
     * @param number      $start
     * @param number|null $stop
     * @param number|int  $step
     * @throws \InvalidArgumentException
     * @return Ginq
     */
    public static function range($start, $stop = null, $step = 1)
    {
        if (!is_numeric($start)) {
            throw new \InvalidArgumentException(
                "Ginq::range() numeric start arguments expected.");
        }
        if (is_null($stop)) {
            return self::from(self::$gen->rangeInf($start, $step));
        } else {
            return self::from(self::$gen->range($start, $stop, $step));
        }
    }

    /**
     * @param mixed $element
     * @param null|int   $count
     * @return Ginq
     */
    public static function repeat($element, $count = null)
    {
        return self::from(self::$gen->repeat($element, $count));
    }

    /**
     * @param array|\Iterator|\IteratorAggregate $xs
     * @return Ginq
     */
    public static function cycle($xs)
    {
        return self::from(self::$gen->cycle(IteratorUtil::iterator($xs)));
    }

    /**
     * @param array|\Iterator|\IteratorAggregate $xs
     * @return Ginq
     */
    public static function from($xs)
    {
        if ($xs instanceof self) {
            return $xs;
        } else {
            return new self(IteratorUtil::iterator($xs));
        }
    }

    /**
     * @param \Closure $sourceFactory
     * @throws \InvalidArgumentException
     * @return Ginq
     */
    public static function fromLazy($sourceFactory)
    {
        if (is_callable($sourceFactory)) {
            return new self(self::$gen->lazySource($sourceFactory));
        } else {
            throw new \InvalidArgumentException('$sourceFactory is not callable');
        }
    }

    /**
     * @return Ginq
     */
    public function renum()
    {
        return self::from(self::$gen->renum($this->getIterator()));
    }

    /**
     * @param \Closure $fn
     * @return Ginq
     */
    public function each($fn)
    {
        return self::from(self::$gen->each($this->getIterator(), $fn));
    }

    /**
     * @param \Closure|string|int|Selector|null $valueSelector (v, k) -> mixed
     * @param \Closure|string|int|Selector|null $keySelector   (v, k) -> mixed
     * @return Ginq
     */
    public function map($valueSelector = null, $keySelector = null)
    {
        return $this->select($valueSelector, $keySelector);
    }

    /**
     * @param \Closure|string|int|Selector|null $valueSelector (v, k) -> mixed
     * @param \Closure|string|int|Selector|null $keySelector   (v, k) -> mixed
     * @return Ginq
     */
    public function select($valueSelector = null, $keySelector = null)
    {
        return self::from(self::$gen->select(
            $this->getIterator(),
            SelectorParser::parse($valueSelector, ValueSelector::getInstance()),
            SelectorParser::parse($keySelector, KeySelector::getInstance())
        ));
    }

    /**
     * @param string|callable $predicate (v, k) -> bool
     * @return Ginq
     */
    public function filter($predicate)
    {
        return $this->where($predicate);
    }

    /**
     * @param string|callable $predicate (v, k) -> bool
     * @return Ginq
     */
    public function where($predicate)
    {
        return self::from(self::$gen->where(
            $this->getIterator(),
            PredicateParser::parse($predicate)
        ));
    }

    /**
     * @return Ginq
     */
    public function reverse()
    {
        return self::from(self::$gen->reverse($this->getIterator()));
    }

    /**
     * @param int $n
     * @return Ginq
     */
    public function take($n)
    {
        return self::from(self::$gen->take($this->getIterator(), $n));
    }

    /**
     * @param int $n
     * @return Ginq
     */
    public function drop($n)
    {
        return self::from(self::$gen->drop($this->getIterator(), $n));
    }

    /**
     * @param string|callable $predicate (v, k) -> bool
     * @return Ginq
     */
    public function takeWhile($predicate)
    {
        return self::from(self::$gen->takeWhile(
            $this->getIterator(),
            PredicateParser::parse($predicate)
        ));
    }

    /**
     * @param string|callable $predicate (v, k) -> bool
     * @return Ginq
     */
    public function dropWhile($predicate)
    {
        return self::from(self::$gen->dropWhile(
            $this->getIterator(), PredicateParser::parse($predicate)
        ));
    }

    /**
     * @param array|\Iterator|\IteratorAggregate $rhs
     * @return Ginq
     */
    public function concat($rhs)
    {
        return self::from(self::$gen->concat(
            $this->getIterator(),
            self::from(IteratorUtil::iterator($rhs))
        ));
    }

    /**
     * @param \Closure|string|Selector     $manySelector (v, k) -> array|Traversable
     * @param \Closure|JoinSelector|null   $resultValueSelector (v0, v1, k0, k1) -> mixed
     * @param \Closure|JoinSelector|null   $resultKeySelector (v0, v1, k0, k1) -> mixed
     * @return Ginq
     */
    public function flatMap($manySelector, $resultValueSelector = null, $resultKeySelector = null)
    {
        return $this->selectMany($manySelector, $resultValueSelector, $resultKeySelector);
    }

    /**
     * @param \Closure|string|Selector     $manySelector (v, k) -> array|Traversable
     * @param \Closure|JoinSelector|null   $resultValueSelector (v0, v1, k0, k1) -> mixed
     * @param \Closure|JoinSelector|null   $resultKeySelector (v0, v1, k0, k1) -> mixed
     * @return Ginq
     */
    public function selectMany($manySelector, $resultValueSelector = null, $resultKeySelector = null)
    {
        if (is_null($resultValueSelector) && is_null($resultKeySelector)) {
            return self::from(self::$gen->selectMany(
                $this->getIterator(),
                SelectorParser::parse($manySelector, ValueSelector::getInstance())));
        } else {
            return self::from(self::$gen->selectManyWithJoin(
                $this->getIterator(),
                SelectorParser::parse($manySelector, ValueSelector::getInstance()),
                JoinSelectorParser::parse($resultValueSelector, ValueJoinSelector::getInstance()),
                JoinSelectorParser::parse($resultKeySelector, KeyJoinSelector::getInstance())
            ));
        }
    }

    /**
     * @param array|\Iterator|\IteratorAggregate $inner
     * @param \Closure|string|int|Selector   $outerCompareKeySelector (v, k) -> comparable
     * @param \Closure|string|int|Selector   $innerCompareKeySelector (v, k) -> comparable
     * @param \Closure|JoinSelector|int      $resultValueSelector (v0, v1, k0, k1) -> mixedd
     * @param \Closure|JoinSelector|int|null $resultKeySelector (v0, v1, k0, k1) -> mixedd
     * @return Ginq
     */
    public function join($inner,
                         $outerCompareKeySelector, $innerCompareKeySelector,
                         $resultValueSelector, $resultKeySelector = null)
    {
        return self::from(self::$gen->join(
            $this->getIterator(),
            IteratorUtil::iterator($inner),
            SelectorParser::parse($outerCompareKeySelector, ValueSelector::getInstance()),
            SelectorParser::parse($innerCompareKeySelector, ValueSelector::getInstance()),
            JoinSelectorParser::parse($resultValueSelector, ValueJoinSelector::getInstance()),
            JoinSelectorParser::parse($resultKeySelector,   KeyJoinSelector::getInstance()),
            EqualityComparerParser::parse(null, EqualityComparer::getDefault())
        ));
    }

    /**
     * @param array|\Iterator|\IteratorAggregate    $rhs
     * @param \Closure|JoinSelector|int      $resultValueSelector (v0, v1, k0, k1) -> mixed
     * @param \Closure|JoinSelector|int|null $resultKeySelector (v0, v1, k0, k1) -> mixed
     * @return Ginq
     */
    public function zip($rhs, $resultValueSelector, $resultKeySelector = null)
    {
        return self::from(self::$gen->zip(
            $this->getIterator(),
            IteratorUtil::iterator($rhs),
            JoinSelectorParser::parse($resultValueSelector, ValueJoinSelector::getInstance()),
            JoinSelectorParser::parse($resultKeySelector,   KeyJoinSelector::getInstance())
        ));
    }

    /**
     * @param \Closure|string|Selector       $compareKeySelector (v, k) -> mixed
     * @param \Closure|string|Selector|null  $elementSelector    (v, k) -> mixed
     * @return Ginq
     */
    public function groupBy($compareKeySelector, $elementSelector = null)
    {
        return self::from(self::$gen->groupBy(
            $this->getIterator(),
            SelectorParser::parse($compareKeySelector, ValueSelector::getInstance()),
            SelectorParser::parse($elementSelector,    ValueSelector::getInstance()),
            EqualityComparer::getDefault()
        ));
    }

    /**
     * @param array|\Iterator|\IteratorAggregate $inner
     * @param \Closure|string|int|Selector   $outerCompareKeySelector (v, k) -> comparable
     * @param \Closure|string|int|Selector   $innerCompareKeySelector (v, k) -> comparable
     * @param \Closure|JoinSelector|int      $resultValueSelector (outer, inners, outerKey, innerKey) -> mixed
     * @param \Closure|JoinSelector|int|null $resultKeySelector   (outer, ineers, outerKey, innerKey) -> mixed
     * @return Ginq
     */
    public function groupJoin($inner,
                              $outerCompareKeySelector, $innerCompareKeySelector,
                              $resultValueSelector, $resultKeySelector = null)
    {
        return self::from(self::$gen->groupJoin(
            $this->getIterator(),
            IteratorUtil::iterator($inner),
            SelectorParser::parse($outerCompareKeySelector, ValueSelector::getInstance()),
            SelectorParser::parse($innerCompareKeySelector, ValueSelector::getInstance()),
            JoinSelectorParser::parse($resultValueSelector, ValueJoinSelector::getInstance()),
            JoinSelectorParser::parse($resultKeySelector,   KeyJoinSelector::getInstance()),
            EqualityComparerParser::parse(null, EqualityComparer::getDefault())
        ));
    }

    /**
     * @param \Closure|string|int|Selector|null $compareKeySelector (v, k) -> comparable
     * @return OrderingGinq
     */
    public function orderBy($compareKeySelector = null)
    {
        $compareKeySelector = SelectorParser::parse($compareKeySelector, ValueSelector::getInstance());
        $comparer = ComparerParser::parse(null, Comparer::getDefault());
        $comparer = new ProjectionComparer($compareKeySelector, $comparer);
        return new OrderingGinq($this->getIterator(), $comparer);
    }

    /**
     * @param \Closure|string|int|Selector|null $compareKeySelector (v, k) -> comparable
     * @return OrderingGinq
     */
    public function orderByDesc($compareKeySelector = null)
    {
        $compareKeySelector = SelectorParser::parse($compareKeySelector, ValueSelector::getInstance());
        $comparer = ComparerParser::parse(null, Comparer::getDefault());
        $comparer = new ProjectionComparer($compareKeySelector, $comparer);
        $comparer = new ReverseComparer($comparer);
        return new OrderingGinq($this->getIterator(), $comparer);
    }

    /**
     * @return Ginq
     */
    public function distinct()
    {
        return self::from(
            self::$gen->distinct($this->getIterator(),
                EqualityComparerParser::parse(null, EqualityComparer::getDefault())
            ));
    }

    /**
     * @param array|\Iterator|\IteratorAggregate $rhs
     * @return Ginq
     */
    public function union($rhs)
    {
        return self::from(
            self::$gen->union(
                $this->getIterator(),
                $rhs,
                EqualityComparerParser::parse(null, EqualityComparer::getDefault())
            ));
    }

    /**
     * @param array|\Iterator|\IteratorAggregate $rhs
     * @return Ginq
     */
    public function intersect($rhs)
    {
        return self::from(
            self::$gen->intersect(
                $this->getIterator(),
                $rhs,
                EqualityComparerParser::parse(null, EqualityComparer::getDefault())
            ));
    }

    /**
     * @param array|\Iterator|\IteratorAggregate $rhs
     * @return Ginq
     */
    public function except($rhs)
    {
        return self::from(
            self::$gen->except(
                $this->getIterator(),
                $rhs,
                EqualityComparerParser::parse(null, EqualityComparer::getDefault())
            ));
    }

    /**
     * @param array|\Iterator|\IteratorAggregate $rhs
     * @return bool
     */
    public function sequenceEquals($rhs)
    {
        $eqComparer = EqualityComparerParser::parse(null, EqualityComparer::getDefault());
        $lhs = $this->getIterator();
        $rhs = IteratorUtil::iterator($rhs);
        if ($lhs instanceof \Countable && $rhs instanceof \Countable) {
            if ($lhs->count() !== $rhs->count()) {
                return false;
            }
        }
        $lhs->rewind(); $rhs->rewind();
        while ($lhs->valid()) {
            $v0 = $lhs->current();
            if ($rhs->valid()) {
                $v1 = $rhs->current();
                if (!$eqComparer->equals($v0, $v1)) {
                    return false;
                }
                $rhs->next();
            } else {
                return false;
            }
            $lhs->next();
        }
        return true;
    }

    /**
     * @deprecated
     * @param int $index
     * @throws \RuntimeException
     * @throws \OutOfRangeException
     * @return mixed
     */
    public function getValueAt($index)
    {
        return $this->getAt($index);
    }

    /**
     * @param int $index
     * @throws \RuntimeException
     * @throws \OutOfRangeException
     * @return mixed
     */
    public function getAt($index)
    {
        $it = $this->getIterator();
        if ($it instanceof \Countable) {
            if ($it->count() <= $index) {
                throw new \OutOfRangeException('index out of range.');
            }
        }
        $it->rewind();
        if (!$it->valid()) {
            throw new \RuntimeException("Sequence is empty");
        }
        for ($i = 0; $i < $index; $i++) {
            $it->next();
            if (!$it->valid()) {
                throw new \OutOfRangeException('index out of range.');
            }
        }
        return $it->current();
    }

    /**
     * @deprecated
     * @param int            $index
     * @param mixed|\Closure $default
     * @return mixed
     */
    public function getValueAtOrElse($index, $default)
    {
        return $this->getAtOrElse($index, $default);
    }

    /**
     * @param int            $index
     * @param mixed|\Closure $default
     * @return mixed
     */
    public function getAtOrElse($index, $default)
    {
        $it = $this->getIterator();
        if ($it instanceof \Countable) {
            if ($it->count() <= $index) {
                return FuncUtil::applyOrItself($default, $index);
            }
        }
        $it->rewind();
        if (!$it->valid()) {
            return FuncUtil::applyOrItself($default, $index);
        }
        for ($i = 0; $i < $index; $i++) {
            $it->next();
            if (!$it->valid()) {
                return FuncUtil::applyOrItself($default, $index);
            }
        }
        return $it->current();
    }

    /**
     * @param int $index
     * @throws \RuntimeException
     * @throws \OutOfRangeException
     * @return mixed
     */
    public function getKeyAt($index)
    {
        $it = $this->getIterator();
        if ($it instanceof \Countable) {
            if ($it->count() <= $index) {
                throw new \OutOfRangeException('index out of range.');
            }
        }
        $it->rewind();
        if (!$it->valid()) {
            throw new \RuntimeException("Sequence is empty");
        }
        for ($i = 0; $i < $index; $i++) {
            $it->next();
            if (!$it->valid()) {
                throw new \OutOfRangeException('index out of range.');
            }
        }
        return $it->key();
    }

    /**
     * @param int            $index
     * @param mixed|\Closure $default
     * @return mixed
     */
    public function getKeyAtOrElse($index, $default)
    {
        $it = $this->getIterator();
        if ($it instanceof \Countable) {
            if ($it->count() <= $index) {
                return FuncUtil::applyOrItself($default, $index);
            }
        }
        $it->rewind();
        if (!$it->valid()) {
            return FuncUtil::applyOrItself($default, $index);
        }
        for ($i = 0; $i < $index; $i++) {
            $it->next();
            if (!$it->valid()) {
                return FuncUtil::applyOrItself($default, $index);
            }
        }
        return $it->key();
    }

    /**
     * @return Ginq
     */
    public function memoize()
    {
        return self::from(self::$gen->memoize($this->getIterator()));
    }

    /**
     * @param int $chunkSize
     * @return Ginq
     */
    public function buffer($chunkSize)
    {
        return self::from(
            self::$gen->buffer($this->getIterator(), $chunkSize)
        );
    }

    /**
     * @param int   $chunkSize
     * @param mixed $padding
     * @return Ginq
     */
    public function bufferWithPadding($chunkSize, $padding)
    {
        return self::from(
            self::$gen->bufferWithPadding($this->getIterator(), $chunkSize, $padding)
        );
    }

    /**
     * plugin
     */

    public function __call($name, $args)
    {
        if (isset(self::$registeredFunctions[$name])) {
            call_user_func_array(
                self::$registeredFunctions[$name],
                array_merge(array($this), $args)
            );
        }
    }

    private static $registeredFunctions = array();

    public static function register($className)
    {
        $ref = new \ReflectionClass($className);

        //echo "$className";

        $funcNames = Ginq::from($ref->getMethods(\ReflectionMethod::IS_STATIC))
            ->where(function ($m) {
                /** @var $m \ReflectionMethod  */
                return $m->isPublic();
            })
            ->where(function ($m) {
                /** @var $m \ReflectionMethod  */
                /** @var $p \ReflectionParameter */
                $p = Ginq::from($m->getParameters())->firstOrElse(false);
                if ($p === false) return false;

                $c = $p->getClass();

                return ($c->getName() === 'Ginq\Ginq') or $c->isSubclassOf('Ginq\Ginq');
            })
            ->select(function ($m) {
                /** @var $m \ReflectionMethod  */
                return $m->getName();
            });

        foreach ($funcNames as $func) {
            self::$registeredFunctions[$func] = array($className, $func);
        }
    }

    public static function listRegisteredFunctions()
    {
        return self::$registeredFunctions;
    }
}

Ginq::useIterator();


