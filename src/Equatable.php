<?php

namespace UoBParser;

interface Equatable {

    /**
     * Whether this object should be considered equal to another instance
     * of the same class.
     * @param object|null $other Other instance to use for comparison.
     * @return bool
     */
    public function equals($other);

}